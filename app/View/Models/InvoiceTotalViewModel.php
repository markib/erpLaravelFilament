<?php

namespace App\View\Models;

use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Invoice;
use App\Utilities\Currency\CurrencyConverter;

class InvoiceTotalViewModel
{
    public function __construct(
        public ?Invoice $invoice,
        public ?array $data = null
    ) {}

    public function buildViewData(): array
    {
        $lineItems = collect($this->data['lineItems'] ?? []);

        $subtotal = $lineItems->sum(function ($item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);

            return $quantity * $unitPrice;
        });

        $taxTotal = $lineItems->reduce(function ($carry, $item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $salesTaxes = $item['salesTaxes'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $taxAmount = Adjustment::whereIn('id', $salesTaxes)
                ->pluck('rate')
                ->sum(fn($rate) => $lineTotal * ($rate / 100));

            return $carry + $taxAmount;
        }, 0);

        $discountTotal = $lineItems->reduce(function ($carry, $item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $salesDiscounts = $item['salesDiscounts'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $discountAmount = Adjustment::whereIn('id', $salesDiscounts)
                ->pluck('rate')
                ->sum(fn($rate) => $lineTotal * ($rate / 100));

            return $carry + $discountAmount;
        }, 0);

        $grandTotal = $subtotal + ($taxTotal - $discountTotal);

        return [
            'subtotal' => CurrencyConverter::formatToMoney($subtotal),
            'taxTotal' => CurrencyConverter::formatToMoney($taxTotal),
            'discountTotal' => CurrencyConverter::formatToMoney($discountTotal),
            'grandTotal' => CurrencyConverter::formatToMoney($grandTotal),
        ];
    }
}
