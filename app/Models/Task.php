<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'category',
        'priority',
        'status',
        'due_date',
        'due_time',
        'assigned_to',
        'created_by',
        'completed_at',
        'completed_by',
        'checklist',
        'is_pinned',
        'handover_notes',
        'related_type',
        'related_id',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
            'checklist' => 'array',
            'is_pinned' => 'boolean',
        ];
    }

    protected $appends = [
        'is_overdue',
        'checklist_progress',
        'total_subtasks',
        'completed_subtasks',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors & Mutators
    |--------------------------------------------------------------------------
    */

    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'COMPLETED' || $this->status === 'CANCELLED' || ! $this->due_date) {
            return false;
        }

        return Carbon::parse($this->due_date)->endOfDay()->isPast();
    }

    public function getTotalSubtasksAttribute(): int
    {
        return is_array($this->checklist) ? count($this->checklist) : 0;
    }

    public function getCompletedSubtasksAttribute(): int
    {
        if (! is_array($this->checklist)) {
            return 0;
        }

        return count(array_filter($this->checklist, fn ($item) => ! empty($item['is_completed'])));
    }

    public function getChecklistProgressAttribute(): int
    {
        $total = $this->total_subtasks;
        if ($total === 0) {
            return $this->status === 'COMPLETED' ? 100 : 0;
        }

        return (int) round(($this->completed_subtasks / $total) * 100);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', ['TODO', 'IN_PROGRESS']);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'COMPLETED');
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->whereDate('due_date', Carbon::today());
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->whereDate('due_date', '<', Carbon::today());
    }

    public function scopeAssignedToUser(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to', $userId);
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (empty($term)) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term) {
            $q->where('title', 'LIKE', "%{$term}%")
                ->orWhere('description', 'LIKE', "%{$term}%")
                ->orWhere('category', 'LIKE', "%{$term}%")
                ->orWhereHas('assignedTo', function (Builder $sub) use ($term) {
                    $sub->where('name', 'LIKE', "%{$term}%")
                        ->orWhere('email', 'LIKE', "%{$term}%");
                });
        });
    }
}
