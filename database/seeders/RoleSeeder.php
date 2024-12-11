<?php

namespace Database\Seeders;


use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define permissions
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
        ];

        // Seed permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Define roles and assign permissions
        $roles = [
            'admin' => $permissions, // Admin gets all permissions
            'editor' => [
                'products.create',
                'products.read',
                'products.update',
                'reports.view',
            ],
            'viewer' => [
                'products.read',
                'dashboard.view',
                'reports.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web', 'company_id' => 1]);
            $role->syncPermissions($rolePermissions);
        }
    }
}
