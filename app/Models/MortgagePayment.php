<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MortgagePayment extends Model
{
    protected $fillable = [
        'mortgage_id',
        'amount',
        'type',
        'date',
        'note'
    ];
}
