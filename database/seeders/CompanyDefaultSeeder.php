<?php

namespace Database\Seeders;

use App\Models\Setting\CompanyDefault;
use App\Models\Setting\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CompanyDefaultSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example: Assigning a default currency to the first company
        $currency = Currency::first(); // Get the first currency or choose a specific one
        CompanyDefault::first()->update(['currency_code' => $currency->code]);
    }
}
