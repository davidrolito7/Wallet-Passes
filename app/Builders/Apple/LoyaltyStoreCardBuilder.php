<?php

namespace App\Builders\Apple;

use Spatie\LaravelMobilePass\Builders\Apple\Entities\Image;
use Spatie\LaravelMobilePass\Builders\Apple\StoreCardPassBuilder;

class LoyaltyStoreCardBuilder extends StoreCardPassBuilder
{
    public function setStripImage(string $x1Path, ?string $x2Path = null, ?string $x3Path = null): self
    {
        $this->images['strip'] = new Image($x1Path, $x2Path, $x3Path);

        return $this;
    }
}
