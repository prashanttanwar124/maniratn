<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('metal_type', 10)->default('GOLD')->after('code');
        });

        DB::table('categories')
            ->where(function ($query) {
                $query->where('name', 'like', 'Silver %')
                    ->orWhere('code', 'like', 'S%');
            })
            ->update(['metal_type' => 'SILVER']);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('metal_type');
        });
    }
};
