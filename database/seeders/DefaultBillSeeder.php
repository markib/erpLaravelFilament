<?php

namespace Database\Seeders;

use App\Models\Setting\DocumentDefault;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DefaultBillSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DocumentDefault::factory(1)
            ->create(['type' => 'bill']);

    }
}