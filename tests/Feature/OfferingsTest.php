<?php

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Common\OfferingType;
use App\Filament\Company\Resources\Common\OfferingResource\Pages\CreateOffering;
use App\Models\Accounting\Account;
use App\Models\Accounting\Adjustment;
use App\Models\Common\Offering;
use App\Models\Product\Product;
use Livewire\Livewire;

it('check empty form data to offering creation form', function () {

    $this->actingAs($this->testUser);

    // Perform the test
    $component = Livewire::test(CreateOffering::class)
        ->set('data', [
            'name' => '',
            'price' => '',
            'type' => OfferingType::Product->value,
        ])

        ->call('create')
        ->assertHasErrors([ // Assert validation errors for required fields
            'data.name' => 'required',
            'data.price' => 'required',

        ]);

    // Directly check the value of the 'type' property
    $this->assertEquals(OfferingType::Product->value, $component->get('data')['type']); // Assert the 'type' property value
    // ->assertSet('type', OfferingType::Product->value);
});

it('allows the user to create a new  sales offering', function () {

    $account = Account::factory()->create([
        'category' => AccountCategory::Revenue,
        'type' => AccountType::OperatingRevenue,
    ]);
    $product = Product::factory()->create();
    // Create sales tax adjustments
    $salesTaxes = Adjustment::factory()->count(2)->create([
        'category' => AdjustmentCategory::Tax,
        'type' => AdjustmentType::Sales,
        'status' => 'approved',
        //  'company_id' => $this->testCompany->id, // Use the created tenant
        //  'account_id' => $account->id, // Assuming there's a valid account_id in your system
        'name' => 'Test Adjustment',
        'recoverable' => 'yes',
        'rate' => 100,
        'computation' => 'fixed',
        'start_date' => now(),
        'end_date' => now()->addDays(30),
        'transaction_id' => '123456',
        'previous_quantity' => 10,
        'new_quantity' => 15,
        'previous_price' => 50,
        'new_price' => 60,
    ]);

    $validData = [
        'type' => OfferingType::Product->value,
        'name' => $product->product_name,
        'price' => $product->formatted_price,
        'description' => $product->product_note,
        'attributes' => ['Sellable'],
        'income_account_id' => $account->id,
        'expense_account_id' => null,
    ];

    // dump('Sales Tax IDs being sent:', $validData['sales_tax_ids']);
    // Perform the Livewire test
    $component = Livewire::test(CreateOffering::class)
        ->set('data', $validData)
        ->call('create')
        ->assertHasNoErrors();

    // dump($component->get('data')['sale_information']['income_account_id']);

    // Verify the database has the offering
    $this->assertDatabaseHas('offerings', [
        'type' => $validData['type'],
        'name' => $validData['name'],
        'sellable' => true,
        'purchasable' => false,
        'price' => $validData['price'],
        'description' => $validData['description'],
        'income_account_id' => $validData['income_account_id'],
        'expense_account_id' => null,
    ]);

    // Verify the sales tax adjustments were attached
    $offering = Offering::where('name', $validData['name'])->first();

    // dump('Offering sales_tax_ids:', $offering->salesTaxes);
    $this->assertCount(2, $offering->salesTaxes);

    foreach ($salesTaxes as $tax) {
        $this->assertDatabaseHas('adjustmentables', [
            'adjustmentable_type' => Offering::class,
            'adjustmentable_id' => $offering->id,
            'adjustment_id' => $tax->id,
        ]);
    }
});
