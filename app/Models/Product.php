<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{

    protected $guarded = [];

    // RELATIONSHIPS
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purity()
    {
        return $this->belongsTo(Purity::class);
    }

    public function verificationTags()
    {
        return $this->hasMany(VerificationTag::class);
    }

    // AUTO-BARCODE LOGIC
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $nextId = (Product::max('id') ?? 0) + 1;
            $product->barcode = 'G' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        });
    }
}
