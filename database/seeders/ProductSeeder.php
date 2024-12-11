<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Product\Categories;
use App\Models\Product\Product;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure you have some categories and companies seeded first
        $categories = Categories::all();
        $companies = Company::all();

        if ($categories->isEmpty() || $companies->isEmpty()) {
            $this->command->warn("Categories and Companies must be seeded before running ProductSeeder.");
            return;
        }

        // Generate 50 products
        foreach ($companies as $company) {
            Product::factory()
                ->count(50)
                ->state(function () use ($categories, $company) {
                    return [
                        'category_id' => $categories->random()->id,
                        'company_id' => $company->id,
                        'created_by' => User::inRandomOrder()->first()?->id, // Assuming a user creates products
                        'updated_by' => User::inRandomOrder()->first()?->id,
                    ];
                })
                ->create();
        }
    }
}
