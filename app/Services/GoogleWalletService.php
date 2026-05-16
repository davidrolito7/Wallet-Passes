<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassBuilder;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassClass;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;

class GoogleWalletService
{
    public function __construct(private StampImageService $stampImage) {}

    public function ensureClass(LoyaltyProgram $program): void
    {
        $business = $program->business;

        LoyaltyPassClass::make($program->googleClassSuffix())
            ->setIssuerName($business->name)
            ->setProgramName($program->name)
            ->setProgramLogoUrl($business->logo_url ?? config('app.url') . '/images/default-logo.png')
            ->setRewardsTier($program->reward_title)
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

        $barcodeValue = 'loyalty:' . $card->id . ':' . md5($card->id . $card->created_at);

        // Build base pass via Spatie builder
        $pass = LoyaltyPassBuilder::make()
            ->setClass($program->googleClassSuffix())
            ->setAccountId('CARD-' . str_pad($card->id, 6, '0', STR_PAD_LEFT))
            ->setAccountName($card->holder_name)
            ->setBalanceString($this->balanceString($card))
            ->setBarcode(BarcodeType::Qr, $barcodeValue)
            ->save();

        // Enrich payload with visual fields and push to Google (triggers MobilePass::boot() → patch)
        $content = $pass->content;
        $content['googleObjectPayload'] = $this->enrichPayload(
            $content['googleObjectPayload'],
            $card,
            true
        );
        $pass->update(['content' => $content]);

        return $pass;
    }

    public function updatePass(LoyaltyCard $card): void
    {
        $pass = $card->googlePass();

        if (! $pass) {
            return;
        }

        $content = $pass->content;

        // Regenerate stamp image for the new stamp count
        $content['googleObjectPayload']['loyaltyPoints']['balance']['string'] = $this->balanceString($card);
        $content['googleObjectPayload'] = $this->enrichPayload(
            $content['googleObjectPayload'],
            $card,
            false
        );

        // update() triggers MobilePass::boot() → PushPassUpdateJob → Google API PATCH
        $pass->update(['content' => $content]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payload enrichment
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Add premium visual fields to the Google Wallet object payload:
     * - heroImage: dynamic stamp image
     * - imageModulesData: secondary stamp image
     * - textModulesData: member since, next reward, progress
     */
    private function enrichPayload(array $payload, LoyaltyCard $card, bool $regenerate): array
    {
        $program = $card->loyaltyProgram;

        // ── Stamp image ──────────────────────────────────────────────────────
        $imageUrl = $regenerate
            ? $this->stampImage->regenerateFor($card)
            : $this->stampImage->urlFor($card);

        if ($imageUrl) {
            // heroImage: full-width banner (most prominent)
            $payload['heroImage'] = [
                'sourceUri' => ['uri' => $imageUrl],
                'contentDescription' => [
                    'defaultValue' => [
                        'language' => 'es',
                        'value'    => $card->progressText() . ' sellos',
                    ],
                ],
            ];

            // imageModulesData: also shown in the pass body on some devices
            $payload['imageModulesData'] = [
                [
                    'mainImage' => [
                        'sourceUri' => ['uri' => $imageUrl],
                        'contentDescription' => [
                            'defaultValue' => [
                                'language' => 'es',
                                'value'    => 'Progreso de sellos',
                            ],
                        ],
                    ],
                    'id' => 'stamp_progress',
                ],
            ];
        }

        // ── Text modules ─────────────────────────────────────────────────────
        $payload['textModulesData'] = $this->buildTextModules($card);

        return $payload;
    }

    /**
     * Build textModulesData sections: progress, next reward, member since.
     */
    private function buildTextModules(LoyaltyCard $card): array
    {
        $program  = $card->loyaltyProgram;
        $business = $program->business;
        $modules  = [];

        // Progress details
        $modules[] = [
            'header' => 'Progreso',
            'body'   => $card->stamps_collected . ' de ' . $program->total_stamps . ' visitas',
            'id'     => 'progress',
        ];

        // Final reward
        $modules[] = [
            'header' => 'Premio al completar',
            'body'   => $program->reward_title . ($program->reward_description ? ' — ' . $program->reward_description : ''),
            'id'     => 'final_reward',
        ];

        // Next milestone if any
        $nextMilestone = $program->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->orderBy('stamp_count')
            ->first();

        if ($nextMilestone) {
            $remaining = $nextMilestone->stamp_count - $card->stamps_collected;
            $modules[] = [
                'header' => 'Próximo premio',
                'body'   => $nextMilestone->reward_title . ' (faltan ' . $remaining . ' ' . ($remaining === 1 ? 'visita' : 'visitas') . ')',
                'id'     => 'next_milestone',
            ];
        }

        // Member since
        if ($card->created_at) {
            $modules[] = [
                'header' => 'Miembro desde',
                'body'   => $card->created_at->translatedFormat('d \d\e F, Y'),
                'id'     => 'member_since',
            ];
        }

        // Completed state
        if ($card->is_completed) {
            $modules[] = [
                'header' => '¡Tarjeta completada!',
                'body'   => 'Visita ' . $business->name . ' para canjear tu premio: ' . $program->reward_title,
                'id'     => 'completed',
            ];
        }

        return $modules;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function balanceString(LoyaltyCard $card): string
    {
        return $card->stamps_collected . '/' . $card->loyaltyProgram->total_stamps . ' visitas';
    }
}
