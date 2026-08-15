<?php

namespace App\Services;

use App\Models\Business;
use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\StampImageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassBuilder;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassClass;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;
use Spatie\LaravelMobilePass\Support\Google\GoogleJwtSigner;
use Spatie\LaravelMobilePass\Support\Google\GoogleWalletClient;

class GoogleWalletService
{
    /**
     * Google Wallet allows a maximum of 3 notification-triggering messages
     * per pass within a 24-hour period. Keep the local guard aligned with Google.
     */
    private const MAX_PUSH_NOTIFICATIONS_PER_DAY = 3;

    private const WALLET_API_BASE_URL = 'https://walletobjects.googleapis.com/walletobjects/v1';
    private const FIELD_UPDATE_NOTIFY_PREFERENCE = 'notifyOnUpdate';

    public function __construct(
        private GoogleWalletClient $client,
        private GoogleJwtSigner $jwtSigner,
    ) {}

    public function ensureClass(LoyaltyProgram $program): void
    {
        $business = $program->business;

        $class = LoyaltyPassClass::make($program->googleClassSuffix())
            ->setIssuerName($business->name)
            ->setProgramName($program->name)
            ->setProgramLogoUrl($business->logoPublicUrl() ?? config('app.url').'/images/default-logo.png')
            ->setRewardsTier($program->prizeSystems()->value('reward_title') ?? '—')
            ->setRewardsTierLabel('Premio')
            ->setAccountNameLabel('Miembro')
            ->setAccountIdLabel('Tarjeta')
            ->setBackgroundColor($business->primary_color)
            ->save();

        $this->syncMerchantLocation($class->id(), $business);
    }

