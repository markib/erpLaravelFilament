<?php

namespace App\Collections\Accounting;

use App\Models\Accounting\Invoice;
use App\Utilities\Currency\CurrencyAccessor;
use App\Utilities\Currency\CurrencyConverter;
use Illuminate\Database\Eloquent\Collection;

class InvoiceCollection extends Collection
{
    public function sumMoneyInCents(string $column): int
    {
        return $this->reduce(static function ($carry, Invoice $invoice) use ($column) {
            return $carry + $invoice->getRawOriginal($column);
        }, 0);
    }

    public function sumMoneyFormattedSimple(string $column, ?string $currency = null): string
    {
        $currency ??= CurrencyAccessor::getDefaultCurrency();

        $totalCents = $this->sumMoneyInCents($column);

        return CurrencyConverter::convertCentsToFormatSimple($totalCents, $currency);
    }

    public function sumMoneyFormatted(string $column, ?string $currency = null): string
    {
        $currency ??= CurrencyAccessor::getDefaultCurrency();

        $totalCents = $this->sumMoneyInCents($column);

        return CurrencyConverter::formatCentsToMoney($totalCents, $currency);
    }
}
