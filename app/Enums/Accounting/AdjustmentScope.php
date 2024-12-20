<?php

namespace App\Enums\Accounting;

enum AdjustmentScope: string
{
    case Product = 'product';
    case Service = 'service';
    case Global = 'global';
    case Local = 'local';

    public function getLabel(): ?string
    {
        return translate($this->name);
    }
}
