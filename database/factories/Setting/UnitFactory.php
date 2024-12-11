<?php

namespace Database\Factories\Setting;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Model\Setting\Unit>
 */
class UnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => 1, // Assumes a `CompanyFactory` exists
            'name' => $this->faker->randomElement(['piece', 'box', 'kg']),
            'short_name' => $this->faker->lexify('???'),
            'operator' => $this->faker->randomElement(['+', '-', '*', '/']),
            'operation_value' => $this->faker->numberBetween(1, 100),
            'enabled' => $this->faker->boolean,
            'created_by' => User::factory(), // Assumes a `UserFactory` exists
            'updated_by' => User::factory(), // Assumes a `UserFactory` exists
        ];
    }
}
