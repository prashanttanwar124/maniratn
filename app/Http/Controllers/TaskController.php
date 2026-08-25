<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks with Kanban & Table views.
     */
    public function index(Request $request): Response
    {
        $userId = auth()->id();
        $tab = $request->input('tab', 'all'); // all, my_tasks, urgent, due_today, completed
        $search = $request->input('search');
        $status = $request->input('status', 'all');
        $priority = $request->input('priority', 'all');
        $category = $request->input('category', 'all');
        $assignedTo = $request->input('assigned_to', 'all');
        $viewMode = $request->input('view', 'kanban'); // kanban, table

        $query = Task::with([
            'assignedTo:id,name,email',
            'creator:id,name,email',
            'completedBy:id,name,email',
        ]);

        // Search Filter
        if (! empty($search)) {
            $query->search($search);
        }

        // Tab Presets
        if ($tab === 'my_tasks') {
            $query->where('assigned_to', $userId);
        } elseif ($tab === 'urgent') {
            $query->whereIn('priority', ['HIGH', 'URGENT'])->whereIn('status', ['TODO', 'IN_PROGRESS']);
        } elseif ($tab === 'due_today') {
            $query->whereDate('due_date', Carbon::today())->whereIn('status', ['TODO', 'IN_PROGRESS']);
        } elseif ($tab === 'completed') {
            $query->where('status', 'COMPLETED');
        }

        // Status Filter
        if ($status !== 'all') {
            $query->where('status', $status);
        }

        // Priority Filter
        if ($priority !== 'all') {
            $query->where('priority', $priority);
        }

        // Category Filter
        if ($category !== 'all') {
            $query->where('category', $category);
        }

        // Assignee Filter
        if ($assignedTo !== 'all') {
            if ($assignedTo === 'unassigned') {
                $query->whereNull('assigned_to');
            } else {
                $query->where('assigned_to', (int) $assignedTo);
            }
        }

        // Priority and Pinned sorting
        $tasks = $query->orderByDesc('is_pinned')
            ->orderByRaw("CASE 
                WHEN status = 'TODO' THEN 1 
                WHEN status = 'IN_PROGRESS' THEN 2 
                WHEN status = 'COMPLETED' THEN 3 
                ELSE 4 END")
            ->orderByRaw("CASE 
                WHEN priority = 'URGENT' THEN 1 
                WHEN priority = 'HIGH' THEN 2 
                WHEN priority = 'MEDIUM' THEN 3 
                ELSE 4 END")
            ->orderBy('due_date')
            ->latest('id')
            ->get();

        // Real-time Dashboard Metrics
        $allTasksQuery = Task::query();
        $metrics = [
            'total' => (clone $allTasksQuery)->count(),
            'todo' => (clone $allTasksQuery)->where('status', 'TODO')->count(),
            'in_progress' => (clone $allTasksQuery)->where('status', 'IN_PROGRESS')->count(),
            'completed' => (clone $allTasksQuery)->where('status', 'COMPLETED')->count(),
            'completed_today' => (clone $allTasksQuery)->where('status', 'COMPLETED')->whereDate('completed_at', Carbon::today())->count(),
            'overdue' => (clone $allTasksQuery)->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->whereDate('due_date', '<', Carbon::today())->count(),
            'due_today' => (clone $allTasksQuery)->whereNotIn('status', ['COMPLETED', 'CANCELLED'])->whereDate('due_date', Carbon::today())->count(),
            'my_pending' => (clone $allTasksQuery)->where('assigned_to', $userId)->whereIn('status', ['TODO', 'IN_PROGRESS'])->count(),
        ];

        // Available Users / Staff for assignment
        $availableUsers = User::query()
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        // Optional Customers for quick task linking
        $customers = Customer::query()
            ->select('id', 'name', 'mobile')
            ->latest('id')
            ->take(30)
            ->get();

        return Inertia::render('tasks/Index', [
            'tasks' => $tasks,
            'metrics' => $metrics,
            'availableUsers' => $availableUsers,
            'customers' => $customers,
            'filters' => [
                'tab' => $tab,
                'search' => $search ?? '',
                'status' => $status,
                'priority' => $priority,
                'category' => $category,
                'assigned_to' => $assignedTo,
                'view' => $viewMode,
            ],
            'categories' => [
                ['value' => 'CUSTOMER_FOLLOWUP', 'label' => 'Customer Follow-up', 'icon' => 'pi-phone', 'color' => 'emerald'],
                ['value' => 'KARIGAR_WORKSHOP', 'label' => 'Karigar & Workshop', 'icon' => 'pi-wrench', 'color' => 'amber'],
                ['value' => 'INVENTORY_AUDIT', 'label' => 'Inventory & Stock Audit', 'icon' => 'pi-box', 'color' => 'blue'],
                ['value' => 'BILLING_FINANCE', 'label' => 'Billing & Finance', 'icon' => 'pi-wallet', 'color' => 'purple'],
                ['value' => 'MAINTENANCE', 'label' => 'Showroom Maintenance', 'icon' => 'pi-cog', 'color' => 'teal'],
                ['value' => 'GENERAL', 'label' => 'General Operations', 'icon' => 'pi-check-circle', 'color' => 'slate'],
            ],
            'priorities' => [
                ['value' => 'LOW', 'label' => 'Low', 'color' => 'slate'],
                ['value' => 'MEDIUM', 'label' => 'Medium', 'color' => 'blue'],
                ['value' => 'HIGH', 'label' => 'High', 'color' => 'amber'],
                ['value' => 'URGENT', 'label' => 'Urgent 🔥', 'color' => 'rose'],
            ],
        ]);
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|in:CUSTOMER_FOLLOWUP,KARIGAR_WORKSHOP,INVENTORY_AUDIT,BILLING_FINANCE,MAINTENANCE,GENERAL',
            'priority' => 'required|string|in:LOW,MEDIUM,HIGH,URGENT',
            'status' => 'nullable|string|in:TODO,IN_PROGRESS,COMPLETED',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'checklist' => 'nullable|array',
            'checklist.*.text' => 'required|string|max:255',
            'checklist.*.is_completed' => 'boolean',
            'is_pinned' => 'boolean',
            'handover_notes' => 'nullable|string|max:1000',
            'related_type' => 'nullable|string|max:50',
            'related_id' => 'nullable|integer',
        ]);

        $validated['created_by'] = auth()->id();
        $validated['status'] = $validated['status'] ?? 'TODO';

        if (($validated['status'] ?? '') === 'COMPLETED') {
            $validated['completed_at'] = Carbon::now();
            $validated['completed_by'] = auth()->id();
        }

        // Format checklist items with unique IDs if not provided
        if (! empty($validated['checklist'])) {
            $validated['checklist'] = array_values(array_map(function ($item, $idx) {
                return [
                    'id' => (string) ($item['id'] ?? (time() . '_' . $idx)),
                    'text' => trim($item['text']),
                    'is_completed' => (bool) ($item['is_completed'] ?? false),
                ];
            }, $validated['checklist'], array_keys($validated['checklist'])));
        }

        Task::create($validated);

        return redirect()->back()->with('success', 'Task safaltapoorvak create ho gaya!');
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, Task $task): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'category' => 'required|string|in:CUSTOMER_FOLLOWUP,KARIGAR_WORKSHOP,INVENTORY_AUDIT,BILLING_FINANCE,MAINTENANCE,GENERAL',
            'priority' => 'required|string|in:LOW,MEDIUM,HIGH,URGENT',
            'status' => 'required|string|in:TODO,IN_PROGRESS,COMPLETED,CANCELLED',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'checklist' => 'nullable|array',
            'checklist.*.text' => 'required|string|max:255',
            'checklist.*.is_completed' => 'boolean',
            'is_pinned' => 'boolean',
            'handover_notes' => 'nullable|string|max:1000',
            'related_type' => 'nullable|string|max:50',
            'related_id' => 'nullable|integer',
        ]);

        if ($validated['status'] === 'COMPLETED' && $task->status !== 'COMPLETED') {
            $validated['completed_at'] = Carbon::now();
            $validated['completed_by'] = auth()->id();
        } elseif ($validated['status'] !== 'COMPLETED') {
            $validated['completed_at'] = null;
            $validated['completed_by'] = null;
        }

        if (isset($validated['checklist'])) {
            $validated['checklist'] = array_values(array_map(function ($item, $idx) {
                return [
                    'id' => (string) ($item['id'] ?? (time() . '_' . $idx)),
                    'text' => trim($item['text']),
                    'is_completed' => (bool) ($item['is_completed'] ?? false),
                ];
            }, $validated['checklist'], array_keys($validated['checklist'])));
        }

        $task->update($validated);

        return redirect()->back()->with('success', 'Task update ho gaya!');
    }

    /**
     * Quick status toggle (TODO -> IN_PROGRESS -> COMPLETED).
     */
    public function updateStatus(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:TODO,IN_PROGRESS,COMPLETED,CANCELLED',
        ]);

        $newStatus = $validated['status'];
        $updates = ['status' => $newStatus];

        if ($newStatus === 'COMPLETED') {
            $updates['completed_at'] = Carbon::now();
            $updates['completed_by'] = auth()->id();
        } else {
            $updates['completed_at'] = null;
            $updates['completed_by'] = null;
        }

        $task->update($updates);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'task' => $task->fresh(['assignedTo', 'creator', 'completedBy']),
                'message' => 'Status updated to ' . $newStatus,
            ]);
        }

        return redirect()->back()->with('success', 'Task status update ho gaya!');
    }

    /**
     * Toggle a single checklist subtask item.
     */
    public function toggleChecklistItem(Request $request, Task $task, string $itemId): JsonResponse|RedirectResponse
    {
        $checklist = $task->checklist ?? [];
        $updated = false;

        foreach ($checklist as &$item) {
            if (($item['id'] ?? '') === $itemId) {
                $item['is_completed'] = ! ($item['is_completed'] ?? false);
                $updated = true;
                break;
            }
        }

        if ($updated) {
            $task->update(['checklist' => $checklist]);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'task' => $task->fresh(),
            ]);
        }

        return redirect()->back();
    }

    /**
     * Toggle pinned status.
     */
    public function togglePin(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $task->update(['is_pinned' => ! $task->is_pinned]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_pinned' => $task->is_pinned,
            ]);
        }

        return redirect()->back()->with('success', $task->is_pinned ? 'Task pin kiya gaya!' : 'Task unpin kiya gaya!');
    }

    /**
     * Remove the specified task.
     */
    public function destroy(Task $task): RedirectResponse
    {
        $task->delete();

        return redirect()->back()->with('success', 'Task delete ho gaya!');
    }
}
