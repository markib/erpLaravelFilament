<?php

namespace Database\Seeders;

use App\Models\Product\Categories;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultCompanyId = 1; // Replace with a valid company ID in your database.
        Categories::factory()->count(10)->create([
            'company_id' => $defaultCompanyId,
        ]);
    }
}
