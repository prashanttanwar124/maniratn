<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'session_number',
        'opening_cash',
        'opening_gold',
        'opening_silver',
        'expected_opening_cash',
        'expected_opening_gold',
        'expected_opening_silver',
        'opening_mismatch_reason',
        'reopen_reason',
        'reopened_from_id',
        'opened_by',
        'closing_cash',
        'closing_gold',
        'closing_silver',
        'difference_cash',
        'difference_gold',
        'difference_silver',
        'closed_at',
        'closed_by'
    ];

    protected $casts = [
        'date' => 'date',
        'closed_at' => 'datetime',
    ];
}
