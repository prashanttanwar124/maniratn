<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceItem extends Model
{
    protected $guarded = [];

    public function verificationTags()
    {
        return $this->hasMany(VerificationTag::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function silverProduct()
    {
        return $this->belongsTo(SilverProduct::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }
}
