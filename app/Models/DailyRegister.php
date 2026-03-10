<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailyRegister extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'opening_cash',
        'opening_gold',
        'opened_by',
        'closing_cash',
        'closing_gold',
        'difference_cash',
        'closed_at',
        'closed_by'
    ];
}
