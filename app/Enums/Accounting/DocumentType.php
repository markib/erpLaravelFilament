<?php

namespace App\Enums\Accounting;

use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasIcon, HasLabel
{
    case Invoice = 'invoice';
    case Bill = 'bill';
    // TODO: Add estimate
    // case Estimate = 'estimate';

    public const DEFAULT = self::Invoice->value;

    public function getLabel(): ?string
    {
        return $this->name;
    }

    public function getIcon(): ?string
    {
        return match ($this->value) {
            self::Invoice->value => 'heroicon-o-document-duplicate',
            self::Bill->value => 'heroicon-o-clipboard-document-list',
        };
    }

    public function getTaxKey(): string
    {
        return match ($this) {
            self::Invoice => 'salesTaxes',
            self::Bill => 'purchaseTaxes',
        };
    }

    public function getDiscountKey(): string
    {
        return match ($this) {
            self::Invoice => 'salesDiscounts',
            self::Bill => 'purchaseDiscounts',
        };
    }
}
