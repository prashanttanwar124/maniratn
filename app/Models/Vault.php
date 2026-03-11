<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vault extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'balance',
    ];

    public function movements()
    {
        return $this->hasMany(VaultMovement::class);
    }
}
