<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example: Assign roles to specific users
        $adminRole = Role::where('name', 'admin')->first();   // Assuming you have a role named 'admin'
        $editorRole = Role::where('name', 'editor')->first(); // Assuming you have a role named 'editor'

        // Fetch users
        $user1 = User::find(1);  // Find a user by ID
        $user2 = User::find(2);  // Find another user by ID

        // Define the company_id
        $companyId = 1; // Replace with the actual company ID

        // Attach roles to users with company_id
        $user1->roles()->attach($adminRole->id, ['company_id' => $companyId]);   // Assign 'admin' role to user 1
        $user1->roles()->attach($editorRole->id, ['company_id' => $companyId]);  // Assign 'editor' role to user 1

        $user2->roles()->attach($editorRole->id, ['company_id' => $companyId]);  // Assign 'editor' role to user 2
    }
}
