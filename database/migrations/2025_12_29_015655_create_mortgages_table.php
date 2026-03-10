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
        Schema::create('mortgages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->onDelete('cascade');

            $table->string('item_name');            // e.g., "Gold Ring"
            $table->decimal('gross_weight', 8, 3);  // e.g., 10.500 gm
            $table->decimal('net_weight', 8, 3)->nullable();

            $table->decimal('loan_amount', 10, 2);  // ₹ 50,000
            $table->decimal('interest_rate', 5, 2); // e.g., 2.00% pm

            $table->string('image_path')->nullable(); // 📸 FIELD FOR PHOTO

            $table->date('start_date');
            $table->date('end_date')->nullable();   // Date when they took it back
            $table->enum('status', ['ACTIVE', 'RELEASED'])->default('ACTIVE');

            $table->text('notes')->nullable();      // Bag No / Location
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortgages');
    }
};
