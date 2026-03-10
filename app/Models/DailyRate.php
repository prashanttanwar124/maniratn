<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DailyRate extends Model
{
    use HasFactory;


    protected $fillable = [
        'date',
        'gold_buy',
        'gold_sell',
        'silver_sell',
    ];
}
