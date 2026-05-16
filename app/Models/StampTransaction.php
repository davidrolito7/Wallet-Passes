<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StampTransaction extends Model
{
    protected $fillable = [
        'loyalty_card_id',
        'stamps_added',
        'stamps_after',
        'note',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'stamps_added' => 'integer',
            'stamps_after' => 'integer',
        ];
    }

    public function loyaltyCard(): BelongsTo
    {
        return $this->belongsTo(LoyaltyCard::class);
    }
}
