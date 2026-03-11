<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->decimal('expected_opening_cash', 15, 2)->nullable()->after('opening_gold');
            $table->decimal('expected_opening_gold', 15, 3)->nullable()->after('expected_opening_cash');
            $table->text('opening_mismatch_reason')->nullable()->after('expected_opening_gold');
        });
    }

    public function down(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->dropColumn([
                'expected_opening_cash',
                'expected_opening_gold',
                'opening_mismatch_reason',
            ]);
        });
    }
};
