<?php

namespace App\View\Models;

use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Utilities\Currency\CurrencyConverter;

class BillTotalViewModel
{
    public function __construct(
        public ?Bill $bill,
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
            $purchaseTaxes = $item['purchaseTaxes'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $taxAmount = Adjustment::whereIn('id', $purchaseTaxes)
                ->pluck('rate')
                ->sum(fn($rate) => $lineTotal * ($rate / 100));

            return $carry + $taxAmount;
        }, 0);

        $discountTotal = $lineItems->reduce(function ($carry, $item) {
            $quantity = max((float) ($item['quantity'] ?? 0), 0);
            $unitPrice = max((float) ($item['unit_price'] ?? 0), 0);
            $purchaseDiscounts = $item['purchaseDiscounts'] ?? [];
            $lineTotal = $quantity * $unitPrice;

            $discountAmount = Adjustment::whereIn('id', $purchaseDiscounts)
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
