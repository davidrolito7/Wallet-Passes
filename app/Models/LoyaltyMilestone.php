<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyMilestone extends Model
{
    protected $fillable = [
        'loyalty_program_id',
        'stamp_count',
        'reward_title',
        'reward_description',
        'is_repeatable',
    ];

    protected function casts(): array
    {
        return [
            'stamp_count'   => 'integer',
            'is_repeatable' => 'boolean',
        ];
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(MilestoneRedemption::class);
    }

    public function wasAlreadyTriggeredFor(LoyaltyCard $card): bool
    {
        if ($this->is_repeatable) {
            return false;
        }

        return $this->redemptions()
            ->where('loyalty_card_id', $card->id)
            ->exists();
    }
}
