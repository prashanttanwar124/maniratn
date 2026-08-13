<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('counters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('counter_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });

        Schema::table('silver_products', function (Blueprint $table) {
            $table->foreignId('counter_id')->nullable()->after('supplier_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('silver_products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_id');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('counter_id');
        });

        Schema::dropIfExists('counters');
    }
};
