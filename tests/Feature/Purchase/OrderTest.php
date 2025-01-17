<?php

use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\DocumentDiscountMethod;
use App\Enums\Accounting\OrderStatus;
use App\Enums\Common\ItemType;
use App\Filament\Company\Resources\Purchases\OrderResource\Pages\EditOrder;
use App\Filament\Company\Resources\Purchases\OrderResource\Pages\ListOrders;
use App\Models\Accounting\DocumentLineItem;
use App\Models\Accounting\Order;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\Parties\Supplier;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(
    function () {

        $this->vendor = Supplier::factory([
            'company_id' => $this->testCompany->id,
            'currency_code' => 'USD',
            'created_by' => $this->testUser->id,
            'updated_by' => $this->testUser->id,
        ])->count(2)->create();
        $this->order = Order::factory(['company_id' => $this->testCompany->id, 'vendor_id' => $this->vendor->first()->id])->withLineItems(2)->create();
    }
);

it('allows the user to create a new order', function () {

    // $this->order = Order::factory(['company_id' => $this->testCompany->id])->withLineItems(3)->create();
    $currency = $this->order->currency_code;
    // Assert Order Totals (Very Important)
    $expectedSubtotal = $this->order->lineItems->sum('subtotal'); // Calculate expected subtotal
    $expectedTaxTotal = $this->order->lineItems->sum('tax_total');
    $expectedDiscountTotal = $this->order->lineItems->sum('discount_total');
    $expectedTotal = $expectedSubtotal + $expectedTaxTotal - $expectedDiscountTotal;

    // Step 5: Refresh the $this->order and calculate totals
    $this->order->refresh();

    expect(money($this->order->subtotal, $currency)->getAmount())->toBe(money($expectedSubtotal, $currency)->getAmount());
    expect(money($this->order->tax_total, $currency)->getAmount())->toBe(money($expectedTaxTotal, $currency)->getAmount());
    expect(money($this->order->discount_total, $currency)->getAmount())->toBe(money($expectedDiscountTotal, $currency)->getAmount());
    expect(money($this->order->total, $currency)->getAmount())->toBe(money($expectedTotal, $currency)->getAmount());
});

it('allows the user to update an existing order forms', function () {

    // $offering = Offering::factory()->withPurchaseTaxes()->withPurchaseDiscounts()->count(2)->create(['company_id' => $this->testCompany->id, 'purchasable' => true]);

    $currency = $this->order->currency_code;
    // --- Prepare Update Data ---
    $updatedLineItem = $this->order->lineItems->first();

    $updatedQuantity = $updatedLineItem->quantity + 1;

    $updatedData = [
        'vendor_id' => $this->vendor->last()->id,
        'header' => 'Updated Order Header',
        'subheader' => 'Updated Order Subheader',
        'currency_code' => $currency,
        'date' => now()->toDateString(),
        'expiration_date' => now()->addDays(30)->toDateString(),
        'discount_method' => DocumentDiscountMethod::PerLineItem->value, // Ensure valid value
        'discount_computation' => AdjustmentComputation::Percentage->value, // Ensure valid value
        'item_type' => ItemType::offering->value,
        'lineItems' => [
            [
                'offering_id' => $this->order->lineItems->first()->offering_id,
                // 'product_id' => null,
                'description' => 'Updated Item 1',
                'quantity' => $updatedQuantity,
                'unit_price' => $this->order->lineItems->first()->unit_price,
                'purchaseTaxes' => $this->order->lineItems->first()->purchaseTaxes->pluck('id')->toArray(),
                'purchaseDiscounts' => $this->order->lineItems->first()->purchaseDiscounts->pluck('id')->toArray(),

            ],
            [
                'offering_id' => $this->order->lineItems->last()->offering_id,
                // 'product_id' => null,
                'description' => 'Updated Item 2',
                'quantity' => $updatedLineItem->quantity,
                'unit_price' => $this->order->lineItems->last()->unit_price,
                'purchaseTaxes' => $this->order->lineItems->last()->purchaseTaxes->pluck('id')->toArray(),
                'purchaseDiscounts' => $this->order->lineItems->last()->purchaseDiscounts->pluck('id')->toArray(),

            ],
        ],
        'terms' => 'Updated Terms',
        'footer' => 'Updated Footer',
    ];

    // dump($updatedData);
    $component = Livewire::test(EditOrder::class, ['record' => $this->order->id, 'company' => $this->testCompany->id]);

    $component->set('data.vendor_id', $updatedData['vendor_id']);

    $component->assertSet('data.currency_code', $updatedData['currency_code'])

        ->set('data.header', $updatedData['header'])
        ->set('data.subheader', $updatedData['subheader'])
        ->set('data.date', $updatedData['date'])
        ->set('data.expiration_date', $updatedData['expiration_date']);

    // // Act: Set item_type to 'offering'
    $component->set('data.item_type', $updatedData['item_type']);

    $component->set('data.discount_method', $updatedData['discount_method'])
        ->set('data.discount_computation', $updatedData['discount_computation']);

    $component->assertSet('data.discount_method', $updatedData['discount_method']);

    $component->set('data.lineItems', $updatedData['lineItems']);

    $component->set('data.terms', $updatedData['terms'])
        ->set('data.footer', $updatedData['footer']);

    // dump('Component data before save:', $component->get('data.lineItems.0.unit_price'));
    $component->call('save');
    $component->assertHasNoErrors();

    $this->order->refresh();

    // Recalculate expected totals *after* the updates
    $expectedSubtotal = $this->order->lineItems->sum('subtotal');
    $expectedTaxTotal = $this->order->lineItems->sum('tax_total');
    $expectedDiscountTotal = $this->order->lineItems->sum('discount_total');
    $expectedTotal = $expectedSubtotal + $expectedTaxTotal - $expectedDiscountTotal;

    expect($this->order->header)->toBe('Updated Order Header');
    expect(money($this->order->subtotal)->getAmount())->toBe(money($expectedSubtotal)->getAmount());
    expect(money($this->order->tax_total)->getAmount())->toBe(money($expectedTaxTotal)->getAmount());
    expect(money($this->order->discount_total)->getAmount())->toBe(money($expectedDiscountTotal)->getAmount());
    expect(money($this->order->total)->getAmount())->toBe(money($expectedTotal)->getAmount());
    expect($this->order->discount_method->value)->toBe(DocumentDiscountMethod::PerLineItem->value);
    expect($this->order->discount_computation->value)->toBe(AdjustmentComputation::Percentage->value);
});

