<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_drafts', function (Blueprint $table) {
            $table->string('source_type', 30)->nullable()->after('user_id');
            $table->string('source_reference', 120)->nullable()->after('source_type');
            $table->unique(['user_id', 'source_type', 'source_reference'], 'invoice_drafts_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_drafts', function (Blueprint $table) {
            $table->dropUnique('invoice_drafts_source_unique');
            $table->dropColumn(['source_type', 'source_reference']);
        });
    }
};
