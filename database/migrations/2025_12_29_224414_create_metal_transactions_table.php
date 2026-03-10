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
        Schema::create('metal_transactions', function (Blueprint $table) {
            $table->id();
            $table->morphs('party'); // Links to Customer, Karigar, or Supplier
            $table->enum('type', ['ISSUE', 'RECEIPT']);
            $table->decimal('gross_weight', 10, 3);
            $table->decimal('fine_weight', 10, 3);
            $table->date('date');
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_transactions');
    }
};
