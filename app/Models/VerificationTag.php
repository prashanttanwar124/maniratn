<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class VerificationTag extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'written_at' => 'datetime',
            'locked_at' => 'datetime',
            'last_verified_at' => 'datetime',
            'verified_count' => 'integer',
        ];
    }

    public static function generateToken(): string
    {
        do {
            $token = 'tag_' . strtoupper(Str::random(12));
        } while (static::query()->where('token', $token)->exists());

        return $token;
    }

    public function invoiceItem()
    {
        return $this->belongsTo(InvoiceItem::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function silverProduct()
    {
        return $this->belongsTo(SilverProduct::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function writtenBy()
    {
        return $this->belongsTo(User::class, 'written_by');
    }
}
