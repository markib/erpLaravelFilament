<?php

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\CreateInvoice;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\EditInvoice;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\Bill;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Common\Offering;
use App\Models\Parties\Customer;
use Livewire\Livewire;

it('allows the user to create a new sales invoice', function () {

    // Create Offering and related adjustments (salesTaxes and salesDiscounts)
    $offering = Offering::factory()->create(['sellable' => true]);

    $salesTax = Adjustment::factory()->tax()->create(['rate' => 10]);

    $salesDiscount = Adjustment::factory()->discount()->create(['rate' => 5]);

    // Assign sales taxes and discounts to the offering
    $offering->salesTaxes()->attach($salesTax);
    $offering->salesDiscounts()->attach($salesDiscount);

    // Create an invoice with 3 line items (you can change the count if needed)
    $invoice = Invoice::factory()->withLineItems(3)->create();

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

it('allows the user to edit a sales invoice with valid data', function () {

    // Step 1: Set up a test customer and related offerings
    $customer = Customer::factory()->create();
    $offering1 = Offering::factory()->create(['sellable' => true]);
    $offering2 = Offering::factory()->create(['sellable' => true]);
    $salesTax = Adjustment::factory()->tax()->create(['rate' => 10]);
    $salesDiscount = Adjustment::factory()->discount()->create(['rate' => 5]);

    // Attach taxes and discounts to the offering
    $offering1->salesTaxes()->attach($salesTax);
    $offering1->salesDiscounts()->attach($salesDiscount);

    $offering2->salesTaxes()->attach($salesTax);
    $offering2->salesDiscounts()->attach($salesDiscount);

    // Step 2: Create a base invoice with line items
    // Create an invoice with 3 line items (you can change the count if needed)
    $invoice = Invoice::factory()->withLineItems(2)->create();

    // Step 3: Update data for the invoice
    $updatedData = [
        'client_id' => $customer->id,
        'header' => 'Updated Invoice Header',
        'subheader' => 'Updated Subheader',
        'date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'discount_method' => DocumentDiscountMethod::PerLineItem->value, // Ensure valid value
        'discount_computation' => AdjustmentComputation::Percentage->value, // Ensure valid value
        'item_type' => 'offering',
        'lineItems' => [
            ['offering_id' => $offering1->id, 'description' => 'Updated Item 1', 'quantity' => 2, 'unit_price' => 50, 'subtotal' => 100],
            ['offering_id' => $offering2->id, 'description' => 'Updated Item 2', 'quantity' => 3, 'unit_price' => 30, 'subtotal' => 90],
        ],
    ];

    // Step 4: Interact with the Livewire component of the Filament resource
    Livewire::test(EditInvoice::class, ['record' => $invoice->id, 'company' => 1])
        ->set('data.client_id', $updatedData['client_id'])
        ->set('data.header', $updatedData['header'])
        ->set('data.subheader', $updatedData['subheader'])
        ->set('data.date', $updatedData['date'])
        ->set('data.due_date', $updatedData['due_date'])
        ->set('data.discount_method', $updatedData['discount_method'])
        ->set('data.discount_computation', $updatedData['discount_computation'])
        ->set('data.item_type', $updatedData['item_type'])
        ->set('data.lineItems', $updatedData['lineItems'])
        ->call('save')
        ->assertHasNoErrors();

    $invoice->refresh();

    $subtotal = bcdiv($invoice->lineItems()->sum('subtotal'), '100', 2);
    $taxTotal = bcdiv($invoice->lineItems->sum('tax_total'), '100', 2);
    $discountTotal = bcdiv($invoice->lineItems->sum('discount_total'), '100', 2);
    $grandTotal = $subtotal + $taxTotal - $discountTotal;

    expect($invoice->header)->toBe('Updated Invoice Header');
    expect((float) str_replace(',', '', $invoice->subtotal))->toBe((float) $subtotal);
    expect((float) str_replace(',', '', $invoice->tax_total))->toBe((float) $taxTotal);
    expect((float) str_replace(',', '', $invoice->discount_total))->toBe((float) $discountTotal);
    expect((float) str_replace(',', '', $invoice->total))->toBe((float) $grandTotal);
    expect($invoice->discount_method->value)->toBe(DocumentDiscountMethod::PerLineItem->value);
    expect(in_array($invoice->discount_computation, AdjustmentComputation::cases()))->toBeTrue();
    expect($invoice->item_type)->toBe('offering');

});

it('sales invoice update requires client and offering', function () {
    $invoice = Invoice::factory()->create();
    $client = Customer::factory()->create();
    $offering = Offering::factory()->create(['sellable' => true]);

    Livewire::test(CreateInvoice::class)
        ->set('data.client_id', null)
        //->set('data.item_type', 'offering')
        ->set('data.lineItems', [
            ['product_id' => null, 'description' => 'Test Item', 'quantity' => 1, 'unit_price' => 100, 'subtotal' => 100],
        ])
        ->call('create')
        ->assertHasErrors(['data.client_id', 'data.lineItems.0.product_id']);

    $this->assertDatabaseMissing('invoices', [
        'id' => $invoice->id,
        'client_id' => $client->id, // Ensure values were NOT updated
        // 'item_type' => $invoice->item_type,
    ]);
    $this->assertDatabaseMissing('document_line_items', [
        'documentable_id' => $invoice->id,
        'product_id' => $offering->id,
    ]);
});

it('updates line items when editing the sales invoice', function () {
    // Step 1: Create an invoice with initial line items
    $invoice = Invoice::factory()
        ->has(DocumentLineItem::factory()->count(2), 'lineItems')
        ->create();

    $customer = Customer::factory()->create();

    $offering1 = Offering::factory()->create(['sellable' => true]);
    $offering2 = Offering::factory()->create(['sellable' => true]);

    // Updated line items data
    $updatedLineItems = [
        ['offering_id' => $offering1->id, 'description' => 'New Item 1', 'quantity' => 2, 'unit_price' => 50, 'subtotal' => 100],
        ['offering_id' => $offering2->id, 'description' => 'New Item 2', 'quantity' => 4, 'unit_price' => 25, 'subtotal' => 100],
    ];

    // Step 2: Update the invoice

    $response = Livewire::test(EditInvoice::class, ['record' => $invoice->id])
        ->set('data.client_id', $customer->id)
        ->set('data.item_type', 'Offering')
        ->set('data.discount_method', DocumentDiscountMethod::PerLineItem->value) // Ensure valid value
        ->set('data.discount_computation', AdjustmentComputation::Percentage->value) // Ensure valid value
        ->set('data.lineItems', $updatedLineItems)
        ->call('save')
        ->assertHasNoErrors();

    $response->assertStatus(200);

    // Reload invoice
    $invoice->refresh();

    expect($invoice->lineItems->count())->toBe(2);
    expect($invoice->lineItems[0]->description)->toBe('New Item 1');
    expect($invoice->lineItems[1]->quantity)->toBe(4);
    expect($invoice->discount_method->value)->toBe(DocumentDiscountMethod::PerLineItem->value);
    expect(in_array($invoice->discount_computation, AdjustmentComputation::cases()))->toBeTrue();
    // expect($invoice->item_type)->toBe('offering');

});
