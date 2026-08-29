<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\LaravelMobilePass\Models\MobilePass;

class LoyaltyCard extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'loyalty_program_id',
        'current_prize_system_id',
        'first_name',
        'last_name',
        'birth_date',
        'holder_identifier',
        'stamps_collected',
        'is_completed',
        'completed_at',
        'cycle_started_at',
        'last_stamp_at',
        'google_pass_id',
        'apple_pass_id',
    ];

    protected function casts(): array
    {
        return [
            'stamps_collected' => 'integer',
            'is_completed'     => 'boolean',
            'completed_at'     => 'datetime',
            'cycle_started_at' => 'datetime',
            'last_stamp_at'    => 'datetime',
            'birth_date'       => 'date',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Nombre para mostrar en la tarjeta de Apple/Google Wallet: solo la primera palabra de
     * nombre y apellido (si el cliente escribió varios), en Mayúscula Inicial. El registro en
     * base de datos (first_name/last_name) conserva siempre lo que el cliente escribió completo.
     */
    public function walletHolderName(): string
    {
        return trim($this->firstWordTitleCase($this->first_name) . ' ' . $this->firstWordTitleCase($this->last_name));
    }

    private function firstWordTitleCase(?string $value): string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        $firstWord = preg_split('/\s+/u', $value)[0];

        return mb_convert_case($firstWord, MB_CASE_TITLE, 'UTF-8');
    }

    public function loyaltyProgram(): BelongsTo
    {
        // withTrashed ensures soft-deleted programs still load on the card — avoids null crashes.
        return $this->belongsTo(LoyaltyProgram::class)->withTrashed();
    }

    public function currentPrizeSystem(): BelongsTo
    {
        return $this->belongsTo(LoyaltyPrizeSystem::class, 'current_prize_system_id');
    }

    public function resolvedPrizeSystem(): ?LoyaltyPrizeSystem
    {
        if ($this->current_prize_system_id) {
            return $this->currentPrizeSystem;
        }
        return $this->loyaltyProgram?->prizeSystems()->first();
    }

    public function stampTransactions(): HasMany
    {
        return $this->hasMany(StampTransaction::class);
    }

    public function rewardRedemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class);
    }

    public function milestoneRedemptions(): HasMany
    {
        return $this->hasMany(MilestoneRedemption::class);
    }

    public function googlePass(): ?MobilePass
    {
        if (! $this->google_pass_id) {
            return null;
        }

        return MobilePass::find($this->google_pass_id);
    }

    public function applePass(): ?MobilePass
    {
        if (! $this->apple_pass_id) {
            return null;
        }

        return MobilePass::find($this->apple_pass_id);
    }

    public function progressText(): string
    {
        $total = $this->loyaltyProgram?->total_stamps ?? '?';

        return $this->stamps_collected . ' / ' . $total . ' visitas';
    }

    public function nextRewardText(): string
    {
        $program = $this->loyaltyProgram;

        if (! $program) {
            return '—';
        }

        $next        = $this->nextMilestone();
        $rewardTitle = $this->resolvedPrizeSystem()?->reward_title ?? '—';

        if ($next) {
            $remaining = $next->stamp_count - $this->stamps_collected;
            if ($remaining <= 0) {
                return '¡Premio disponible: ' . $next->reward_title . '!';
            }

            return $remaining === 1
                ? '¡1 visita para: ' . $next->reward_title . '!'
                : 'Próximo premio en ' . $remaining . ' visitas: ' . $next->reward_title;
        }

        $remaining = $program->total_stamps - $this->stamps_collected;

        if ($remaining <= 0) {
            return '¡Premio disponible: ' . $rewardTitle . '!';
        }

        return $remaining === 1
            ? '¡1 visita para completar!'
            : 'Te faltan ' . $remaining . ' visitas para tu próximo premio';
    }

    /**
     * Lista de premios para el reverso de la tarjeta (Apple y Google Wallet):
     * "Visita 2: Café gratis (Espresso o americano)" — la descripción solo se agrega
     * entre paréntesis cuando el premio la tiene configurada.
     */
    public function prizesListText(): string
    {
        $program     = $this->loyaltyProgram;
        $prizeSystem = $this->resolvedPrizeSystem();
        $lines       = [];

        foreach ($prizeSystem?->milestones ?? collect() as $m) {
            $lines[] = 'Visita ' . $m->stamp_count . ': ' . $m->reward_title . $this->parenthesizedDescription($m->reward_description);
        }

        $lines[] = 'Visita ' . $program->total_stamps . ': '
            . ($prizeSystem?->reward_title ?? '—') . $this->parenthesizedDescription($prizeSystem?->reward_description) . ' ★';

        return implode("\n", $lines);
    }

    private function parenthesizedDescription(?string $description): string
    {
        return filled($description) ? ' (' . $description . ')' : '';
    }

    public function nextMilestone(): ?LoyaltyMilestone
    {
        return $this->resolvedPrizeSystem()?->milestones()
            ->where('stamp_count', '>', $this->stamps_collected)
            ->first();
    }

    public function stampVisual(): string
    {
        $program = $this->loyaltyProgram;

        if (! $program) {
            return '—';
        }

        $icon             = $program->stampIconLabel();
        $milestoneCounts  = $this->resolvedPrizeSystem()?->milestoneCounts() ?? [];

        $parts = [];
        for ($i = 1; $i <= $program->total_stamps; $i++) {
            $isMilestone = in_array($i, $milestoneCounts, true);

            if ($i <= $this->stamps_collected) {
                $parts[] = $isMilestone ? '★' : $icon;
            } else {
                $parts[] = $isMilestone ? '☆' : '○';
            }
        }

        return implode(' ', $parts);
    }

    public function isReadyForReward(): bool
    {
        return $this->stamps_collected >= ($this->loyaltyProgram?->total_stamps ?? PHP_INT_MAX);
    }

    /**
     * Vigencia del ciclo actual. Se ancla a cycle_started_at (se reinicia con cada canje o
     * reinicio por vencimiento) y no a created_at, que siempre debe reflejar la fecha real
     * de alta del cliente ("Miembro desde" en la tarjeta).
     */
    public function validUntil(): ?\Illuminate\Support\Carbon
    {
        $months = $this->loyaltyProgram?->validity_months;
        $anchor = $this->cycle_started_at ?? $this->created_at;

        if (! $months || ! $anchor) {
            return null;
        }

        return $anchor->copy()->addMonths($months);
    }

    public function isExpired(): bool
    {
        return (bool) $this->validUntil()?->isPast();
    }
}
