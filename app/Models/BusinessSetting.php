<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class BusinessSetting extends Model
{
    protected $fillable = [
        'store_name',
        'address',
        'phone',
        'email',
        'website',
        'google_review_url',
        'gst_number',
        'logo_path',
        'ai_enabled',
        'ai_hub_url',
        'ai_api_key',
        'ai_voice_enabled',
        'ai_voice_name',
    ];

    protected $appends = [
        'logo_url',
    ];

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::url($this->logo_path);
    }
}
