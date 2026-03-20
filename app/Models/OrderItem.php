<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $casts = [
        'target_weight' => 'decimal:3',
        'purity' => 'decimal:2',
        'finished_weight' => 'decimal:3',
        'wastage' => 'decimal:3',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    // Also include relationships to Assignee (Karigar/Supplier) if needed
    public function assignee()
    {
        return $this->morphTo();
    }
}
