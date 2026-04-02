<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Staff extends Model
{
    protected $table = 'staff';

    protected $fillable = [
        'name',
        'mobile',
        'address',
        'designation',
        'joining_date',
        'is_active',
        'salary_amount',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'joining_date' => 'date',
            'is_active' => 'boolean',
            'salary_amount' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
