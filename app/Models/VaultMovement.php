<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VaultMovement extends Model
{
    protected $fillable = [
        'vault_id',
        'vault_type',
        'direction',
        'amount',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
        'reference',
        'note',
        'user_id',
        'recorded_at',
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
    ];

    public function vault()
    {
        return $this->belongsTo(Vault::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
