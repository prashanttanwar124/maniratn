<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoldStockCountSession extends Model
{
    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function dailyRegister()
    {
        return $this->belongsTo(DailyRegister::class);
    }

    public function entries()
    {
        return $this->hasMany(GoldStockCountEntry::class, 'session_id');
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
