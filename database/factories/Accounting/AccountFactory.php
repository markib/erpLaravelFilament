<?php

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountSubtype;
use App\Models\Banking\BankAccount;
use App\Models\Setting\Currency;
use App\Utilities\Currency\CurrencyAccessor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1,
            'subtype_id' => 1,
            'name' => $this->faker->unique()->word,
            'currency_code' => CurrencyAccessor::getDefaultCurrency() ?? 'USD',
            'description' => $this->faker->sentence,
            'archived' => false,
            'default' => false,
        ];
    }

    public function withBankAccount(string $name): static
    {
        return $this->state(function (array $attributes) use ($name) {
            $bankAccount = BankAccount::factory()->create();
            $accountSubtype = AccountSubtype::where('name', 'Cash and Cash Equivalents')->first();

            return [
                'bank_account_id' => $bankAccount->id,
                'subtype_id' => $accountSubtype->id,
                'name' => $name,
            ];
        });
    }

    public function withForeignBankAccount(string $name, string $currencyCode, float $rate): static
    {
        return $this->state(function (array $attributes) use ($currencyCode, $rate, $name) {
            $currency = Currency::factory()->forCurrency($currencyCode, $rate)->create();
            $bankAccount = BankAccount::factory()->create();
            $accountSubtype = AccountSubtype::where('name', 'Cash and Cash Equivalents')->first();

            return [
                'bank_account_id' => $bankAccount->id,
                'subtype_id' => $accountSubtype->id,
                'name' => $name,
                'currency_code' => $currency->code,
            ];
        });
    }
}
