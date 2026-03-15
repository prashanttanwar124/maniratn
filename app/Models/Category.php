<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    // A Category has many Products
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function silverProducts()
    {
        return $this->hasMany(SilverProduct::class);
    }
}
