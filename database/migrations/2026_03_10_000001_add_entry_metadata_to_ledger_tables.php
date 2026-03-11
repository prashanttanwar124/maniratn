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
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('entry_source')->default('SYSTEM')->after('invoice_id');
            $table->string('entry_type_code')->nullable()->after('entry_source');
        });

        Schema::table('metal_transactions', function (Blueprint $table) {
            $table->string('entry_source')->default('SYSTEM')->after('description');
            $table->string('entry_type_code')->nullable()->after('entry_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['entry_source', 'entry_type_code']);
        });

        Schema::table('metal_transactions', function (Blueprint $table) {
            $table->dropColumn(['entry_source', 'entry_type_code']);
        });
    }
};
