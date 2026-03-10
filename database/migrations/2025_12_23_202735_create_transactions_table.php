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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();

            // This creates 'transactable_id' and 'transactable_type'
            // It allows this transaction to belong to ANY model (Customer, Supplier, Staff)
            $table->morphs('transactable');

            $table->enum('type', ['SALE', 'PAYMENT', 'PURCHASE', 'RECEIPT', 'VOID']);

            $table->string('payment_method')->default('CASH'); // CASH, UPI, CHEQUE
            $table->string('reference_number')->nullable();

            $table->decimal('amount', 15, 2);
            $table->string('description')->nullable(); // e.g., "Sold Ring", "Salary Paid", "Purchase Invoice #102"
            $table->date('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
