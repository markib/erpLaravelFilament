<?php

namespace Tests;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\JournalEntry;
use App\Models\Accounting\Order;
use App\Models\Banking\BankAccount;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\Product\Categories;
use App\Models\Product\Product;
use App\Models\User;
use App\Testing\TestsReport;
use Database\Seeders\TestDatabaseSeeder;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Livewire\Features\SupportTesting\Testable;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * Indicates whether the default seeder should run before each test.
     */
    protected bool $seed = true;

    /**
     * Run a specific seeder before each test.
     */
    protected string $seeder = TestDatabaseSeeder::class;

    protected User $testUser;

    // protected Categories $testCategory;

    protected ?Company $testCompany;

    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap([
            'offering' => Offering::class,
            'invoice' => Invoice::class,
            'bill' => Bill::class,
            'bankAccount' => BankAccount::class,
            'journal_entry' => JournalEntry::class,
            'product' => Product::class,
            'estimate' => Estimate::class,
            'order' => Order::class,
        ]);

        Testable::mixin(new TestsReport);

        $this->testUser = User::first();

        // $this->testCategory = Categories::factory()->create();

        $this->testCompany = $this->testUser->ownedCompanies->first();

        $this->testUser->switchCompany($this->testCompany);

        $this->actingAs($this->testUser);

        Filament::setTenant($this->testCompany);
    }
}
