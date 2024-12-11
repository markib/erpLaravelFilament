<?php

namespace Database\Factories\Product;

use App\Models\Product\Categories;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Model>
 */
class CategoryFactory extends Factory
{
    protected $model = Categories::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_code' => strtoupper($this->faker->unique()->bothify('CAT###')), // Ensure uppercase for codes like CAT123
            'category_name' => ucfirst($this->faker->words(3, true)), // Capitalized category names e.g., "Home Appliances"
            'enabled' => $this->faker->boolean(90), // Favor "enabled" categories
        ];
    }
}
