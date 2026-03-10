<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\MorphMany;

class Supplier extends Model
{
    protected $fillable = [
        // 1. Identity
        'company_name',
        'contact_person',
        'mobile',

        // 2. Taxation
        'gst_number',
        'pan_no',

        // 3. Bank Details
        'bank_name',
        'account_no',
        'ifsc_code',

        // 4. Category/Type
        'type',
    ];

    // ... existing code ...

    // 1. CASH Transactions (from your existing 'transactions' table)
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactable');
    }

    // 2. METAL Transactions (The new table)
    public function metalTransactions(): MorphMany
    {
        return $this->morphMany(MetalTransaction::class, 'party');
    }

    // Helper: Get Current Metal Balance (Fine Gold)
    public function getMetalBalanceAttribute()
    {
        // ISSUE (You gave) is negative for them? Or Positive? 
        // Let's standardise: 
        // We OWE them (Credit) = Positive
        // They OWE us (Debit) = Negative

        $given = $this->metalTransactions()->where('type', 'ISSUE')->sum('fine_weight');
        $received = $this->metalTransactions()->where('type', 'RECEIVE')->sum('fine_weight');

        return $received - $given;
        // If result is positive, YOU owe the supplier gold.
    }
}
