<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyProgram extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'total_stamps',
        'stamp_icon',
        'stamp_icon_url',
        'card_font',
        'reward_title',
        'reward_description',
        'google_class_suffix',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'total_stamps' => 'integer',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(LoyaltyMilestone::class)->orderBy('stamp_count');
    }

    public function loyaltyCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class);
    }

    public function activeCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class)->where('is_completed', false);
    }

    public function completedCards(): HasMany
    {
        return $this->hasMany(LoyaltyCard::class)->where('is_completed', true);
    }

    public function googleClassSuffix(): string
    {
        return $this->google_class_suffix ?? 'loyalty-program-' . $this->id;
    }

    public function stampIconLabel(): string
    {
        return match ($this->stamp_icon) {
            'coffee' => '☕',
            'star'   => '⭐',
            'stamp'  => '🔵',
            'heart'  => '❤️',
            'fire'   => '🔥',
            'crown'  => '👑',
            'gem'    => '💎',
            'bolt'   => '⚡',
            default  => '●',
        };
    }

    public function fontPath(): string
    {
        $map = [
            'montserrat' => resource_path('fonts/Montserrat-Bold.ttf'),
            'opensans'   => resource_path('fonts/OpenSans-Bold.ttf'),
            'ubuntu'     => resource_path('fonts/Ubuntu-Bold.ttf'),
        ];

        $path = $map[$this->card_font] ?? resource_path('fonts/Roboto-Bold.ttf');

        return file_exists($path) ? $path : resource_path('fonts/Roboto-Bold.ttf');
    }

    /** @return array{stamp_count:int,reward_title:string}[] */
    public function milestoneCounts(): array
    {
        return $this->milestones->map(fn ($m) => $m->stamp_count)->toArray();
    }
}
