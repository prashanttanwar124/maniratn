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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($product) {
            $nextId = (SilverProduct::max('id') ?? 0) + 1;
            $product->barcode = 'S' . str_pad($nextId, 5, '0', STR_PAD_LEFT);
        });
    }

    public function legacyBarcode(): string
    {
        $categoryCode = $this->category?->code ?? Category::find($this->category_id)?->code ?? 'SLV';

        return 'MS-' . $categoryCode . '-' . str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
