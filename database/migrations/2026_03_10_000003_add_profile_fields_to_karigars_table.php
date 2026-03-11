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
        Schema::table('karigars', function (Blueprint $table) {
            $table->string('work_type')->nullable()->after('mobile');
            $table->string('city')->nullable()->after('work_type');
            $table->text('notes')->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('karigars', function (Blueprint $table) {
            $table->dropColumn(['work_type', 'city', 'notes']);
        });
    }
};
