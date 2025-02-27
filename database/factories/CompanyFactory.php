<?php

namespace Database\Factories;

use App\Models\Accounting\Bill;
use App\Models\Accounting\Estimate;
use App\Models\Accounting\Invoice;
use App\Models\Accounting\Transaction;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\Parties\Customer;
use App\Models\Parties\Supplier;
use App\Models\Setting\CompanyProfile;
use App\Models\User;
use App\Services\CompanyDefaultService;
use Illuminate\Database\Eloquent\Factories\Factory;

class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->company(),
            'user_id' => User::factory(),
            'personal_company' => true,
        ];
    }

    public function withCompanyProfile(): self
    {
        return $this->afterCreating(function (Company $company) {
            CompanyProfile::factory()->forCompany($company)->withCountry('US')->create();
        });
    }

    /**
     * Set up default settings for the company after creation.
     */
    public function withCompanyDefaults(): self
    {
        return $this->afterCreating(function (Company $company) {
            $countryCode = $company->profile->country;
            $companyDefaultService = app(CompanyDefaultService::class);
            $companyDefaultService->createCompanyDefaults($company, $company->owner, 'USD', $countryCode, 'en');
        });
    }

    public function withTransactions(int $count = 2000): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $defaultBankAccount = $company->default->bankAccount;

            Transaction::factory()
                ->forCompanyAndBankAccount($company, $defaultBankAccount)
                ->count($count)
                ->create([
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withClients(int $count = 10): self
    {
        return $this->has(Customer::factory()->count($count)->withPrimaryContact()->withAddresses());
    }

    public function withVendors(int $count = 10): self
    {
        return $this->has(Supplier::factory()->count($count)->withContact()->withAddress());
    }

    public function withOfferings(int $count = 2): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            logger()->debug('Company ID: ' . $company->id);
            Offering::factory()
                ->count($count)
                ->sellable()
                ->withAdjustments()
                ->purchasable()
                // ->withPurchaseAdjustments()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withInvoices(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $draftCount = (int) floor($count * 0.2);
            $approvedCount = (int) floor($count * 0.2);
            $paidCount = (int) floor($count * 0.3);
            $partialCount = (int) floor($count * 0.1);
            $overpaidCount = (int) floor($count * 0.1);
            $overdueCount = $count - ($draftCount + $approvedCount + $paidCount + $partialCount + $overpaidCount);

            Invoice::factory()
                ->count($draftCount)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($approvedCount)
                ->approved()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($paidCount)
                ->paid()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($partialCount)
                ->partial()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($overpaidCount)
                ->overpaid()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Invoice::factory()
                ->count($overdueCount)
                ->overdue()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withEstimates(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $draftCount = (int) floor($count * 0.2);     // 20% drafts
            $approvedCount = (int) floor($count * 0.3);   // 30% approved
            $acceptedCount = (int) floor($count * 0.2);  // 20% accepted
            $declinedCount = (int) floor($count * 0.1);  // 10% declined
            $convertedCount = (int) floor($count * 0.1); // 10% converted to invoices
            $expiredCount = $count - ($draftCount + $approvedCount + $acceptedCount + $declinedCount + $convertedCount); // remaining 10%

            Estimate::factory()
                ->count($draftCount)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Estimate::factory()
                ->count($approvedCount)
                ->approved()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Estimate::factory()
                ->count($acceptedCount)
                ->accepted()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Estimate::factory()
                ->count($declinedCount)
                ->declined()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Estimate::factory()
                ->count($convertedCount)
                ->converted()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Estimate::factory()
                ->count($expiredCount)
                ->expired()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }

    public function withBills(int $count = 10): self
    {
        return $this->afterCreating(function (Company $company) use ($count) {
            $unpaidCount = (int) floor($count * 0.4);
            $paidCount = (int) floor($count * 0.3);
            $partialCount = (int) floor($count * 0.2);
            $overdueCount = $count - ($unpaidCount + $paidCount + $partialCount);

            Bill::factory()
                ->count($unpaidCount)
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Bill::factory()
                ->count($paidCount)
                ->paid()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Bill::factory()
                ->count($partialCount)
                ->partial()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);

            Bill::factory()
                ->count($overdueCount)
                ->overdue()
                ->create([
                    'company_id' => $company->id,
                    'created_by' => $company->user_id,
                    'updated_by' => $company->user_id,
                ]);
        });
    }
}
