<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Karigar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mobile',
    ];

    // RELATIONSHIPS

    // 1. CASH Transactions (Paid labor charges, advances)
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'transactable');
    }

    // 2. METAL Transactions (Gold issued/received)
    public function metalTransactions(): MorphMany
    {
        return $this->morphMany(MetalTransaction::class, 'party');
    }

    // Helper: Get Current Metal Balance (Gold they owe you)
    public function getMetalBalanceAttribute()
    {
        // ISSUE = You gave gold (They owe you +)
        // RECEIVE = They returned gold (They owe you -)

        $issued = $this->metalTransactions()->where('type', 'ISSUE')->sum('fine_weight');
        $received = $this->metalTransactions()->where('type', 'RECEIVE')->sum('fine_weight');

        return $issued - $received;
        // Positive result = Karigar has your gold.
    }
}
