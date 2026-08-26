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
        'gross_weight',
        'fine_weight',
        'purity_percent',
        'balance_before',
        'balance_after',
        'source_type',
        'source_id',
        'reference',
        'correlation_id',
        'operation_key',
        'note',
        'user_id',
        'recorded_at',
    ];

    protected $casts = [
        'amount' => 'decimal:3',
        'gross_weight' => 'decimal:3',
        'fine_weight' => 'decimal:3',
        'purity_percent' => 'decimal:4',
        'balance_before' => 'decimal:3',
        'balance_after' => 'decimal:3',
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
