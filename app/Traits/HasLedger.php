<?php

namespace App\Traits;

use App\Models\Transaction;

trait HasLedger
{
    // 1. Link to the Transactions Table
    public function transactions()
    {
        return $this->morphMany(Transaction::class, 'transactable')->orderBy('date', 'desc');
    }

    // 2. Calculate Current Balance Automatically
    public function getBalanceAttribute()
    {
        // CLEAR LOGIC:
        // SALE    = They bought items (Balance goes UP)
        // PAYMENT = They paid cash (Balance goes DOWN)

        $totalSales    = $this->transactions()->where('type', 'SALE')->sum('amount');
        $totalPayments = $this->transactions()->where('type', 'PAYMENT')->sum('amount');

        // Result: 
        // Positive Number (e.g., 5000) = They Owe You ₹5,000
        // Zero (0) = Account Settled
        // Negative Number (e.g., -100) = You Owe Them ₹100 (Advance Payment)
        return $totalSales - $totalPayments;
    }
}
