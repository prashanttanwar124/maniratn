<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MetalTransaction extends Model
{
    protected $fillable = [
        'party_type',
        'party_id',
        'type',
        'gross_weight',
        'purity',
        'fine_weight',
        'description',
        'date',
        'entry_source',
        'entry_type_code',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    // Connects to Supplier OR User (Karigar)
    public function party(): MorphTo
    {
        return $this->morphTo();
    }
}
