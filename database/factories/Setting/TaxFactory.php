<?php

namespace Database\Factories\Setting;

use App\Enums\Setting\TaxComputation;
use App\Enums\Setting\TaxScope;
use App\Enums\Setting\TaxType;
use App\Models\Setting\Tax;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tax>
 */
class TaxFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Tax::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $computation = $this->faker->randomElement(TaxComputation::class);

        if ($computation === TaxComputation::Fixed) {
            $rate = $this->faker->biasedNumberBetween(1, 10) * 100; // $1 - $10
        } else {
            $rate = $this->faker->biasedNumberBetween(3, 25) * 10000; // 3% - 25%
        }

        return [
            'name' => $this->faker->unique()->word,
            'description' => $this->faker->sentence,
            'rate' => $rate,
            'computation' => $computation,
            'type' => $this->faker->randomElement(TaxType::class),
            'scope' => $this->faker->randomElement(TaxScope::class),
            'enabled' => false,
        ];
    }

    public function salesTax(): self
    {
        return $this->state([
            'name' => 'State Sales Tax',
            'type' => TaxType::Sales,
        ]);
    }

    public function purchaseTax(): self
    {
        return $this->state([
            'name' => 'State Purchase Tax',
            'type' => TaxType::Purchase,
        ]);
    }
}
