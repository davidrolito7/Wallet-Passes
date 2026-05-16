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
        'holder_name',
        'holder_email',
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
        ];
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
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
        return $this->stamps_collected . ' / ' . $this->loyaltyProgram->total_stamps;
    }

    public function stampVisual(): string
    {
        $program = $this->loyaltyProgram;
        $icon = $program->stampIconLabel();
        $empty = '○';

        $stamps = str_repeat($icon . ' ', $this->stamps_collected);
        $remaining = str_repeat($empty . ' ', max(0, $program->total_stamps - $this->stamps_collected));

        return trim($stamps . $remaining);
    }

    public function isReadyForReward(): bool
    {
        return $this->stamps_collected >= $this->loyaltyProgram->total_stamps;
    }
}
