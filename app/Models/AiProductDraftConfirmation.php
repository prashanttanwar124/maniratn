<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiProductDraftConfirmation extends Model
{
    protected $guarded = [];

    protected $casts = [
        'result' => 'array',
    ];
}
