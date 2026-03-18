<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldSchemeInstallment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'due_date' => 'date',
        'paid_on' => 'date',
        'amount_due' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'voided_at' => 'datetime',
    ];

    public function scheme()
    {
        return $this->belongsTo(CustomerGoldScheme::class, 'customer_gold_scheme_id');
    }

    public function collector()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function voider()
    {
        return $this->belongsTo(User::class, 'voided_by');
    }
}
