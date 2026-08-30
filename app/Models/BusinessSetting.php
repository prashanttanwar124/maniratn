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
        'qr_onboarding_enabled',
        'qr_onboarding_token',
        'qr_onboarding_pin',
    ];

    protected function casts(): array
    {
        return [
            'ai_enabled' => 'boolean',
            'ai_voice_enabled' => 'boolean',
            'qr_onboarding_enabled' => 'boolean',
        ];
    }

    public static function generateQrOnboardingToken(): string
    {
        return 'mani_join_' . bin2hex(random_bytes(16));
    }


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
