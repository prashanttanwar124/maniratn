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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // ORD-1001 (Covers all items)
            $table->foreignId('customer_id')->constrained();
            $table->date('due_date'); // When the whole bundle is needed
            $table->text('notes')->nullable();

            // Link to Billing (The whole order gets billed together)
            $table->foreignId('invoice_id')->nullable()->constrained();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
