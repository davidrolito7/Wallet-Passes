<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MilestoneRedemption extends Model
{
    protected $fillable = [
        'loyalty_card_id',
        'loyalty_milestone_id',
        'redeemed_by',
        'triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'triggered_at' => 'datetime',
        ];
    }

    public function loyaltyCard(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(LoyaltyMilestone::class, 'loyalty_milestone_id');
    }
}
