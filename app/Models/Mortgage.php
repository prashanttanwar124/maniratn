<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Mortgage extends Model
{
    protected $guarded = [];

    protected $appends = ['image_url', 'pending_amount', 'total_interest_paid'];

    public function getImageUrlAttribute()
    {
        return $this->image_path
            ? Storage::url($this->image_path)
            : null; // Or return a default placeholder image
    }

    // 2. Link to Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function payments()
    {
        return $this->hasMany(MortgagePayment::class);
    }

    // Virtual Attribute: How much principal is left?
    public function getPendingAmountAttribute()
    {
        $paidPrincipal = $this->payments()
            ->where('type', 'PRINCIPAL')
            ->sum('amount');

        return $this->loan_amount - $paidPrincipal;
    }

    // Virtual Attribute: Total Interest collected so far
    public function getTotalInterestPaidAttribute()
    {
        return $this->payments()
            ->where('type', 'INTEREST')
            ->sum('amount');
    }
}
