<?php

use App\Enums\Accounting\AdjustmentComputation;
use App\Filament\Company\Resources\AdjustmentResource\Pages\CreateAdjustment;
use App\Filament\Company\Resources\AdjustmentResource\Pages\EditAdjustment;
use App\Filament\Company\Resources\AdjustmentResource\Pages\ListAdjustments;
use App\Models\Accounting\Account;
use App\Models\Accounting\Adjustment;
use App\Models\Company;
use App\Models\Product\Product;
use App\Models\User;
use Livewire\Livewire;

it('check empty form data to adjustment creation form', function () {

    $user = User::factory()->create();
    $this->actingAs($user);

    // Perform the test
    Livewire::test(CreateAdjustment::class)
        ->set('data.company_id', null)
        ->set('data.account_id', null)
        ->set('data.name', null)
        ->set('data.category', null)
        ->set('data.type', null)        // Simulate empty type
        ->set('data.status', null)      // Simulate empty status
        ->set('data.recoverable', null) // Simulate empty recoverable
        ->set('data.rate', null)        // Simulate empty rate
        ->set('data.computation', null) // Simulate empty computation
        ->set('data.start_date', null)  // Simulate empty start_date
        ->set('data.end_date', null)    // Simulate empty end_date

        ->call('create')
        ->assertHasErrors([ // Assert validation errors for required fields
            'data.company_id',
            'data.account_id',
            'data.name',
            'data.category',
            'data.type',
            'data.status',
            'data.recoverable',
            'data.rate',
            'data.computation',
            'data.start_date',
            'data.end_date',
        ]);
});

it('allows the user to create a new adjustments', function () {

    // Now, perform the Livewire test on the CreateAdjustment page with valid data
    $validData = [
        'company_id' => $this->testCompany->id, // Use the created tenant
        'account_id' => Account::factory()->create()->id, // Assuming there's a valid account_id in your system
        'name' => 'Test Adjustment',
        'category' => 'tax',
        'type' => 'sales',
        'status' => 'pending',
        'recoverable' => 'yes',
        'rate' => 100,
        'computation' => AdjustmentComputation::Fixed->value,
        'start_date' => now(),
        'end_date' => now()->addDays(30),
        'transaction_id' => '123456',
        'previous_quantity' => 10,
        'new_quantity' => 15,
        'previous_price' => 50,
        'new_price' => 60,
    ];

    // Perform the test
    Livewire::test(CreateAdjustment::class)
        ->set('data', $validData) // Set all form fields with valid data
        ->call('create') // Call the create method that submits the form
        ->assertHasNoErrors(); // Assert that there are no validation errors

    // Verify that the Adjustment has been created
    $this->assertDatabaseHas('adjustments', [
        'company_id' => $validData['company_id'],
        'name' => $validData['name'],
        'category' => $validData['category'],
        'rate' => $validData['rate'],
        'transaction_id' => $validData['transaction_id'],
    ]);

    // Optionally, check if the company_id is correct after creation
    $adjustment = Adjustment::first();
    $this->assertEquals($validData['company_id'], $adjustment->company_id);  // Ensure the company_id is correct
});

