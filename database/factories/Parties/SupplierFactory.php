<?php

namespace Database\Factories\Parties;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Parties\Supplier>
 */
class SupplierFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_name' => $this->faker->name(),
            'supplier_email' => $this->faker->unique()->safeEmail(),
            'supplier_phone' => $this->faker->phoneNumber(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'address' => $this->faker->address(),
            'enabled' => $this->faker->boolean(),
            'currency_code' => $this->faker->randomElement(['USD']),
            'company_id' => Company::factory(), // Generates a related company
            'created_by' => User::factory(),    // Generates the user who created the customer
            'updated_by' => User::factory(),    //
        ];
    }
}
