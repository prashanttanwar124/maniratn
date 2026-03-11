<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            'manage_users',
            'manage_roles_permissions',
            'view_dashboard',
            'manage_daily_rates',
            'view_vault',
            'manage_vault',
            'manage_products',
            'manage_categories',
            'manage_customers',
            'manage_suppliers',
            'create_order',
            'manage_orders',
            'settle_karigar',
            'manage_invoices',
            'manage_ledgers',
            'manage_expenses',
            'manage_mortgages',
            'view_all_sales',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        $roles = [
            'admin' => $permissions,
            'manager' => [
                'view_dashboard',
                'manage_daily_rates',
                'view_vault',
                'manage_products',
                'manage_categories',
                'manage_customers',
                'manage_suppliers',
                'create_order',
                'manage_orders',
                'manage_invoices',
                'manage_ledgers',
                'view_all_sales',
            ],
            'accountant' => [
                'view_dashboard',
                'view_vault',
                'manage_vault',
                'manage_ledgers',
                'manage_expenses',
                'manage_mortgages',
                'view_all_sales',
            ],
            'staff' => [
                'view_dashboard',
                'manage_customers',
                'create_order',
                'manage_orders',
                'manage_invoices',
            ],
            'basic' => [],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }

        $user = User::find(1);
        if ($user) {
            $user->syncRoles(['admin']);
        }
    }
}
