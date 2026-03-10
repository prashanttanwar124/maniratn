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

    // AUTO-BARCODE LOGIC
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            // 1. Get the category code (e.g., "RNG")
            // We must load the relationship because 'category_id' is just a number (e.g., 1)
            $categoryCode = Category::find($product->category_id)->code;

            // 2. Get the next ID
            $nextId = (Product::max('id') ?? 0) + 1;

            // 3. Generate: MJ-RNG-00001
            $product->barcode = 'MJ-' . $categoryCode . '-' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        });
    }
}
