<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TestDatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        
        $user =  User::factory()
            ->withPersonalCompany()
            ->create([
                'name' => 'Test Company Owner',
                'email' => 'test@gmail.com',
                'password' => bcrypt('password'),
                'current_company_id' => 1,
            ]);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'company_id' => $user->current_company_id]);
        // Assign the roles to the user
        $user->assignRole($adminRole); // Assign admin role or userRole as needed

        $this->call(CategorySeeder::class);
    }
}
