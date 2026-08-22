<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Business extends Authenticatable
{
    use SoftDeletes;

    protected static function boot(): void
    {
        parent::boot();

        // Cascade soft-delete to programs (which in turn cascade to cards).
        static::deleting(function (Business $business) {
            $business->loyaltyPrograms()->each(fn ($program) => $program->delete());
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'logo_url',
        'primary_color',
        'secondary_color',
        'label_color',
        'login_email',
        'password',
        'contact_phone',
        'instagram_url',
        'website',
        'is_active',
        'latitude',
        'longitude',
        'location_enabled',
        'location_relevant_text',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active'        => 'boolean',
            'password'         => 'hashed',
            'latitude'         => 'decimal:7',
            'longitude'        => 'decimal:7',
            'location_enabled' => 'boolean',
        ];
    }

    public function hasLocation(): bool
    {
        return $this->location_enabled && $this->latitude !== null && $this->longitude !== null;
    }

    public function logoPublicUrl(): ?string
    {
        if (! $this->logo_url) {
            return null;
        }

        if (str_starts_with($this->logo_url, 'http')) {
            return $this->logo_url;
        }

        return \Storage::disk('public')->url($this->logo_url);
    }

    public function loyaltyPrograms(): HasMany
    {
        return $this->hasMany(LoyaltyProgram::class);
    }

    public function activePrograms(): HasMany
    {
        return $this->hasMany(LoyaltyProgram::class)->where('is_active', true);
    }
}