    /**
     * Ubicación para que la tarjeta aparezca sola en la pantalla de bloqueo al acercarse al
     * negocio. El builder de Spatie no expone merchantLocations, así que se manda directo por
     * PATCH. Vive en LoyaltyClass (no en el objeto), aplica a todas las tarjetas del programa,
     * y Google decide el radio de activación por su cuenta — solo se le da la coordenada.
     */
    private function syncMerchantLocation(string $classId, Business $business): void
    {
        if ($business->latitude === null || $business->longitude === null) {
            return;
        }

        try {
            $this->client->patchClass('loyaltyClass', $classId, [
                'merchantLocations' => [
                    [
                        'latitude'  => (float) $business->latitude,
                        'longitude' => (float) $business->longitude,
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            Log::warning('GoogleWallet: merchantLocations patch failed', [
                'class_id' => $classId,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    public function createPass(LoyaltyCard $card): MobilePass
    {
        $program = $card->loyaltyProgram;
        $this->ensureClass($program);

        $barcodeValue = 'loyalty:'.$card->id.':'.md5($card->id.$card->created_at);

        // save() inserts the basic payload into Google Wallet and creates the MobilePass DB record.
        // The created event does NOT trigger the Spatie update observer, so no double PATCH.
        $pass = LoyaltyPassBuilder::make()
            ->setClass($program->googleClassSuffix())
            ->setAccountId('CARD-'.str_pad($card->id, 6, '0', STR_PAD_LEFT))
            ->setAccountName($card->fullName())
            ->setBalanceString($this->balanceString($card))
            ->setBarcode(BarcodeType::Qr, $barcodeValue)
            ->save();

        // Enrich the payload (heroImage, textModules) and persist to DB.
        // Use withoutEvents so Spatie does not trigger a second auto-PATCH.
        $content = $pass->content;
        $content['googleObjectPayload'] = $this->buildPayload($content['googleObjectPayload'], $card);
        MobilePass::withoutEvents(fn () => $pass->update(['content' => $content]));

        // Send the enriched payload to Google directly (no notification on creation).
        $objectId = $this->resolveObjectId($pass);
        if ($objectId) {
            try {
                $this->client->patchObject('loyaltyObject', $objectId, $content['googleObjectPayload']);
            } catch (\Throwable $e) {
                Log::warning('GoogleWallet: initial PATCH failed on createPass', [
                    'object_id' => $objectId,
                    'card_id'   => $card->id,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $pass->fresh();
    }

    /**
     * Update the Google Wallet LoyaltyObject for a card after a stamp change.
     *
     * Google Wallet has two different notification mechanisms:
     *   balance_update_only — PATCH loyaltyPoints.balance with notifyPreference.
     *   custom_message_only — PATCH the object, then AddMessage TEXT_AND_NOTIFY.
     *   both                — avoid double notifications by preferring AddMessage when a
     *                          custom visit message exists; otherwise fallback to notifyPreference.
     *
     * The PATCH body is intentionally small. Google PATCH requests replace arrays that are
     * included in the request, so only send the fields that this service owns.
     *
     * @param  bool  $notify           Whether this update may trigger a Google Wallet notification.
     * @param  bool  $sendVisitMessage Send the program's configured per-visit message.
     */
    public function updatePass(LoyaltyCard $card, bool $notify = true, bool $sendVisitMessage = true): void
    {
        $card->loadMissing('loyaltyProgram.business', 'loyaltyProgram.milestones');

        $pass = $card->googlePass();

        if (! $pass) {
            Log::warning('GoogleWallet: no MobilePass found for card', [
                'card_id'        => $card->id,
                'google_pass_id' => $card->google_pass_id,
            ]);
            return;
        }

        $objectId = $this->resolveObjectId($pass);

        if (! $objectId) {
            Log::error('GoogleWallet: missing googleObjectId in MobilePass content', [
                'mobile_pass_id' => $pass->id,
                'card_id'        => $card->id,
            ]);
            return;
        }

        $content = $pass->content;
        $payload = $this->buildPayload($content['googleObjectPayload'], $card);
        $content['googleObjectPayload'] = $payload;

        MobilePass::withoutEvents(fn () => $pass->update(['content' => $content]));

        $program = $card->loyaltyProgram;
        $mode = $program->google_wallet_notification_mode ?? 'custom_message_only';

        $hasCustomMessage = $sendVisitMessage
            && ($program->visit_notification_enabled ?? false)
            && filled($program->visit_notification_message);

        // Do not send two Google Wallet notifications for the same visit unless you
        // intentionally change this logic. AddMessage is the only flow that shows your text.
        $shouldSendMessage = $notify
            && $hasCustomMessage
            && in_array($mode, ['custom_message_only', 'both'], true);

        // Fallback to the field-update notification when there is no custom message flow.
        $includeNotifyPreference = $notify && ! $shouldSendMessage && in_array($mode, [
            'balance_update_only',
            'both',
            'custom_message_only', // fallback when the custom message is not configured
        ], true);

        $patchPayload = $this->patchPayloadForUpdate($payload);

        if ($includeNotifyPreference) {
            // Transient field. Do not persist it to DB; set it on every PATCH that should notify.
            $patchPayload['notifyPreference'] = self::FIELD_UPDATE_NOTIFY_PREFERENCE;
        }

        try {
            $this->client->patchObject('loyaltyObject', $objectId, $patchPayload);

            Log::info('GoogleWallet: PATCH successful', [
                'object_id'         => $objectId,
                'card_id'           => $card->id,
                'balance'           => $payload['loyaltyPoints']['balance']['string'] ?? null,
                'notifyPreference'  => $includeNotifyPreference ? self::FIELD_UPDATE_NOTIFY_PREFERENCE : 'none',
                'notification_mode' => $mode,
            ]);
        } catch (\Throwable $e) {
            Log::error('GoogleWallet: PATCH failed', [
                'object_id' => $objectId,
                'card_id'   => $card->id,
                'error'     => $e->getMessage(),
            ]);
            return;
        }

        if ($shouldSendMessage) {
            $this->sendVisitNotificationMessage($card);
        }
    }

    /**
     * Send the program's configured per-visit notification message via Google Wallet addMessage.
     * Called automatically by updatePass() when visit_notification_enabled is true.
     */
    public function sendVisitNotificationMessage(LoyaltyCard $card): void
    {
        $card->loadMissing('loyaltyProgram.business', 'loyaltyProgram.milestones');

        $pass = $card->googlePass();
        if (! $pass) {
            return;
        }

        $objectId = $this->resolveObjectId($pass);
        if (! $objectId) {
            return;
        }

        $program = $card->loyaltyProgram;

        $rawMessage = filled($program->visit_notification_message)
            ? $program->visit_notification_message
            : 'Llevas {stamps_collected}/{total_stamps} visitas.';

        $message   = $this->resolveTemplate($rawMessage, $card);
        $messageId = 'visit-'.$card->id.'-'.$card->stamps_collected.'-'.Str::uuid()->toString();
        $header    = $program->business->name ?? $program->name;

        Log::info('GoogleWallet: sending visit notification message', [
            'object_id'  => $objectId,
            'card_id'    => $card->id,
            'message_id' => $messageId,
            'message'    => $message,
        ]);

        $this->callAddMessage($objectId, $messageId, $message, notify: true, header: $header);
    }

    /**
     * Send a one-off message to a card's Google Wallet pass.
     * Use this for birthday greetings, promotions, manual alerts, etc.
     * Does NOT modify the stamp count or balance.
     *
     * @param  bool  $notify  true → TEXT_AND_NOTIFY (push); false → TEXT (silent message on pass details).
     */
    public function sendMessage(LoyaltyCard $card, string $message, bool $notify = true, string $header = ''): void
    {
        $card->loadMissing('loyaltyProgram.business');

        $pass = $card->googlePass();

        if (! $pass) {
            Log::warning('GoogleWallet: sendMessage — no MobilePass found', ['card_id' => $card->id]);
            return;
        }

        $objectId = $this->resolveObjectId($pass);

        if (! $objectId) {
            Log::error('GoogleWallet: sendMessage — missing googleObjectId', [
                'mobile_pass_id' => $pass->id,
                'card_id'        => $card->id,
            ]);
            return;
        }

        $messageId = 'msg-'.$card->id.'-'.Str::uuid()->toString();
        $resolvedHeader = filled($header)
            ? $header
            : ($card->loyaltyProgram->business->name ?? $card->loyaltyProgram->name ?? config('app.name'));

        Log::info('GoogleWallet: sending manual message', [
            'object_id'  => $objectId,
            'card_id'    => $card->id,
            'message_id' => $messageId,
            'message'    => $message,
            'notify'     => $notify,
        ]);

        $this->callAddMessage($objectId, $messageId, $message, $notify, $resolvedHeader);
    }

    // ── Payload builders ──────────────────────────────────────────────────────

    private function buildPayload(array $payload, LoyaltyCard $card): array
    {
        $program = $card->loyaltyProgram;

        // This is the only LoyaltyObject field that can trigger a field-update notification.
        $payload['loyaltyPoints']['balance']['string'] = $this->balanceString($card);
        // Google defaults this label to "Points" ("Puntos" in Spanish locales) when unset.
        $payload['loyaltyPoints']['label'] = 'Visitas';

        // Hero image: stamp grid when assets are configured, otherwise static background.
        if ($program->filled_stamp_image || $program->empty_stamp_image) {
            $heroUrl = app(StampImageService::class)->urlFor($card);
        } else {
            $heroUrl = $program->backgroundImageUrl();
        }

        if ($heroUrl) {
            $payload['heroImage'] = [
                'sourceUri'          => ['uri' => $this->versionedImageUrl($heroUrl, $card)],
                'contentDescription' => [
                    'defaultValue' => ['language' => 'es', 'value' => $program->name],
                ],
            ];
        }

        $payload['textModulesData'] = $this->textModules($card);

        return $payload;
    }

    /**
     * PATCH only the fields owned by this service. Google replaces array fields that are included
     * in a PATCH body, so avoid sending an old full object snapshot.
     */
    private function patchPayloadForUpdate(array $payload): array
    {
        return array_filter([
            'loyaltyPoints'   => $payload['loyaltyPoints'] ?? null,
            'heroImage'       => $payload['heroImage'] ?? null,
            'textModulesData' => $payload['textModulesData'] ?? null,
        ], static fn ($value) => $value !== null);
    }

    /**
     * Google Wallet images are referenced by URI. When the stamp-grid image is regenerated at the
     * same public URL, Wallet may keep showing the cached image until the pass is reopened. Add a
     * stable version query string for public URLs so each stamp update points to a fresh image URI.
     */
    private function versionedImageUrl(string $url, LoyaltyCard $card): string
    {
        // Do not alter signed URLs: appending a query string can invalidate the signature.
        if (str_contains($url, 'X-Amz-Signature') || str_contains($url, 'X-Goog-Signature')) {
            return $url;
        }

        $version = implode('-', array_filter([
            'card'.$card->id,
            'stamps'.$card->stamps_collected,
            $card->updated_at?->timestamp,
        ]));

        return $url.(str_contains($url, '?') ? '&' : '?').'gwv='.rawurlencode($version ?: (string) time());
    }

    private function textModules(LoyaltyCard $card): array
    {
        $program = $card->loyaltyProgram;
        $modules = [];

        $modules[] = [
            'header' => 'Visitas',
            'body'   => $card->stamps_collected.' de '.$program->total_stamps,
            'id'     => 'progress',
        ];

        $modules[] = [
            'header' => 'Próximo Premio',
            'body'   => $this->nextRewardText($card),
            'id'     => 'next_reward',
        ];

        if ($card->created_at) {
            $modules[] = [
                'header' => 'Miembro desde',
                'body'   => $card->created_at->translatedFormat('F Y'),
                'id'     => 'member_since',
            ];
        }

        if ($validUntil = $card->validUntil()) {
            $modules[] = [
                'header' => 'Vigencia',
                'body'   => $validUntil->translatedFormat('F Y'),
                'id'     => 'validity',
            ];
        }

        return $modules;
    }

    // ── Google Wallet API: addMessage ─────────────────────────────────────────

    /**
     * POST /loyaltyObject/{objectId}/addMessage
     *
     * messageType TEXT_AND_NOTIFY adds the message to the pass details and triggers an
     * Android Google Wallet notification. messageType TEXT adds the message silently.
     */
    private function callAddMessage(
        string $objectId,
        string $messageId,
        string $body,
        bool $notify = true,
        string $header = '',
    ): void {
        $objectState = $this->fetchObjectState($objectId);

        if (! $objectState['exists']) {
            Log::warning('GoogleWallet: addMessage skipped — object not found or could not be fetched', [
                'object_id' => $objectId,
                'state'     => $objectState['state'],
            ]);
            return;
        }

        $state = $objectState['state'];
        $normalizedState = filled($state) ? strtoupper((string) $state) : 'ACTIVE';

        if (! in_array($normalizedState, ['ACTIVE', 'STATE_UNSPECIFIED'], true)) {
            Log::warning('GoogleWallet: addMessage skipped — pass is not ACTIVE', [
                'object_id' => $objectId,
                'state'     => $state,
            ]);
            return;
        }

        $cacheKey = "gw_push_count_24h:{$objectId}";
        $count = (int) cache()->get($cacheKey, 0);

        if ($notify && $count >= self::MAX_PUSH_NOTIFICATIONS_PER_DAY) {
            Log::warning('GoogleWallet: addMessage skipped — Google 24h notification limit reached locally', [
                'object_id' => $objectId,
                'count'     => $count,
                'limit'     => self::MAX_PUSH_NOTIFICATIONS_PER_DAY,
            ]);
            return;
        }

        // addMessage always appends — it never replaces a message with the same id — so the
        // pass details screen accumulates every past message forever. Clear the list first so
        // only the message we are about to send is visible.
        $this->clearMessages($objectId);

        $url = sprintf(
            '%s/loyaltyObject/%s/addMessage',
            $this->googleWalletApiBaseUrl(),
            rawurlencode($objectId),
        );

        $messageType = $notify ? 'TEXT_AND_NOTIFY' : 'TEXT';

        $message = [
            'header'      => filled($header) ? $header : (string) config('app.name', 'Wallet'),
            'body'        => $body,
            'id'          => $messageId,
            'messageType' => $messageType,
        ];

        try {
            $response = $this->googleHttpRequest()
                ->asJson()
                ->post($url, ['message' => $message]);

            if ($response->failed()) {
                Log::error('GoogleWallet: addMessage failed', [
                    'object_id'    => $objectId,
                    'message_id'   => $messageId,
                    'message_type' => $messageType,
                    'status'       => $response->status(),
                    'response'     => $response->json() ?? $response->body(),
                ]);
                return;
            }

            if ($notify) {
                cache()->put($cacheKey, $count + 1, now()->addDay());
            }

            Log::info('GoogleWallet: addMessage sent successfully', [
                'object_id'    => $objectId,
                'message_id'   => $messageId,
                'header'       => $message['header'],
                'body'         => $body,
                'message_type' => $messageType,
            ]);
        } catch (\Throwable $e) {
            Log::error('GoogleWallet: addMessage exception', [
                'object_id' => $objectId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * addMessage and fetchObjectState call the Google Wallet REST API directly instead of going
     * through Spatie's GoogleWalletClient (it has no addMessage primitive). That client retries
     * transient failures automatically (`retry(3, 200, ...)`); our raw calls did not, which made
     * message delivery to Android noticeably less reliable than every other pass update. Mirror
     * the same retry policy here so a single network blip or 5xx doesn't just drop the message.
     */
    private function googleHttpRequest(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->jwtSigner->accessToken())
            ->acceptJson()
            ->retry(3, 200, function (\Throwable $exception) {
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }

                if ($exception instanceof \Illuminate\Http\Client\RequestException) {
                    return $exception->response->serverError();
                }

                return false;
            }, throw: false);
    }

    /**
     * PATCH /loyaltyObject/{objectId} with an empty messages array.
     * Google replaces array fields sent in a PATCH, so this wipes the pass's message history
     * before sending a fresh one via addMessage — otherwise old messages never disappear.
     */
    private function clearMessages(string $objectId): void
    {
        try {
            $this->client->patchObject('loyaltyObject', $objectId, ['messages' => []]);
        } catch (\Throwable $e) {
            Log::warning('GoogleWallet: clearMessages failed', [
                'object_id' => $objectId,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /loyaltyObject/{objectId} — verifies existence and object state in Google Wallet.
     * Returns ['exists' => bool, 'state' => string|null]
     */
    private function fetchObjectState(string $objectId): array
    {
        $url = sprintf(
            '%s/loyaltyObject/%s',
            $this->googleWalletApiBaseUrl(),
            rawurlencode($objectId),
        );

        try {
            $response = $this->googleHttpRequest()->get($url);

            if ($response->successful()) {
                return ['exists' => true, 'state' => $response->json('state')];
            }

            Log::warning('GoogleWallet: fetchObjectState failed', [
                'object_id' => $objectId,
                'status'    => $response->status(),
                'response'  => $response->json() ?? $response->body(),
            ]);

            return ['exists' => false, 'state' => null];
        } catch (\Throwable $e) {
            Log::warning('GoogleWallet: fetchObjectState exception', [
                'object_id' => $objectId,
                'error'     => $e->getMessage(),
            ]);
            return ['exists' => false, 'state' => null];
        }
    }

    private function googleWalletApiBaseUrl(): string
    {
        $configured = (string) config('mobile-pass.google.api_base_url', '');

        return rtrim($configured !== '' ? $configured : self::WALLET_API_BASE_URL, '/');
    }

    private function resolveObjectId(MobilePass $pass): ?string
    {
        // The full Google Wallet object ID (format: ISSUER_ID.suffix) is stored by Spatie
        // in content['googleObjectId'] at pass creation time. This is NOT the same as
        // the local MobilePass UUID (google_pass_id on LoyaltyCard).
        return $pass->content['googleObjectId'] ?? null;
    }

    /**
     * Replace template variables with card/program values.
     *
     * Supported placeholders:
     *   {first_name}       {full_name}          {stamps_collected}
     *   {total_stamps}     {remaining_stamps}   {business_name}
     *   {program_name}     {next_reward}         {reward_title}
     */
    private function resolveTemplate(string $template, LoyaltyCard $card): string
    {
        $program     = $card->loyaltyProgram;
        $business    = $program->business;
        $prizeSystem = $card->resolvedPrizeSystem();

        $next = $prizeSystem?->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->first();

        $remaining = max(0, ($next?->stamp_count ?? $program->total_stamps) - $card->stamps_collected);

        $rewardTitle = $prizeSystem?->reward_title ?? '—';

        $vars = [
            '{first_name}'       => $card->first_name,
            '{full_name}'        => $card->fullName(),
            '{stamps_collected}' => $card->stamps_collected,
            '{total_stamps}'     => $program->total_stamps,
            '{remaining_stamps}' => $remaining,
            '{business_name}'    => $business->name,
            '{program_name}'     => $program->name,
            '{next_reward}'      => $next?->reward_title ?? $rewardTitle,
            '{reward_title}'     => $rewardTitle,
        ];

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    private function nextRewardText(LoyaltyCard $card): string
    {
        $program     = $card->loyaltyProgram;
        $prizeSystem = $card->resolvedPrizeSystem();

        $next = $prizeSystem?->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->first();

        if ($next) {
            $remaining = $next->stamp_count - $card->stamps_collected;
            return $remaining <= 0
                ? '¡'.$next->reward_title.' disponible!'
                : $next->reward_title.' — faltan '.$remaining.' '.($remaining === 1 ? 'visita' : 'visitas');
        }

        $rewardTitle = $prizeSystem?->reward_title ?? '—';
        $remaining   = $program->total_stamps - $card->stamps_collected;

        if ($remaining <= 0) {
            return '¡'.$rewardTitle.' disponible!';
        }

        return $rewardTitle.' — faltan '.$remaining.' '.($remaining === 1 ? 'visita' : 'visitas');
    }

    private function balanceString(LoyaltyCard $card): string
    {
        return $card->stamps_collected.'/'.$card->loyaltyProgram->total_stamps.' visitas';
    }
}
