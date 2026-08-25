<?php

namespace App\Services\Ai\Actions;

use App\Models\Task;
use App\Models\User;
use App\Services\Ai\Contracts\AiActionInterface;
use Carbon\Carbon;

class TaskAction implements AiActionInterface
{
    public function handle(array $args): array
    {
        $action = $args['action'] ?? 'get'; // 'get' or 'create'
        $userId = auth()->id();

        if ($action === 'create') {
            $title = trim($args['title'] ?? '');
            if (empty($title)) {
                return [
                    'found' => false,
                    'message' => 'Task title zaroori hai.',
                ];
            }

            $category = strtoupper($args['category'] ?? 'GENERAL');
            $priority = strtoupper($args['priority'] ?? 'MEDIUM');
            $dueDate = ! empty($args['due_date']) ? Carbon::parse($args['due_date'])->format('Y-m-d') : Carbon::today()->format('Y-m-d');
            $assignedTo = ! empty($args['assigned_to_user_id']) ? (int) $args['assigned_to_user_id'] : $userId;

            $task = Task::create([
                'title' => $title,
                'description' => $args['description'] ?? null,
                'category' => in_array($category, ['CUSTOMER_FOLLOWUP', 'KARIGAR_WORKSHOP', 'INVENTORY_AUDIT', 'BILLING_FINANCE', 'MAINTENANCE', 'GENERAL']) ? $category : 'GENERAL',
                'priority' => in_array($priority, ['LOW', 'MEDIUM', 'HIGH', 'URGENT']) ? $priority : 'MEDIUM',
                'status' => 'TODO',
                'due_date' => $dueDate,
                'assigned_to' => $assignedTo,
                'created_by' => $userId ?: User::first()?->id ?: 1,
                'checklist' => ! empty($args['checklist']) && is_array($args['checklist']) ? $args['checklist'] : null,
                'is_pinned' => (bool) ($args['is_pinned'] ?? false),
            ]);

            return [
                'found' => true,
                'created' => true,
                'task' => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'category' => $task->category,
                    'priority' => $task->priority,
                    'status' => $task->status,
                    'due_date' => $task->due_date,
                    'assigned_to_name' => $task->assignedTo?->name,
                ],
                'message' => "Task #{$task->id} '{$task->title}' create ho gaya hai!",
            ];
        }

        // Default: Get / Query Tasks
        $filter = $args['filter'] ?? 'pending'; // 'pending', 'all', 'today', 'my'
        $query = Task::with('assignedTo:id,name');

        if ($filter === 'my' && $userId) {
            $query->where('assigned_to', $userId)->whereIn('status', ['TODO', 'IN_PROGRESS']);
        } elseif ($filter === 'today') {
            $query->whereDate('due_date', Carbon::today())->whereIn('status', ['TODO', 'IN_PROGRESS']);
        } elseif ($filter === 'pending') {
            $query->whereIn('status', ['TODO', 'IN_PROGRESS']);
        }

        $tasks = $query->latest('id')->take(6)->get();

        return [
            'found' => true,
            'count' => $tasks->count(),
            'filter' => $filter,
            'tasks' => $tasks->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'category' => $t->category,
                'priority' => $t->priority,
                'status' => $t->status,
                'due_date' => $t->due_date,
                'assigned_to_name' => $t->assignedTo?->name ?? 'Unassigned',
                'is_overdue' => $t->is_overdue,
            ])->toArray(),
        ];
    }
}