it('allows the user to delete an existing order', function () {
    // Create an order to be deleted

    $orderId = $this->order->id;

    // Assert that the order exists before deletion
    expect(Order::find($orderId))->not->toBeNull();
    // Mount the Livewire component
    Livewire::test(ListOrders::class)
        ->callTableAction('delete', $this->order)
        ->assertSuccessful();

    // Refresh the database state
    $this->refreshDatabase();

    // Assert that the order no longer exists in the database
    expect(Order::find($orderId))->toBeNull();

    // Assert that related line items are also deleted
    expect(DocumentLineItem::where('documentable_id', $orderId)->count())->toBe(0);
});

it('allows the user to approve an existing order', function () {
    // Assume $this->order is already created and unapproved
    $orderId = $this->order->id;

    // Assert that the order exists and is not approved before the action
    expect(Order::find($orderId))->not->toBeNull();
    expect($this->order->status)->toBe(OrderStatus::Draft);

    // Mount the Livewire component and call the approve action
    // $component =Livewire::test(ListOrders::class)
    //     ->assertTableActionExists('approveDraft')
    //     ->callTableAction('approveDraft', $this->order)
    //     ->assertSuccessful()
    //     ->assertHasNoErrors();
    $this->order->expiration_date = now()->addDays(30);
    $this->order->save();

    $now = Carbon::now();
    $this->order->approveDraft($now);
    // Refresh the order from the database

    $this->order->refresh();

    // Assert that the order is now approved
    expect($this->order->status)->toBe(OrderStatus::Unsent);
});

it('allows the user to mark as sent for existing order', function () {
    // Assume $this->order is already created and unapproved

    $orderId = $this->order->id;

    // Assert that the order exists and is not approved before the action
    expect(Order::find($orderId))->not->toBeNull();

    // expect($this->order->status)->toBe(OrderStatus::Draft);

    // $this->order->approveDraft();
    // $this->order->refresh();

    // expect($this->order->status)->toBe(OrderStatus::Unsent);

    // Mount the Livewire component and call the approve action
    Livewire::test(ListOrders::class)
        ->callTableAction('markAsSent', $this->order)
        ->assertSuccessful();

    // Refresh the order from the database
    $this->order->refresh();

    // Assert that the order is now approved
    expect($this->order->status)->toBe(OrderStatus::Sent);

});
