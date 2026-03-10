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
        Schema::create('daily_registers', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('opening_cash', 15, 2);
            $table->decimal('opening_gold', 15, 3);
            $table->foreignId('opened_by')->constrained('users');

            // 
            $table->decimal('closing_cash', 15, 2)->nullable();
            $table->decimal('closing_gold', 15, 3)->nullable();
            $table->decimal('difference_cash', 15, 2)->default(0); // Shortage/Excess
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('daily_registers');
    }
};
