<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SilverProduct extends Model
{
    protected $guarded = [];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function verificationTags()
    {
        return $this->hasMany(VerificationTag::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $nextId = (SilverProduct::max('id') ?? 0) + 1;
            $product->barcode = 'S' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        });
    }
}
