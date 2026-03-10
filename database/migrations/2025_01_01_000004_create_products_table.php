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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('barcode')->unique();
            $table->string('name');

            // RELATIONSHIPS
            $table->foreignId('category_id')->constrained();
            $table->foreignId('purity_id')->constrained();
            $table->foreignId('supplier_id')->constrained();

            // WEIGHTS
            $table->decimal('gross_weight', 10, 3);
            $table->decimal('net_weight', 10, 3);

            $table->decimal('making_charge', 10, 2);
            $table->boolean('is_sold')->default(false);
            $table->string('image_path')->nullable(); // 📸 Added this back for you

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
