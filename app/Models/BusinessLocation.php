<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessLocation extends Model
{
    protected $fillable = [
        'business_id',
        'name',
        'latitude',
        'longitude',
        'relevant_text',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latitude'  => 'decimal:7',
            'longitude' => 'decimal:7',
            'is_active' => 'boolean',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * Enlace de Google Maps generado a partir de las coordenadas guardadas. Se usa para
     * precargar el campo "Enlace de Google Maps" al editar, ya que no se guarda el enlace
     * original que el usuario pegó — solo las coordenadas ya resueltas.
     */
    public function mapsUrl(): ?string
    {
        if ($this->latitude === null || $this->longitude === null) {
            return null;
        }

        return "https://www.google.com/maps?q={$this->latitude},{$this->longitude}";
    }
}
