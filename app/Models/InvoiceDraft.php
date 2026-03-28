<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceDraft extends Model
{
    protected $guarded = [];

    protected $casts = [
        'draft_data' => 'array',
        'grand_total' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
