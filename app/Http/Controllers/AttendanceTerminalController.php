<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\User;
use App\Models\AttendanceReason;
use Inertia\Response;
use Illuminate\Http\Request;
use App\Models\StaffAttendance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\StaffPresenceEvent;

class AttendanceTerminalController extends Controller
{
    private const INDIAN_TIMEZONE = 'Asia/Kolkata';

    public function show(): Response
    {
        return Inertia::render('attendance/Terminal', [
            'reasons' => $this->outReasons(),
        ]);
    }

    public function identify(Request $request)
    {
        $validated = $request->validate([
            'passcode' => ['required', 'string'],
        ]);

        $user = User::query()
            ->with('staffProfile')
            ->where('attendance_enabled', true)
            ->get()
            ->first(fn (User $candidate) => ! empty($candidate->attendance_passcode) && Hash::check($validated['passcode'], $candidate->attendance_passcode));

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid attendance passcode.',
            ], 422);
        }

        if ($user->staffProfile && ! $user->staffProfile->is_active) {
            return response()->json([
                'success' => false,
                'message' => "{$user->name} is marked inactive. Contact admin if this is incorrect.",
            ], 422);
        }

        $attendance = $this->todayAttendance($user);
        $state = $this->resolveState($attendance);

        if ($state === 'NOT_CHECKED_IN') {
            $attendance = DB::transaction(function () use ($user) {
                $attendance = StaffAttendance::create([
                    'user_id' => $user->id,
                    'date' => Carbon::today()->toDateString(),
                    'status' => 'PRESENT',
                    'check_in_at' => now(),
                ]);

                StaffPresenceEvent::create([
                    'staff_attendance_id' => $attendance->id,
                    'user_id' => $user->id,
                    'type' => 'CHECK_IN',
                    'event_time' => now(),
                ]);

                return $attendance;
            });

            return response()->json([
                'success' => true,
                'completed' => true,
                'message' => "{$user->name} checked in successfully.",
                'user' => $this->terminalUserPayload($user),
                'state' => 'IN_STORE',
                'attendance' => $this->attendancePayload($attendance),
            ]);
        }

        if ($state === 'OUT_OF_STORE') {
            $attendance = DB::transaction(function () use ($attendance, $user) {
                StaffPresenceEvent::create([
                    'staff_attendance_id' => $attendance->id,
                    'user_id' => $user->id,
                    'type' => 'BACK_IN',
                    'event_time' => now(),
                ]);

                return $attendance->fresh(['presenceEvents']);
            });

            return response()->json([
                'success' => true,
                'completed' => true,
                'message' => "{$user->name} marked back in store.",
                'user' => $this->terminalUserPayload($user),
                'state' => 'IN_STORE',
                'attendance' => $this->attendancePayload($attendance),
            ]);
        }

        if ($state === 'CHECKED_OUT') {
            return response()->json([
                'success' => false,
                'message' => "{$user->name} is already checked out for today.",
            ], 422);
        }

        return response()->json([
            'success' => true,
            'completed' => false,
            'message' => "Choose next action for {$user->name}.",
            'user' => $this->terminalUserPayload($user),
            'state' => 'IN_STORE',
            'attendance' => $this->attendancePayload($attendance),
            'available_actions' => [
                ['label' => 'Check Out', 'value' => 'CHECK_OUT'],
                ...$this->outReasons(),
            ],
        ]);
    }

    public function act(Request $request)
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'action' => ['required', 'string'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        abort_unless($user->attendance_enabled, 404);
        abort_if($user->staffProfile && ! $user->staffProfile->is_active, 422, 'This staff member is marked inactive.');

        $attendance = $this->todayAttendance($user);
        abort_unless($attendance, 422, 'Attendance record not found for today.');

        $state = $this->resolveState($attendance);
        abort_if($state !== 'IN_STORE', 422, 'This staff member is not currently marked in store.');

        $allowedActions = collect($this->outReasons())->pluck('value')->push('CHECK_OUT')->all();
        abort_unless(in_array($validated['action'], $allowedActions, true), 422, 'Invalid attendance action.');

        $attendance = DB::transaction(function () use ($attendance, $user, $validated) {
            if ($validated['action'] === 'CHECK_OUT') {
                $attendance->update([
                    'check_out_at' => now(),
                ]);

                StaffPresenceEvent::create([
                    'staff_attendance_id' => $attendance->id,
                    'user_id' => $user->id,
                    'type' => 'CHECK_OUT',
                    'notes' => $validated['notes'] ?? null,
                    'event_time' => now(),
                ]);

                return $attendance->fresh(['presenceEvents']);
            }

            StaffPresenceEvent::create([
                'staff_attendance_id' => $attendance->id,
                'user_id' => $user->id,
                'type' => 'OUT',
                'reason' => $validated['action'],
                'notes' => $validated['notes'] ?? null,
                'event_time' => now(),
            ]);

            return $attendance->fresh(['presenceEvents']);
        });

        $isCheckout = $validated['action'] === 'CHECK_OUT';

        return response()->json([
            'success' => true,
            'completed' => true,
            'message' => $isCheckout
                ? "{$user->name} checked out successfully."
                : "{$user->name} marked out for " . str($validated['action'])->replace('_', ' ')->lower() . '.',
            'user' => $this->terminalUserPayload($user),
            'state' => $isCheckout ? 'CHECKED_OUT' : 'OUT_OF_STORE',
            'attendance' => $this->attendancePayload($attendance),
        ]);
    }

    private function todayAttendance(User $user): ?StaffAttendance
    {
        return StaffAttendance::query()
            ->with(['presenceEvents' => fn ($query) => $query->latest('event_time')])
            ->where('user_id', $user->id)
            ->whereDate('date', Carbon::today())
            ->first();
    }

    private function resolveState(?StaffAttendance $attendance): string
    {
        if (! $attendance) {
            return 'NOT_CHECKED_IN';
        }

        if ($attendance->check_out_at) {
            return 'CHECKED_OUT';
        }

        $latestEvent = $attendance->presenceEvents->sortByDesc('event_time')->first();

        if ($latestEvent && $latestEvent->type === 'OUT') {
            return 'OUT_OF_STORE';
        }

        return 'IN_STORE';
    }

    private function terminalUserPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'designation' => $user->staffProfile?->designation,
            'staff_mobile' => $user->staffProfile?->mobile,
        ];
    }

    private function attendancePayload(?StaffAttendance $attendance): ?array
    {
        if (! $attendance) {
            return null;
        }

        $latestEvent = $attendance->presenceEvents->sortByDesc('event_time')->first();

        return [
            'check_in_at' => $this->formatIndianTime($attendance->check_in_at),
            'check_out_at' => $this->formatIndianTime($attendance->check_out_at),
            'latest_event' => $latestEvent ? [
                'type' => $latestEvent->type,
                'reason' => $latestEvent->reason,
                'event_time' => $this->formatIndianTime($latestEvent->event_time),
            ] : null,
        ];
    }

    private function outReasons(): array
    {
        return AttendanceReason::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['label', 'value'])
            ->map(fn (AttendanceReason $reason) => [
                'label' => $reason->label,
                'value' => $reason->value,
            ])
            ->values()
            ->all();
    }

    private function formatIndianTime($value): ?string
    {
        return $value ? $value->copy()->timezone(self::INDIAN_TIMEZONE)->format('h:i A') : null;
    }
}
