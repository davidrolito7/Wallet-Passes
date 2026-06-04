<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LoyaltyPrizeSystem extends Model
{
    protected $fillable = [
        'loyalty_program_id',
        'sort_order',
        'reward_title',
        'reward_description',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function loyaltyProgram(): BelongsTo
    {
        return $this->belongsTo(LoyaltyProgram::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(LoyaltyMilestone::class)->orderBy('stamp_count');
    }

    /** @return int[] */
    public function milestoneCounts(): array
    {
        return $this->milestones->map(fn ($m) => $m->stamp_count)->toArray();
    }
}
