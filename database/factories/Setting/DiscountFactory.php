<?php

namespace Database\Factories\Setting;

use App\Enums\Setting\DiscountComputation;
use App\Enums\Setting\DiscountScope;
use App\Enums\Setting\DiscountType;
use App\Models\Setting\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = Discount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = $this->faker->dateTimeBetween('now', '+1 year');
        $endDate = $this->faker->dateTimeBetween($startDate, Carbon::parse($startDate)->addYear());

        $computation = $this->faker->randomElement(DiscountComputation::class);

        if ($computation === DiscountComputation::Fixed) {
            $rate = $this->faker->numberBetween(5, 100) * 100; // $5 - $100
        } else {
            $rate = $this->faker->numberBetween(3, 50) * 10000; // 3% - 50%
        }

        return [
            'name' => $this->faker->unique()->word,
            'description' => $this->faker->sentence,
            'rate' => $rate,
            'computation' => $computation,
            'type' => $this->faker->randomElement(DiscountType::class),
            'scope' => $this->faker->randomElement(DiscountScope::class),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'enabled' => false,
        ];
    }

    public function salesDiscount(): self
    {
        return $this->state([
            'name' => 'Summer Sale',
            'type' => DiscountType::Sales,
        ]);
    }

    public function purchaseDiscount(): self
    {
        return $this->state([
            'name' => 'Bulk Purchase',
            'type' => DiscountType::Purchase,
        ]);
    }
}
