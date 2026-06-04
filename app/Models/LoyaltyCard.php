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
            'last_stamp_at'    => 'datetime',
            'birth_date'       => 'date',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
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
}
