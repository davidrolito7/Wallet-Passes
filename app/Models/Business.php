<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class Business extends Authenticatable
{
    use SoftDeletes;

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
        'website',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'password'  => 'hashed',
        ];
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
