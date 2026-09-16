<?php

namespace App\Builders\Apple;

use Spatie\LaravelMobilePass\Builders\Apple\Entities\Image;
use Spatie\LaravelMobilePass\Builders\Apple\StoreCardPassBuilder;
use Spatie\LaravelMobilePass\Enums\TextAlignmentType;

class LoyaltyStoreCardBuilder extends StoreCardPassBuilder
{
    public function setStripImage(string $x1Path, ?string $x2Path = null, ?string $x3Path = null): self
    {
        $this->images['strip'] = new Image($x1Path, $x2Path, $x3Path);

        return $this;
    }

    public function withBackFieldRight(string $key): self
    {
        if ($this->backFields?->has($key)) {
            $this->backFields[$key]->textAlignment = TextAlignmentType::Right;
        }

        return $this;
    }

    public function withBackFieldAttributedValue(string $key, string $attributedValue): self
    {
        if ($this->backFields?->has($key)) {
            $this->backFields[$key]->attributedValue = $attributedValue;
        }

        return $this;
    }

    /**
     * Vacía las ubicaciones ya hidratadas desde el pase existente. addLocation() siempre suma,
     * nunca reemplaza, así que sin esto no hay forma de refrescar el relevantText de una
     * ubicación ya guardada ni de quitar una que el negocio desactivó.
     */
    public function resetLocations(): self
    {
        $this->locations = [];

        return $this;
    }
}
