<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app()[\Spatie\Permission\PermissionRegistrar::class];
        $registrar->forgetCachedPermissions();

        $this->resetManagedRolesAndPermissions();

        $permissions = RolePermissionRegistry::permissions();

        foreach ($permissions as $permission) {
            Permission::create(['name' => $permission]);
        }

        foreach (RolePermissionRegistry::roles() as $roleName => $rolePermissions) {
            $role = Role::create(['name' => $roleName]);
            $role->syncPermissions($rolePermissions);
        }

        $user = User::find(1);
        if ($user) {
            $user->syncRoles(['admin']);
        }

        $registrar->forgetCachedPermissions();
    }

    protected function resetManagedRolesAndPermissions(): void
    {
        $tableNames = config('permission.table_names');

        DB::table($tableNames['model_has_permissions'])->delete();
        DB::table($tableNames['model_has_roles'])->delete();
        DB::table($tableNames['role_has_permissions'])->delete();
        DB::table($tableNames['permissions'])->delete();
        DB::table($tableNames['roles'])->delete();
    }
}
