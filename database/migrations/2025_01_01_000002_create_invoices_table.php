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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique(); // e.g., "INV-2025-001"

            // Link to Customer
            $table->foreignId('customer_id')->constrained();

            // Financials
            $table->decimal('gold_rate_applied', 10, 2); // Rate on that day (e.g., ₹7200/g)
            $table->decimal('total_amount', 15, 2);      // Final Bill Amount
            $table->decimal('tax_amount', 10, 2)->default(0); // GST (3%)

            $table->date('date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
