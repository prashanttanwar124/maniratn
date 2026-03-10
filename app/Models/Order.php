<?php

namespace App\Models;

use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }



    // Who is making it? (Karigar or Supplier)
    public function assignee()
    {
        return $this->morphTo();
    }

    // Link to the Metal Transactions (The History)
    public function metalTransactions()
    {
        return $this->hasMany(MetalTransaction::class);
    }
}
