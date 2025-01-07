<?php

namespace App\Enums\Common;

use Filament\Support\Contracts\HasLabel;

enum ItemType: string implements HasLabel
{
    case offering = 'Offering';
    case inventory_product = 'inventory_product';

    public function getLabel(): string
    {
        return match ($this) {
            self::offering => 'Offering',
            self::inventory_product => 'Inventory Product',
        };
    }

    public function isOffering(): bool
    {
        return $this == self::inventory_product;
    }

    public function isInventoryProduct(): bool
    {
        return $this == self::inventory_product;
    }
}
