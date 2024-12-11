<?php

namespace Database\Factories\Product;

use App\Models\Product\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => null,
            'product_name' => ucfirst($this->faker->words(3, true)), // e.g., "Wireless Headphones"
            'product_code' => strtoupper($this->faker->unique()->bothify('PRD###')),
            'product_barcode_symbology' => $this->faker->ean13(),
            'product_quantity' => $this->faker->numberBetween(1, 100),
            'product_cost' => $this->faker->randomFloat(2, 10, 500), // Cost between $10 and $500
            'product_price' => $this->faker->randomFloat(2, 20, 1000), // Price between $20 and $1000
            'product_unit' => $this->faker->randomElement(['piece', 'box', 'kg']),
            'product_stock_alert' => $this->faker->numberBetween(5, 20),
            'product_order_tax' => $this->faker->numberBetween(1, 15), // Percentage
            'product_tax_type' => $this->faker->randomElement([1, 2]), // 1: Inclusive, 2: Exclusive
            'product_note' => $this->faker->sentence(),
            'enabled' => $this->faker->boolean(80), // 80% chance of being enabled
        ];
    }
}
