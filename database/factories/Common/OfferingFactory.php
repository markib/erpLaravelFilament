<?php

namespace Database\Factories\Common;

use App\Enums\Accounting\AdjustmentType;
use App\Enums\Common\OfferingType;
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
            'price' => $this->faker->randomFloat(2, 10, 100),
            'sellable' => $this->faker->boolean(80), // 80% chance to be true
            'purchasable' => $this->faker->boolean(20),
            'company_id' => Company::factory(), // Generates a related company
            'created_by' => User::factory(),    // Generates the user who created the customer
            'updated_by' => User::factory(),    //
        ];
    }
}
