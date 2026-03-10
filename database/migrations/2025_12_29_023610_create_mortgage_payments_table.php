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
        Schema::create('mortgage_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mortgage_id')->constrained()->onDelete('cascade');

            $table->decimal('amount', 10, 2);

            // INTEREST = Paying monthly charge (Loan amount stays same)
            // PRINCIPAL = Paying back the loan (Loan amount reduces)
            $table->enum('type', ['INTEREST', 'PRINCIPAL']);

            $table->date('date');
            $table->string('note')->nullable(); // "Paid for Jan & Feb"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mortgage_payments');
    }
};
