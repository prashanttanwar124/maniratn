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
        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'making_charge_type')) {
                $table->enum('making_charge_type', ['percentage', 'flat', 'per_gram'])
                    ->default('percentage')
                    ->after('making_charge');
            }
        });

        Schema::table('silver_products', function (Blueprint $table) {
            if (! Schema::hasColumn('silver_products', 'making_charge_type')) {
                $table->enum('making_charge_type', ['percentage', 'flat', 'per_gram'])
                    ->default('per_gram')
                    ->after('making_charge');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'making_charge_type')) {
                $table->dropColumn('making_charge_type');
            }
        });

        Schema::table('silver_products', function (Blueprint $table) {
            if (Schema::hasColumn('silver_products', 'making_charge_type')) {
                $table->dropColumn('making_charge_type');
            }
        });
    }
};
