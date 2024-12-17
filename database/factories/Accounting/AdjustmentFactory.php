<?php

namespace Database\Factories\Accounting;

use App\Enums\Accounting\AdjustmentCategory;
use App\Enums\Accounting\AdjustmentComputation;
use App\Enums\Accounting\AdjustmentType;
use App\Models\Accounting\Account;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounting\Adjustment>
 */
class AdjustmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'type' => $this->faker->randomElement(AdjustmentType::cases()), // Example type for tax or discount
            'category' => $this->faker->randomElement(AdjustmentCategory::cases()), // Example category for tax or discount
            'rate' => $this->faker->randomFloat(2, 5, 20), // Example rate for tax or discount
            'computation' => $this->faker->randomElement(AdjustmentComputation::cases()),
            'company_id' => Company::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the adjustment is a discount.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function discount()
    {
        return $this->state([
            'category' => AdjustmentCategory::Discount->value, // Adjusting the type to 'Discount'
        ]);
    }

    /**
     * Indicate that the adjustment is a tax.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    public function tax()
    {
        return $this->state([
            'category' => AdjustmentCategory::Tax->value, // Adjusting the type to 'Tax'
        ]);
    }
}
