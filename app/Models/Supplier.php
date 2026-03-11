<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

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
        // Standard ledger rule:
        // ISSUE = gold sent to the party
        // RECEIPT = gold received back from the party
        // Positive result means supplier currently holds your gold.
        $issued = $this->metalTransactions()->where('type', 'ISSUE')->sum('gross_weight');
        $received = $this->metalTransactions()->where('type', 'RECEIPT')->sum('gross_weight');

        return $issued - $received;
    }

    public function setCompanyNameAttribute($value): void
    {
        $this->attributes['company_name'] = $this->toTitleCase($value);
    }

    public function setContactPersonAttribute($value): void
    {
        $this->attributes['contact_person'] = $this->toTitleCase($value);
    }

    private function toTitleCase($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return $value;
        }

        return Str::of(trim((string) $value))->lower()->title()->toString();
    }
}
