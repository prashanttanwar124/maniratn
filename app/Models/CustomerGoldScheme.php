<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerGoldScheme extends Model
{
    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'maturity_date' => 'date',
        'monthly_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'bonus_applied_at' => 'datetime',
        'redeemed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function (CustomerGoldScheme $scheme) {
            if (! $scheme->scheme_number) {
                $nextId = (static::max('id') ?? 0) + 1;
                $scheme->scheme_number = 'GS-' . now()->format('Ymd') . '-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function installments()
    {
        return $this->hasMany(GoldSchemeInstallment::class);
    }

    public function getExpectedCustomerTotalAttribute(): float
    {
        return (float) $this->monthly_amount * (int) $this->total_months;
    }

    public function getRedeemableTotalAttribute(): float
    {
        $bonus = $this->bonus_applied_at ? (float) $this->bonus_amount : 0;

        return (float) $this->paid_total + $bonus;
    }
}
