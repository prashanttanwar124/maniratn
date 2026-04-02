<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Staff;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $staffQuery = Staff::query()->with('user');

        if ($status === 'active') {
            $staffQuery->where('is_active', true);
        } elseif ($status === 'inactive') {
            $staffQuery->where('is_active', false);
        }

        if ($search !== '') {
            $staffQuery->where(function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('mobile', 'like', '%' . $search . '%')
                    ->orWhere('designation', 'like', '%' . $search . '%');
            });
        }

        $staff = $staffQuery
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $staffMember) => $this->transformStaff($staffMember));

        $availableUsers = User::query()
            ->with(['staffProfile', 'roles'])
            ->whereHas('roles', fn ($query) => $query->where('name', 'staff'))
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'attendance_enabled' => (bool) $user->attendance_enabled,
                'staff_id' => $user->staffProfile?->id,
            ])
            ->values();

        return Inertia::render('staff/Index', [
            'staff' => $staff,
            'availableUsers' => $availableUsers,
            'metrics' => [
                'staff_count' => Staff::query()->count(),
                'active_staff' => Staff::query()->where('is_active', true)->count(),
                'monthly_salary' => (float) Staff::query()->where('is_active', true)->sum('salary_amount'),
                'linked_users' => Staff::query()->whereNotNull('user_id')->count(),
            ],
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function store(Request $request)
    {
        Staff::create($this->validateStaff($request));

        return back()->with('success', 'Staff profile created successfully.');
    }

    public function update(Request $request, Staff $staff)
    {
        $staff->update($this->validateStaff($request, $staff));

        return back()->with('success', 'Staff profile updated successfully.');
    }

    public function destroy(Staff $staff)
    {
        $staff->delete();

        return back()->with('success', 'Staff profile deleted successfully.');
    }

    private function validateStaff(Request $request, ?Staff $staff = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mobile' => ['required', 'regex:/^[0-9]{10}$/', 'unique:staff,mobile,' . ($staff?->id ?? 'NULL')],
            'address' => ['nullable', 'string'],
            'designation' => ['required', 'string', 'max:255'],
            'joining_date' => ['required', 'date'],
            'is_active' => ['nullable', 'boolean'],
            'salary_amount' => ['required', 'numeric', 'min:0'],
            'user_id' => [
                'nullable',
                'exists:users,id',
                Rule::unique('staff', 'user_id')->ignore($staff?->id),
            ],
        ]);
    }

    private function transformStaff(Staff $staff): array
    {
        return [
            'id' => $staff->id,
            'name' => $staff->name,
            'mobile' => $staff->mobile,
            'address' => $staff->address,
            'designation' => $staff->designation,
            'joining_date' => optional($staff->joining_date)?->format('Y-m-d'),
            'is_active' => (bool) $staff->is_active,
            'salary_amount' => (float) $staff->salary_amount,
            'user_id' => $staff->user_id,
            'user' => $staff->user ? [
                'id' => $staff->user->id,
                'name' => $staff->user->name,
                'email' => $staff->user->email,
                'attendance_enabled' => (bool) $staff->user->attendance_enabled,
            ] : null,
        ];
    }
}
