<?php

namespace App\Models;

use App\Traits\HasLedger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use HasLedger;

    protected $fillable = [
        'name',
        'mobile',
        'email',
        'address',
        'city',
        'pan_no',
        'aadhaar_no',
        'dob',
        'anniversary_date',
        'membership_id',
    ];

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

    public function setNameAttribute($value): void
    {
        $this->attributes['name'] = $this->toTitleCase($value);
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
