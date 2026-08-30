<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->boolean('qr_onboarding_enabled')->default(true)->after('ai_voice_name');
            $table->string('qr_onboarding_token', 64)->nullable()->after('qr_onboarding_enabled');
            $table->string('qr_onboarding_pin', 10)->nullable()->after('qr_onboarding_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'qr_onboarding_enabled',
                'qr_onboarding_token',
                'qr_onboarding_pin',
            ]);
        });
    }
};
