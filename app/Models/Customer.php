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
        'vault_token',
        'nfc_card_uid',
        'card_status',
        'card_issued_at',
        'card_written_at',
        'card_locked_at',
        'card_last_accessed_at',
        'card_access_count',
        'card_pin',
        'card_notes',
    ];

    protected function casts(): array
    {
        return [
            'card_issued_at' => 'datetime',
            'card_written_at' => 'datetime',
            'card_locked_at' => 'datetime',
            'card_last_accessed_at' => 'datetime',
            'card_access_count' => 'integer',
        ];
    }

    public static function generateVaultToken(): string
    {
        do {
            $token = 'vault_' . strtoupper(Str::random(12));
        } while (static::query()->where('vault_token', $token)->exists());

        return $token;
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

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

    public function goldSchemes()
    {
        return $this->hasMany(CustomerGoldScheme::class);
    }

    public function verificationTags()
    {
        return $this->hasMany(VerificationTag::class);
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
