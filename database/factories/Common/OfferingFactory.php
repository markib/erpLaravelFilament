<?php

namespace Database\Factories\Common;

use App\Enums\Accounting\AccountCategory;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\AdjustmentType;
use App\Enums\Common\OfferingType;
use App\Models\Accounting\Account;
use App\Models\Accounting\Adjustment;
use App\Models\Common\Offering;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Common\Offering>
 */
class OfferingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->word,  // Ensure this field is being populated
            'description' => $this->faker->sentence,
            'type' => $this->faker->randomElement(OfferingType::cases()),
            'price' => $this->faker->randomFloat(2, 1, 999.99),
            'sellable' => $this->faker->boolean(80), // 80% chance to be true
            'purchasable' => $this->faker->boolean(20),
            'company_id' => 1, // Generates a related company
            'income_account_id' => function () {
                return Account::query()
                    ->where('category', AccountCategory::Revenue)
                    ->where('type', AccountType::OperatingRevenue)
                    ->inRandomOrder()
                    ->first()
                ->id;
            },
            'expense_account_id' => function () {
                return Account::query()
                    ->where('category', AccountCategory::Expense)
                    ->where('type', AccountType::OperatingExpense)
                    ->inRandomOrder()
                    ->first()
                ->id;
            },
            'created_by' => User::factory(),    // Generates the user who created the customer
            'updated_by' => User::factory(),    //
        ];
    }

    public function withPurchaseTaxes()
    {
        return $this->afterCreating(function (Offering $offering) {
            // Create purchase taxes
            $purchaseTaxes = Adjustment::factory()
                ->forCompany($offering->company_id)
                ->tax()
                ->count(2) // Adjust the count as needed
                ->create([
                    'type' => AdjustmentType::Purchase->value,
                    'rate' => $this->faker->randomFloat(2, 0, 0.2),
                    'status' => 'approved',
                ]);

            // Attach purchase taxes and discounts to the offering
            $offering->purchaseTaxes()->attach($purchaseTaxes->pluck('id')->toArray());
        });
    }

    public function withPurchaseDiscounts()
    {
        return $this->afterCreating(function (Offering $offering) {
            // Create purchase taxes
            $purchaseDiscounts = Adjustment::factory()
                ->forCompany($offering->company_id)
                ->discount()
                ->count(2) // Adjust the count as needed
                ->create([
                    'type' => AdjustmentType::Purchase->value,
                    'rate' => $this->faker->randomFloat(2, 0, 0.2),
                    'status' => 'approved',
                ]);

            // Attach purchase taxes and discounts to the offering
            $offering->purchaseDiscounts()->attach($purchaseDiscounts->pluck('id')->toArray());
        });
    }

    public function sellable(): self
    {
        return $this->state([
            'sellable' => true,
        ]);
    }

    public function purchasable(): self
    {
        return $this->state([
            'purchasable' => true,
        ]);
    }
}
