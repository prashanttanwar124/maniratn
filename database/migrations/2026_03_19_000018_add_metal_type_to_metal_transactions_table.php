<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('metal_transactions', function (Blueprint $table) {
            $table->string('metal_type', 10)->default('GOLD')->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('metal_transactions', function (Blueprint $table) {
            $table->dropColumn('metal_type');
        });
    }
};
