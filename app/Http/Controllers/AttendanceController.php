<?php

namespace App\Http\Controllers;

use App\Models\AttendanceReason;
use App\Models\StaffAttendance;
use App\Models\StaffPresenceEvent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    private const INDIAN_TIMEZONE = 'Asia/Kolkata';

    public function index(Request $request): Response
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status', 'all');

        $attendanceQuery = StaffAttendance::query()
            ->with([
                'user.staffProfile',
                'presenceEvents' => fn ($query) => $query->orderByDesc('event_time'),
            ])
            ->whereDate('date', $date);

        if ($search !== '') {
            $attendanceQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', '%' . $search . '%')
                    ->orWhere('email', 'like', '%' . $search . '%')
                    ->orWhereHas('staffProfile', function ($staffQuery) use ($search) {
                        $staffQuery->where('designation', 'like', '%' . $search . '%')
                            ->orWhere('mobile', 'like', '%' . $search . '%');
                    });
            });
        }

        if ($status !== 'all') {
            $attendanceQuery->where('status', strtoupper($status));
        }

        $records = $attendanceQuery
            ->orderBy('check_in_at')
            ->get()
            ->map(fn (StaffAttendance $attendance) => $this->transformAttendance($attendance))
            ->values();

        $baseMetricsQuery = StaffAttendance::query()->whereDate('date', $date);

        return Inertia::render('attendance/Index', [
            'records' => $records,
            'reasons' => AttendanceReason::query()
                ->orderBy('sort_order')
                ->orderBy('label')
                ->get()
                ->map(fn (AttendanceReason $reason) => [
                    'id' => $reason->id,
                    'label' => $reason->label,
                    'value' => $reason->value,
                    'is_active' => (bool) $reason->is_active,
                    'sort_order' => (int) $reason->sort_order,
                ])
                ->values(),
            'filters' => [
                'date' => $date,
                'search' => $search,
                'status' => $status,
            ],
            'metrics' => [
                'total' => (clone $baseMetricsQuery)->count(),
                'checked_out' => (clone $baseMetricsQuery)->whereNotNull('check_out_at')->count(),
                'in_store' => (clone $baseMetricsQuery)->get()->filter(fn (StaffAttendance $attendance) => $this->resolveState($attendance) === 'IN_STORE')->count(),
                'out_of_store' => (clone $baseMetricsQuery)->get()->filter(fn (StaffAttendance $attendance) => $this->resolveState($attendance) === 'OUT_OF_STORE')->count(),
            ],
        ]);
    }

    public function reopen(Request $request, StaffAttendance $attendance)
    {
        $validated = $request->validate([
            'note' => ['required', 'string', 'max:255'],
        ]);

        if (! $attendance->check_out_at) {
            return back()->withErrors([
                'attendance' => 'This attendance record is not checked out.',
            ]);
        }

        DB::transaction(function () use ($attendance, $validated, $request) {
            $attendance->update([
                'check_out_at' => null,
                'status' => 'PRESENT',
                'notes' => $validated['note'],
                'marked_by' => $request->user()?->id,
            ]);

            StaffPresenceEvent::create([
                'staff_attendance_id' => $attendance->id,
                'user_id' => $attendance->user_id,
                'type' => 'REOPEN',
                'notes' => $validated['note'],
                'event_time' => now(),
            ]);
        });

        return back()->with('success', 'Checkout reopened successfully.');
    }

    public function storeReason(Request $request)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $baseValue = strtoupper((string) str($validated['label'])->replaceMatches('/[^A-Za-z0-9]+/', '_')->trim('_'));
        $value = $baseValue !== '' ? $baseValue : 'REASON';
        $suffix = 1;

        while (AttendanceReason::query()->where('value', $value)->exists()) {
            $suffix++;
            $value = "{$baseValue}_{$suffix}";
        }

        AttendanceReason::create([
            'label' => $validated['label'],
            'value' => $value,
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'sort_order' => ((int) AttendanceReason::max('sort_order')) + 1,
        ]);

        return back()->with('success', 'Attendance reason added successfully.');
    }

    public function updateReason(Request $request, AttendanceReason $reason)
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $reason->update([
            'label' => $validated['label'],
            'is_active' => (bool) ($validated['is_active'] ?? false),
        ]);

        return back()->with('success', 'Attendance reason updated successfully.');
    }

    public function destroyReason(AttendanceReason $reason)
    {
        $reason->delete();

        return back()->with('success', 'Attendance reason deleted successfully.');
    }

    private function transformAttendance(StaffAttendance $attendance): array
    {
        $latestEvent = $attendance->presenceEvents->sortByDesc('event_time')->first();
        $staffProfile = $attendance->user?->staffProfile;

        return [
            'id' => $attendance->id,
            'date' => optional($attendance->date)?->format('Y-m-d'),
            'status' => $attendance->status,
            'state' => $this->resolveState($attendance),
            'check_in_at' => $this->formatIndianTime($attendance->check_in_at),
            'check_out_at' => $this->formatIndianTime($attendance->check_out_at),
            'notes' => $attendance->notes,
            'staff' => [
                'name' => $attendance->user?->name,
                'email' => $attendance->user?->email,
                'designation' => $staffProfile?->designation,
                'mobile' => $staffProfile?->mobile,
            ],
            'latest_event' => $latestEvent ? [
                'type' => $latestEvent->type,
                'reason' => $latestEvent->reason,
                'notes' => $latestEvent->notes,
                'event_time' => $this->formatIndianTime($latestEvent->event_time),
            ] : null,
            'can_reopen' => ! is_null($attendance->check_out_at),
        ];
    }

    private function resolveState(StaffAttendance $attendance): string
    {
        if ($attendance->check_out_at) {
            return 'CHECKED_OUT';
        }

        $latestEvent = $attendance->presenceEvents->sortByDesc('event_time')->first();

        if ($latestEvent && $latestEvent->type === 'OUT') {
            return 'OUT_OF_STORE';
        }

        return 'IN_STORE';
    }

    private function formatIndianTime($value): ?string
    {
        return $value ? $value->copy()->timezone(self::INDIAN_TIMEZONE)->format('h:i A') : null;
    }
}
