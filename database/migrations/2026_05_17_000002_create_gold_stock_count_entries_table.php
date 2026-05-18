<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_stock_count_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('gold_stock_count_sessions')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('scanned_barcode');
            $table->foreignId('scanned_by')->constrained('users');
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->unique(['session_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_stock_count_entries');
    }
};
