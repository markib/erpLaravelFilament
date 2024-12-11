<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasLabel;

enum AdjustmentComputation: string implements HasLabel
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function getLabel(): ?string
    {
        return translate($this->name);
    }
}
