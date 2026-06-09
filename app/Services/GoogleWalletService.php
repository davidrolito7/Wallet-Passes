<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\StampImageService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassBuilder;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassClass;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;
use Spatie\LaravelMobilePass\Support\Google\GoogleJwtSigner;
use Spatie\LaravelMobilePass\Support\Google\GoogleWalletClient;

class GoogleWalletService
{
    private const MAX_PUSH_NOTIFICATIONS_PER_DAY = 5;

    public function __construct(
        private GoogleWalletClient $client,
        private GoogleJwtSigner $jwtSigner,
    ) {}

    public function ensureClass(LoyaltyProgram $program): void
    {
        $business = $program->business;

        LoyaltyPassClass::make($program->googleClassSuffix())
            ->setIssuerName($business->name)
            ->setProgramName($program->name)
            ->setProgramLogoUrl($business->logoPublicUrl() ?? config('app.url').'/images/default-logo.png')
            ->setRewardsTier($program->prizeSystems()->value('reward_title') ?? '—')
            ->setRewardsTierLabel('Premio')
            ->setAccountNameLabel('Miembro')
            ->setAccountIdLabel('Tarjeta')
            ->setBackgroundColor($business->primary_color)
            ->save();
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
     * Notification strategy (controlled by program.google_wallet_notification_mode):
     *   balance_update_only  — PATCH with notifyPreference:"notifyOnUpdate", no addMessage.
     *   custom_message_only  — PATCH without notifyPreference, addMessage TEXT_AND_NOTIFY.
     *   both                 — PATCH with notifyPreference AND addMessage (two notifications).
     *
     * @param  bool  $notify           Add notifyPreference to PATCH when strategy allows it.
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

        // Build the updated payload and persist to DB without triggering the Spatie auto-PATCH.
        $content = $pass->content;
        $payload = $this->buildPayload($content['googleObjectPayload'], $card);
        $content['googleObjectPayload'] = $payload;

        MobilePass::withoutEvents(fn () => $pass->update(['content' => $content]));

        // Determine notification strategy.
        $program         = $card->loyaltyProgram;
        $mode            = $program->google_wallet_notification_mode ?? 'balance_update_only';
        $hasCustomMessage = $sendVisitMessage
            && ($program->visit_notification_enabled ?? false)
            && filled($program->visit_notification_message);

        // Only include notifyPreference when the strategy calls for a balance-update notification.
        $includeNotify = $notify && match ($mode) {
            'custom_message_only' => ! $hasCustomMessage,   // fallback a balance-update si no hay mensaje configurado
            'balance_update_only' => true,
            'both'                => true,
            default               => ! $hasCustomMessage,
        };

        $patchPayload = $payload;
        if ($includeNotify) {
            // notifyPreference is added only for this PATCH call; it is never persisted to DB.
            $patchPayload['notifyPreference'] = 'notifyOnUpdate';
        }

        try {
            $this->client->patchObject('loyaltyObject', $objectId, $patchPayload);

            Log::info('GoogleWallet: PATCH successful', [
                'object_id'          => $objectId,
                'card_id'            => $card->id,
                'balance'            => $payload['loyaltyPoints']['balance']['string'] ?? null,
                'notify_preference'  => $includeNotify ? 'notifyOnUpdate' : 'none',
                'notification_mode'  => $mode,
            ]);
        } catch (\Throwable $e) {
            Log::error('GoogleWallet: PATCH failed', [
                'object_id' => $objectId,
                'card_id'   => $card->id,
                'error'     => $e->getMessage(),
            ]);
            return;
        }

        // Send the per-visit custom message if the mode and configuration call for it.
        $shouldSendMessage = $hasCustomMessage && in_array($mode, ['custom_message_only', 'both'], true);
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
        $messageId = 'visit-'.$card->id.'-'.$card->stamps_collected.'-'.time();
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
     * @param  bool  $notify  true → TEXT_AND_NOTIFY (push); false → TEXT (silent).
     */
    public function sendMessage(LoyaltyCard $card, string $message, bool $notify = true, string $header = ''): void
    {
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

        $messageId     = 'msg-'.$card->id.'-'.time();
        $resolvedHeader = filled($header)
            ? $header
            : ($card->loyaltyProgram->business->name ?? $card->loyaltyProgram->name ?? '');

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

        // Always reflect the current stamp count in loyaltyPoints.balance.
        $payload['loyaltyPoints']['balance']['string'] = $this->balanceString($card);

        // Hero image: stamp grid when assets are configured, otherwise static background.
        if ($program->filled_stamp_image || $program->empty_stamp_image) {
            $heroUrl = app(StampImageService::class)->urlFor($card);
        } else {
            $heroUrl = $program->backgroundImageUrl();
        }

        if ($heroUrl) {
            $payload['heroImage'] = [
                'sourceUri'          => ['uri' => $heroUrl],
                'contentDescription' => [
                    'defaultValue' => ['language' => 'es', 'value' => $program->name],
                ],
            ];
        }

        $payload['textModulesData'] = $this->textModules($card);

        return $payload;
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

        $modules[] = [
            'header' => 'Premio al Completar',
            'body'   => $card->resolvedPrizeSystem()?->reward_title ?? '—',
            'id'     => 'final_reward',
        ];

        if ($card->created_at) {
            $modules[] = [
                'header' => 'Miembro desde',
                'body'   => $card->created_at->translatedFormat('F Y'),
                'id'     => 'member_since',
            ];
        }

        return $modules;
    }

    // ── Google Wallet API: addMessage ─────────────────────────────────────────

    /**
     * POST /loyaltyObject/{objectId}/addMessage
     *
     * messageType TEXT_AND_NOTIFY triggers a push notification in Android.
     * messageType TEXT adds the message to the pass details silently.
     *
     * {
     *   "message": {
     *     "header": "Nueva visita registrada",
     *     "body": "Llevas 3/10 visitas.",
     *     "id": "visit-42-3-1717200000",
     *     "messageType": "TEXT_AND_NOTIFY"
     *   }
     * }
     */
    private function callAddMessage(
        string $objectId,
        string $messageId,
        string $body,
        bool $notify = true,
        string $header = '',
    ): void {
        if (! $notify) {
            Log::debug('GoogleWallet: callAddMessage notify=false — mensaje silencioso', [
                'object_id'  => $objectId,
                'message_id' => $messageId,
            ]);
        }

        $objectState = $this->fetchObjectState($objectId);

        if (! $objectState['exists']) {
            Log::warning('GoogleWallet: addMessage omitido — objeto no encontrado en Google Wallet', [
                'object_id' => $objectId,
            ]);
            return;
        }

        if (! in_array($objectState['state'], ['ACTIVE', null], true)) {
            Log::warning('GoogleWallet: addMessage omitido — pase no está ACTIVE', [
                'object_id' => $objectId,
                'state'     => $objectState['state'],
            ]);
            return;
        }

        $cacheKey = "gw_push_count:{$objectId}:" . now()->toDateString();

        if ($notify) {
            $count = (int) cache()->get($cacheKey, 0);

            if ($count >= self::MAX_PUSH_NOTIFICATIONS_PER_DAY) {
                Log::warning('GoogleWallet: addMessage omitido — límite de notificaciones alcanzado', [
                    'object_id' => $objectId,
                    'count'     => $count,
                    'limit'     => self::MAX_PUSH_NOTIFICATIONS_PER_DAY,
                ]);
                return;
            }
        }

        $baseUrl     = rtrim((string) config('mobile-pass.google.api_base_url'), '/');
        $url         = "{$baseUrl}/loyaltyObject/{$objectId}/addMessage";
        $messageType = $notify ? 'TEXT_AND_NOTIFY' : 'TEXT';

        $message = [
            'body'        => $body,
            'id'          => $messageId,
            'messageType' => $messageType,
        ];

        if (filled($header)) {
            $message['header'] = $header;
        }

        try {
            $response = Http::withToken($this->jwtSigner->accessToken())
                ->acceptJson()
                ->post($url, ['message' => $message]);

            if ($response->failed()) {
                Log::error('GoogleWallet: addMessage failed', [
                    'object_id'    => $objectId,
                    'message_id'   => $messageId,
                    'message_type' => $messageType,
                    'status'       => $response->status(),
                    'response'     => $response->body(),
                ]);
                return;
            }

            if ($notify) {
                cache()->put($cacheKey, (int) cache()->get($cacheKey, 0) + 1, now()->endOfDay());
            }

            Log::info('GoogleWallet: addMessage sent successfully', [
                'object_id'    => $objectId,
                'message_id'   => $messageId,
                'header'       => $header,
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
     * GET /loyaltyObject/{objectId} — verifica existencia y estado en Google Wallet.
     * Returns ['exists' => bool, 'state' => string|null]
     */
    private function fetchObjectState(string $objectId): array
    {
        $baseUrl = rtrim((string) config('mobile-pass.google.api_base_url'), '/');

        try {
            $response = Http::withToken($this->jwtSigner->accessToken())
                ->acceptJson()
                ->get("{$baseUrl}/loyaltyObject/{$objectId}");

            if ($response->successful()) {
                return ['exists' => true, 'state' => $response->json('state')];
            }

            return ['exists' => false, 'state' => null];
        } catch (\Throwable $e) {
            Log::warning('GoogleWallet: fetchObjectState error', [
                'object_id' => $objectId,
                'error'     => $e->getMessage(),
            ]);
            return ['exists' => false, 'state' => null];
        }
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
