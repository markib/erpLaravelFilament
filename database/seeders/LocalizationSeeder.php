<?php

namespace Database\Seeders;

use App\Models\Setting\Localization;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class LocalizationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
            Localization::create([
        'company_id' => 1,  // Adjust based on the actual company_id
        'language' => 'en',  // Default language
        'timezone' => 'UTC', // Default timezone
    ]);
    }
}
