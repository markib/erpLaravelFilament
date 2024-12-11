<?php

namespace App\Enums\Setting;

use Filament\Support\Contracts\HasLabel;

enum TaxComputation: string implements HasLabel
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';
    case Compound = 'compound';

    public const DEFAULT = self::Percentage->value;

    public function getLabel(): ?string
    {
        return translate($this->name);
    }
}
