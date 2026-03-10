<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $guarded = [];

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
