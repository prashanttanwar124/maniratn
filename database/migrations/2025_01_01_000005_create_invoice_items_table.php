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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();

            // 1. LINK TO THE BILL (Crucial!)
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            // 2. LINK TO THE ITEM (Hybrid Logic)
            // It can be a Stock Product...
            $table->foreignId('product_id')->nullable()->constrained();
            // ...OR a Custom Order (We make this nullable so we can fill one or the other)
            $table->foreignId('order_item_id')->nullable()->constrained();
            $table->string('purity'); // 22k for chain, 18k for others

            // 3. BILLING DETAILS (Locked at moment of sale)
            $table->string('description')->nullable(); // e.g. "Gold Ring" or "Custom Order #1001"
            $table->decimal('weight', 10, 3);          // Weight sold
            $table->decimal('rate', 10, 2)->nullable(); // Gold Rate applied
            $table->decimal('making_charges', 10, 2)->default(0);
            $table->decimal('final_price', 15, 2);     // The final amount for this line item

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
