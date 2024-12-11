<?php

namespace App\Enums\Setting;

use Filament\Support\Contracts\HasLabel;

enum DiscountScope: string implements HasLabel
{
    case Product = 'product';
    case Service = 'service';

    public function getLabel(): ?string
    {
        return translate($this->name);
    }
}
