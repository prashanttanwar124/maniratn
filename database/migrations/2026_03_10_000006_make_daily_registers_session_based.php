<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->dropUnique(['date']);
            $table->unsignedInteger('session_number')->default(1)->after('date');
            $table->text('reopen_reason')->nullable()->after('opening_mismatch_reason');
            $table->foreignId('reopened_from_id')->nullable()->after('reopen_reason')->constrained('daily_registers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reopened_from_id');
            $table->dropColumn(['session_number', 'reopen_reason']);
            $table->unique('date');
        });
    }
};
