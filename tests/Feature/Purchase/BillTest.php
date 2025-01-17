<?php

use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Models\Common\Offering;

it('allows the user to create a new purchases bill', function () {

    // Create Offering and related adjustments (salesTaxes and salesDiscounts)
    $offering = Offering::factory()->create(['purchasable' => true]);

    $salesTax = Adjustment::factory()->tax()->create(['rate' => 10]);

    $salesDiscount = Adjustment::factory()->discount()->create(['rate' => 5]);

    // Assign sales taxes and discounts to the offering
    $offering->salesTaxes()->attach($salesTax);
    $offering->salesDiscounts()->attach($salesDiscount);

    // Create an invoice with 3 line items (you can change the count if needed)
    $invoice = Bill::factory()->withLineItems(3)->create();

    // Step 5: Refresh the invoice and calculate totals
    $invoice->refresh();

    // $subtotal = $invoice->lineItems->sum('subtotal')/100;
    $subtotal = bcdiv($invoice->lineItems()->sum('subtotal'), '100', 2);
    $taxTotal = bcdiv($invoice->lineItems->sum('tax_total'), '100', 2);
    $discountTotal = bcdiv($invoice->lineItems->sum('discount_total'), '100', 2);
    $grandTotal = $subtotal + $taxTotal - $discountTotal;

    $invoice->updateQuietly([
        'subtotal' => $subtotal,
        'tax_total' => $taxTotal,
        'discount_total' => $discountTotal,
        'total' => $grandTotal,
    ]);

    // Assertions to validate the test
    expect($invoice->lineItems->count())->toBe(3);
    expect((float) str_replace(',', '', $invoice->subtotal))->toBe((float) $subtotal);
    expect((float) str_replace(',', '', $invoice->tax_total))->toBe((float) $taxTotal);
    expect((float) str_replace(',', '', $invoice->discount_total))->toBe((float) $discountTotal);
    expect((float) str_replace(',', '', $invoice->total))->toBe((float) $grandTotal);
});
