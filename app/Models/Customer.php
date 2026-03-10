<?php

namespace App\Models;

use App\Traits\HasLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use HasLedger;

    public function mortgages()
    {
        return $this->hasMany(Mortgage::class);
    }

    // Custom Attribute: Total Active Loans
    public function getTotalMortgageAmountAttribute()
    {
        return $this->mortgages()
            ->where('status', 'ACTIVE')
            ->sum('loan_amount');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactable');
    }

    // 2. METAL Transactions (Add This)
    public function metalTransactions(): MorphMany
    {
        return $this->morphMany(MetalTransaction::class, 'party');
    }
}
