<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'dashboard.view',
            'reports.view',
            'reports.generate',
            'settings.update',
            'account.read',
            'account.update',
            'products.create',
            'products.read',
            'products.update',
            'products.delete',
            'company.manage', // Example custom permission
            'employees.manage', // Example custom permission
            'company.create', // Example custom permission
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }
    }
}