it('updates adjustments form data', function () {
    // Create a company, an account, and an adjustment to edit

    $account = Account::factory()->create();
    $adjustment = Adjustment::factory()->create([
        'company_id' => $this->testCompany->id,
        'account_id' => $account->id,
        'name' => Product::factory()->create()->product_name,
        'category' => 'tax',
        'type' => 'sales',
        'status' => 'pending',
        'recoverable' => true,
        'rate' => 50,
        'computation' => 'percentage',
        'transaction_id' => '654321',
    ]);

    // Data to update the adjustment
    $updatedData = [
        'company_id' => $this->testCompany->id,
        'account_id' => $account->id,
        'name' => Product::factory()->create()->product_name,
        'category' => 'discount',
        'type' => 'purchase',
        'status' => 'approved',
        'recoverable' => false,
        'rate' => 120,
        'computation' => 'fixed',
        'start_date' => now(),
        'end_date' => now()->addDays(30),
        'transaction_id' => '123456',
        'previous_quantity' => 10,
        'new_quantity' => 20,
        'previous_price' => 50,
        'new_price' => 70,
    ];

    // Step 2: Update the adjustments

    // Perform the test on the EditAdjustment page with updated data
    $response = Livewire::test(EditAdjustment::class, ['record' => $adjustment->id]) // Pass the existing adjustment to edit
        ->set('data', $updatedData) // Set all form fields with updated data
        ->call('save') // Call the update method to update the record
        ->assertHasNoErrors(); // Assert that there are no validation errors

    $response->assertStatus(200);

    // Verify that the Adjustment has been updated in the database
    $this->assertDatabaseHas('adjustments', [
        'id' => $adjustment->id, // Ensure we're updating the correct record
        'company_id' => $updatedData['company_id'],
        'name' => $updatedData['name'],
        'category' => $updatedData['category'],
        'rate' => $updatedData['rate'],
        'type' => $updatedData['type'],
        'status' => $updatedData['status'],
        'recoverable' => $updatedData['recoverable'],
        'rate' => $updatedData['rate'],
        'computation' => $updatedData['computation'],
    ]);

    $adjustment->refresh();

    $expectedRate = bcdiv($updatedData['rate'], '100', 2);
    expect($adjustment->company_id)->toBe($updatedData['company_id']);
    expect($adjustment->name)->toBe($updatedData['name']);
    // expect($adjustment->category)->toBe($updatedData['category']);
    expect($adjustment->category->value)->toBe($updatedData['category']);
    //expect((float) $adjustment->rate)->toBe((float) $updatedData['rate']);
    expect((float) str_replace(',', '', $adjustment->rate))->toBe((float) $expectedRate);
    expect($adjustment->transaction_id)->toBe('123456');
    expect($adjustment->start_date->toDateString())->toBe(now()->toDateString());

});

it('allows the user to bulk delete adjustments', function () {
    // Seed the database with test data
    $adjustments = Adjustment::factory()->count(3)->create([
        'company_id' => $this->testCompany->id,
        'computation' => AdjustmentComputation::Percentage->value,
    ]);

    // Verify the adjustments are in the database
    foreach ($adjustments as $adjustment) {

        $this->assertDatabaseHas('adjustments', [
            'id' => $adjustment->id,
        ]);
    }
    // Perform the bulk delete action using Filament's Livewire component
    Livewire::test(ListAdjustments::class)
        ->callTableBulkAction(
            'delete',
            $adjustments->pluck('id')->toArray()
        );

    // Verify the adjustments are deleted from the database
    foreach ($adjustments as $adjustment) {
        $this->assertDatabaseMissing('adjustments', [
            'id' => $adjustment->id,
        ]);
    }
});

it('allows the user to bulk approve adjustments', function () {
    // Seed the database with test data
    $adjustments = Adjustment::factory()->count(3)->create([
        'company_id' => $this->testCompany->id,
    ]);

    // Verify the adjustments are in the database
    foreach ($adjustments as $adjustment) {
        $this->assertDatabaseHas('adjustments', [
            'id' => $adjustment->id,
        ]);
    }

    // Perform the bulk approve action using Filament's Livewire component
    Livewire::test(ListAdjustments::class)
        ->callTableBulkAction(
            'approve',  // Change 'delete' to 'approve'
            $adjustments->pluck('id')->toArray()
        );

    // Verify the adjustments are approved in the database
    foreach ($adjustments as $adjustment) {
        // Assuming there's a column 'status' for approval
        $this->assertDatabaseHas('adjustments', [
            'id' => $adjustment->id,
            'status' => 'approved',  // Ensure the status is 'approved'
        ]);
    }
});
