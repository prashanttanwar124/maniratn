<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $guarded = [];

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function silverProducts()
    {
        return $this->hasMany(SilverProduct::class);
    }

    public function scopeGold($query)
    {
        return $query->where('metal_type', 'GOLD');
    }

    public function scopeSilver($query)
    {
        return $query->where('metal_type', 'SILVER');
    }
}
