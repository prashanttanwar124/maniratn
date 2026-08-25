<?php

use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

test('task board attention shortcuts return the exact overdue and completed today queues', function () {
    Carbon::setTestNow('2026-08-25 12:00:00');

    $user = User::factory()->create();
    $this->actingAs($user);

    $overdueTask = Task::create([
        'title' => 'Overdue workshop pickup',
        'category' => 'KARIGAR_WORKSHOP',
        'priority' => 'URGENT',
        'status' => 'TODO',
        'due_date' => Carbon::yesterday(),
        'created_by' => $user->id,
    ]);

    Task::create([
        'title' => 'Old completed task',
        'category' => 'GENERAL',
        'priority' => 'LOW',
        'status' => 'COMPLETED',
        'due_date' => Carbon::yesterday(),
        'completed_at' => Carbon::yesterday(),
        'completed_by' => $user->id,
        'created_by' => $user->id,
    ]);

    $completedTodayTask = Task::create([
        'title' => 'Showroom display completed today',
        'category' => 'MAINTENANCE',
        'priority' => 'MEDIUM',
        'status' => 'COMPLETED',
        'completed_at' => Carbon::now(),
        'completed_by' => $user->id,
        'created_by' => $user->id,
    ]);

    $this->get(route('tasks.index', ['tab' => 'overdue']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $overdueTask->id)
        );

    $this->get(route('tasks.index', ['tab' => 'completed_today']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tasks/Index')
            ->has('tasks', 1)
            ->where('tasks.0.id', $completedTodayTask->id)
        );
});
