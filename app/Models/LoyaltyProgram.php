<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class LoyaltyProgram extends Model
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        // Cascade soft-delete to cards when the program is deleted via Eloquent.
        // (DB-level cascadeOnDelete only fires on hard DELETE, not on Eloquent soft-delete.)
        static::deleting(function (LoyaltyProgram $program) {
            $program->loyaltyCards()->each(fn ($card) => $card->delete());
        });
    }

    protected $fillable = [
        'business_id',
        'name',
        'description',
        'total_stamps',
        'validity_months',
        'stamp_icon',
        'stamp_icon_url',
        'card_font',
        'stamp_style',
        'filled_stamp_image',
        'empty_stamp_image',
        'reward_badge_image',
        'pass_background_image',
        'stamp_scale',
        'stamp_spacing',
        'reward_title',
        'reward_description',
        'google_class_suffix',
        'is_active',
        'visit_notification_enabled',
        'visit_notification_title',
        'visit_notification_message',
        'google_wallet_notification_mode',
        'birthday_reward_enabled',
        'birthday_reward_title',
        'birthday_reward_description',
    ];

    protected function casts(): array
    {
        return [
            'is_active'                   => 'boolean',
            'total_stamps'                => 'integer',
            'validity_months'             => 'integer',
            'visit_notification_enabled'  => 'boolean',
            'birthday_reward_enabled'     => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function prizeSystems(): HasMany
    {
        return $this->hasMany(LoyaltyPrizeSystem::class)->orderBy('sort_order');
    }

    public function milestones(): HasManyThrough
    {
        return $this->hasManyThrough(
            LoyaltyMilestone::class,
            LoyaltyPrizeSystem::class,
            'loyalty_program_id',
            'loyalty_prize_system_id',
        )->orderBy('loyalty_milestones.stamp_count');
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

    public function backgroundImagePath(): ?string
    {
        if (! $this->pass_background_image) {
            return null;
        }

        return \Storage::disk('public')->path($this->pass_background_image);
    }

    public function backgroundImageUrl(): ?string
    {
        if (! $this->pass_background_image) {
            return null;
        }

        return \Storage::disk('public')->url($this->pass_background_image);
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
