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
            $table->boolean('ai_enabled')->default(true)->after('logo_path');
            $table->string('ai_hub_url')->nullable()->default('http://127.0.0.1:8001')->after('ai_enabled');
            $table->string('ai_api_key')->nullable()->after('ai_hub_url');
            $table->boolean('ai_voice_enabled')->default(true)->after('ai_api_key');
            $table->string('ai_voice_name')->default('Aoede')->after('ai_voice_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn([
                'ai_enabled',
                'ai_hub_url',
                'ai_api_key',
                'ai_voice_enabled',
                'ai_voice_name',
            ]);
        });
    }
};
