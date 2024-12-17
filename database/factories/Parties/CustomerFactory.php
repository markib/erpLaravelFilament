<?php

namespace Database\Factories\Parties;

use App\Models\Company;
use App\Models\Parties\Customer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Parties\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_name' => $this->faker->name(),
            'customer_email' => $this->faker->unique()->safeEmail(),
            'customer_phone' => $this->faker->phoneNumber(),
            'city' => $this->faker->city(),
            'country' => $this->faker->country(),
            'address' => $this->faker->address(),
            'enabled' => $this->faker->boolean(),
            'company_id' => Company::factory(), // Generates a related company
            'created_by' => User::factory(),    // Generates the user who created the customer
            'updated_by' => User::factory(),    //
        ];
    }
}
