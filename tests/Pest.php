<?php

use App\Models\Company;
use App\Models\User;
use App\Testing\TestsReport;
use Database\Seeders\TestDatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;

uses(Tests\TestCase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

// expect()->extend('toBeOne', function () {
//     return $this->toBe(1);
// });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

// uses(RefreshDatabase::class); // Applying RefreshDatabase globally

beforeEach(function () {
    // Set up the testing environment

    // Optionally run the seeders
    $this->seed(TestDatabaseSeeder::class);

    // Mix in custom functionality, like the TestsReport
    Testable::mixin(new TestsReport);

    // Set up the user and company
    $this->testUser = User::first();

    // Assume the user has at least one owned company
    $this->testCompany = $this->testUser->ownedCompanies->first();

    // Switch the company for the test user
    $this->testUser->switchCompany($this->testCompany);

    // Set the user for the session
    $this->actingAs($this->testUser);

    // Set the tenant (company) for Filament
    Filament::setTenant($this->testCompany);
});

// This makes use of the existing functionality and your setup before each test
it('checks the user and company setup', function () {
    expect($this->testUser)->not->toBeNull();
    expect($this->testCompany)->not->toBeNull();
});
