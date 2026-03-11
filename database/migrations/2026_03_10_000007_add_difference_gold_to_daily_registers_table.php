<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->decimal('difference_gold', 15, 3)->default(0)->after('difference_cash');
        });
    }

    public function down(): void
    {
        Schema::table('daily_registers', function (Blueprint $table) {
            $table->dropColumn('difference_gold');
        });
    }
};
