<?php

namespace App\Services;

use App\Models\LoyaltyCard;
use App\Models\LoyaltyProgram;
use App\Services\StampImageService;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassBuilder;
use Spatie\LaravelMobilePass\Builders\Google\LoyaltyPassClass;
use Spatie\LaravelMobilePass\Enums\BarcodeType;
use Spatie\LaravelMobilePass\Models\MobilePass;

class GoogleWalletService
{
    public function ensureClass(LoyaltyProgram $program): void
    {
        $business = $program->business;

        LoyaltyPassClass::make($program->googleClassSuffix())
            ->setIssuerName($business->name)
            ->setProgramName($program->name)
            ->setProgramLogoUrl($business->logoPublicUrl() ?? config('app.url') . '/images/default-logo.png')
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

        $pass = LoyaltyPassBuilder::make()
            ->setClass($program->googleClassSuffix())
            ->setAccountId('CARD-' . str_pad($card->id, 6, '0', STR_PAD_LEFT))
            ->setAccountName($card->holder_name)
            ->setBalanceString($this->balanceString($card))
            ->setBarcode(BarcodeType::Qr, $barcodeValue)
            ->save();

        $content = $pass->content;
        $content['googleObjectPayload'] = $this->buildPayload($content['googleObjectPayload'], $card);
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
        $content['googleObjectPayload']['loyaltyPoints']['balance']['string'] = $this->balanceString($card);
        $content['googleObjectPayload'] = $this->buildPayload($content['googleObjectPayload'], $card);

        $pass->update(['content' => $content]);
    }

    // ── Payload ───────────────────────────────────────────────────────────────

    private function buildPayload(array $payload, LoyaltyCard $card): array
    {
        $program = $card->loyaltyProgram;

        // Versión con stickers → hero image generada dinámicamente con cuadrícula 3×N
        // Versión de texto    → usa la imagen de fondo estática (o ninguna hero)
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

        // Progreso
        $modules[] = [
            'header' => 'Visitas',
            'body'   => $card->stamps_collected . ' de ' . $program->total_stamps,
            'id'     => 'progress',
        ];

        // Próximo premio
        $modules[] = [
            'header' => 'Próximo Premio',
            'body'   => $this->nextRewardText($card),
            'id'     => 'next_reward',
        ];

        // Premio final
        $modules[] = [
            'header' => 'Premio al Completar',
            'body'   => $program->reward_title,
            'id'     => 'final_reward',
        ];

        // Miembro desde
        if ($card->created_at) {
            $modules[] = [
                'header' => 'Miembro desde',
                'body'   => $card->created_at->translatedFormat('F Y'),
                'id'     => 'member_since',
            ];
        }

        return $modules;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function nextRewardText(LoyaltyCard $card): string
    {
        $program = $card->loyaltyProgram;

        $next = $program->milestones()
            ->where('stamp_count', '>', $card->stamps_collected)
            ->orderBy('stamp_count')
            ->first();

        if ($next) {
            $remaining = $next->stamp_count - $card->stamps_collected;
            return $remaining <= 0
                ? '¡' . $next->reward_title . ' disponible!'
                : $next->reward_title . ' — faltan ' . $remaining . ' ' . ($remaining === 1 ? 'visita' : 'visitas');
        }

        $remaining = $program->total_stamps - $card->stamps_collected;

        if ($remaining <= 0) {
            return '¡' . $program->reward_title . ' disponible!';
        }

        return $program->reward_title . ' — faltan ' . $remaining . ' ' . ($remaining === 1 ? 'visita' : 'visitas');
    }

    private function balanceString(LoyaltyCard $card): string
    {
        return $card->stamps_collected . '/' . $card->loyaltyProgram->total_stamps . ' visitas';
    }
}
