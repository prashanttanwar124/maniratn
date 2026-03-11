<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

class Karigar extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'mobile',
        'work_type',
        'city',
        'notes',
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
        // RECEIPT = They returned gold (They owe you -)

        $issued = $this->metalTransactions()->where('type', 'ISSUE')->sum('gross_weight');
        $received = $this->metalTransactions()->where('type', 'RECEIPT')->sum('gross_weight');

        return $issued - $received;
        // Positive result = Karigar has your gold.
    }

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $this->toTitleCase($value);
    }

    public function setWorkTypeAttribute($value): void
    {
        $this->attributes['work_type'] = $this->toTitleCase($value);
    }

    public function setCityAttribute($value): void
    {
        $this->attributes['city'] = $this->toTitleCase($value);
    }

    private function toTitleCase($value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return $value;
        }

        return Str::of(trim((string) $value))->lower()->title()->toString();
    }
}
