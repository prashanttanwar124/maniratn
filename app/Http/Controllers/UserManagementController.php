<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UserManagementController extends Controller
{
    public function index()
    {
        $users = User::query()
            ->with(['roles', 'permissions'])
            ->latest()
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'created_at' => optional($user->created_at)?->format('Y-m-d H:i'),
                    'roles' => $user->getRoleNames()->values(),
                    'permissions' => $user->getDirectPermissions()->pluck('name')->values(),
                    'attendance_enabled' => (bool) $user->attendance_enabled,
                    'has_attendance_passcode' => ! empty($user->attendance_passcode),
                ];
            });

        $roles = Role::query()
            ->with(['permissions', 'users'])
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) {
                return [
                    'id' => $role->id,
                    'name' => $role->name,
                    'label' => str($role->name)->replace('_', ' ')->title()->toString(),
                    'permissions' => $role->permissions->pluck('name')->values(),
                    'users_count' => $role->users->count(),
                ];
            });

        $permissions = Permission::query()
            ->withCount(['roles', 'users'])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Permission $permission) => [
                'id' => $permission->id,
                'label' => str($permission->name)->replace('_', ' ')->title()->toString(),
                'value' => $permission->name,
                'roles_count' => $permission->roles_count,
                'users_count' => $permission->users_count,
            ]);

        return Inertia::render('users/Index', [
            'users' => $users,
            'roles' => $roles,
            'roleOptions' => $roles->map(fn (array $role) => [
                'label' => $role['label'],
                'value' => $role['name'],
            ])->values(),
            'permissions' => $permissions,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'role' => ['required', 'exists:roles,name'],
            'attendance_enabled' => ['nullable', 'boolean'],
            'attendance_passcode' => ['nullable', 'string', 'min:4', 'max:20'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->ensureUniqueAttendancePasscode($validated['attendance_passcode'] ?? null);

        $role = Role::firstOrCreate(['name' => $validated['role']]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'attendance_enabled' => (bool) ($validated['attendance_enabled'] ?? false),
            'attendance_passcode' => filled($validated['attendance_passcode'] ?? null)
                ? Hash::make($validated['attendance_passcode'])
                : null,
        ]);

        $user->syncRoles([$role->name]);
        $user->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', Password::min(8)],
            'role' => ['required', 'exists:roles,name'],
            'attendance_enabled' => ['nullable', 'boolean'],
            'attendance_passcode' => ['nullable', 'string', 'min:4', 'max:20'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $this->ensureUniqueAttendancePasscode($validated['attendance_passcode'] ?? null, $user);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => !empty($validated['password'])
                ? Hash::make($validated['password'])
                : $user->password,
            'attendance_enabled' => (bool) ($validated['attendance_enabled'] ?? false),
            'attendance_passcode' => filled($validated['attendance_passcode'] ?? null)
                ? Hash::make($validated['attendance_passcode'])
                : $user->attendance_passcode,
        ]);

        $user->syncRoles([$validated['role']]);
        $user->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user, Request $request)
    {
        if ($request->user()?->is($user)) {
            return back()->withErrors([
                'user' => 'You cannot delete your own account from user management.',
            ]);
        }

        $user->syncRoles([]);
        $user->syncPermissions([]);
        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }

    public function updateRole(Request $request, Role $role)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name,' . $role->id],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->update([
            'name' => str($validated['name'])->snake()->toString(),
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Role updated successfully.');
    }

    public function storeRole(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:roles,name'],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role = Role::create([
            'name' => str($validated['name'])->snake()->toString(),
        ]);

        $role->syncPermissions($validated['permissions'] ?? []);

        return back()->with('success', 'Role created successfully.');
    }

    public function destroyRole(Role $role)
    {
        if ($role->users()->exists()) {
            return back()->withErrors([
                'role' => 'This role is assigned to users. Reassign those users before deleting it.',
            ]);
        }

        $role->syncPermissions([]);
        $role->delete();

        return back()->with('success', 'Role deleted successfully.');
    }

    public function updatePermission(Request $request, Permission $permission)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,' . $permission->id],
        ]);

        $permission->update([
            'name' => str($validated['name'])->snake()->toString(),
        ]);

        return back()->with('success', 'Permission updated successfully.');
    }

    public function storePermission(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
        ]);

        Permission::create([
            'name' => str($validated['name'])->snake()->toString(),
        ]);

        return back()->with('success', 'Permission created successfully.');
    }

    public function destroyPermission(Permission $permission)
    {
        $permission->roles()->detach();
        $permission->users()->detach();
        $permission->delete();

        return back()->with('success', 'Permission deleted successfully.');
    }

    private function ensureUniqueAttendancePasscode(?string $passcode, ?User $ignoreUser = null): void
    {
        if (! filled($passcode)) {
            return;
        }

        $duplicateExists = User::query()
            ->when($ignoreUser, fn ($query) => $query->whereKeyNot($ignoreUser->id))
            ->whereNotNull('attendance_passcode')
            ->get(['id', 'attendance_passcode'])
            ->contains(fn (User $user) => Hash::check($passcode, $user->attendance_passcode));

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'attendance_passcode' => 'This attendance passcode is already assigned to another user. Use a unique passcode.',
            ]);
        }
    }
}
