<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldStockCountEntry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function session()
    {
        return $this->belongsTo(GoldStockCountSession::class, 'session_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function scannedBy()
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }
}
