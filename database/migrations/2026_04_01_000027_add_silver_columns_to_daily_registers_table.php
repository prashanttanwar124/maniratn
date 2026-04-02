<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->decimal('opening_silver', 15, 3)->default(0)->after('opening_gold');
            $table->decimal('expected_opening_silver', 15, 3)->nullable()->after('expected_opening_gold');
            $table->decimal('closing_silver', 15, 3)->nullable()->after('closing_gold');
            $table->decimal('difference_silver', 15, 3)->default(0)->after('difference_gold');
        });
    }

    public function down(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->dropColumn([
                'opening_silver',
                'expected_opening_silver',
                'closing_silver',
                'difference_silver',
            ]);
        });
    }
};
