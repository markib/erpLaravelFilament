<?php

use App\Enums\Accounting\AdjustmentCategory;
use App\Filament\Company\Resources\Sales\InvoiceResource;
use App\Filament\Company\Resources\Sales\InvoiceResource\Pages\CreateInvoice;
use App\Models\Accounting\Adjustment;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Invoice;
use App\Models\Common\Offering;
use App\Models\Parties\Customer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Pest\Livewire\LivewireTest;


use function Pest\Livewire\livewire;

it('allows the user to create a new sales invoice through the resource form', function () {

    $testCompany = $this->testCompany;
    // Fake storage for the file upload
    Storage::fake('public');

    // Step 1: Create a customer for the sales invoice.
    $customer = Customer::factory()->create();
    
    // Create Offering and related adjustments (salesTaxes and salesDiscounts)
    $offering = Offering::factory()->create(['sellable'=> true]);
    
    $salesTax = Adjustment::factory()->tax()->create(['rate' => 10]);
    
    $salesDiscount = Adjustment::factory()->discount()->create(['rate' => 5]);

    
    // Assign sales taxes and discounts to the offering
    $offering->salesTaxes()->attach($salesTax);
    $offering->salesDiscounts()->attach($salesDiscount);


    // Create an invoice with 3 line items (you can change the count if needed)
    $invoice = Invoice::factory()->withLineItems(3)->create();
    // dd($invoice);

   

    // Step 5: Refresh the invoice and calculate totals
    $invoice->refresh();

    // $subtotal = $invoice->lineItems->sum('subtotal')/100;
    $subtotal = bcdiv($invoice->lineItems()->sum('subtotal'), '100', 2);
    $taxTotal = bcdiv($invoice->lineItems->sum('tax_total'),'100',2);
    $discountTotal = bcdiv($invoice->lineItems->sum('discount_total'),'100',2);
    $grandTotal = $subtotal + $taxTotal - $discountTotal;

    $invoice->updateQuietly([
        'subtotal' => $subtotal,
        'tax_total' => $taxTotal,
        'discount_total' => $discountTotal,
        'total' => $grandTotal,
    ]);

    // Assertions to validate the test
    expect($invoice->lineItems->count())->toBe(3);
    expect((float)str_replace(',', '', $invoice->subtotal))->toBe((float)$subtotal);
    expect((float)str_replace(',', '', $invoice->tax_total))->toBe((float)$taxTotal);
    expect((float)str_replace(',', '', $invoice->discount_total))->toBe((float)$discountTotal);
    expect((float)str_replace(',', '', $invoice->total))->toBe((float)$grandTotal);
});