<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceOldGold extends Model
{
    use HasFactory;

    protected $table = 'invoice_old_golds';

    protected $guarded = [];

    protected $casts = [
        'gross_weight' => 'decimal:3',
        'wastage_weight' => 'decimal:3',
        'net_weight' => 'decimal:3',
        'rate' => 'decimal:2',
        'final_price' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
