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

        // 1. Create Permissions (The specific actions)
        Permission::create(['name' => 'view_vault']);       // See money/gold
        Permission::create(['name' => 'manage_vault']);     // Add funds/expenses
        Permission::create(['name' => 'settle_karigar']);   // Rate fixing
        Permission::create(['name' => 'create_order']);     // Take orders
        Permission::create(['name' => 'view_all_sales']);   // See total business

        // 2. Create Roles and Assign Permissions

        // ROLE: STAFF (Sales Person)
        $staffRole = Role::create(['name' => 'staff']);
        $staffRole->givePermissionTo([
            'create_order',
            // Staff can't see vault or settle karigars
        ]);

        // ROLE: ADMIN (Owner)
        $adminRole = Role::create(['name' => 'admin']);
        $adminRole->givePermissionTo(Permission::all()); // Admin can do everything
        $user = User::find(1);
        $user->assignRole('admin');
    }
}
