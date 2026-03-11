<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vault_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vault_id')->constrained()->cascadeOnDelete();
            $table->enum('vault_type', ['GOLD', 'SILVER', 'CASH', 'BANK'])->index();
            $table->enum('direction', ['CREDIT', 'DEBIT']);
            $table->decimal('amount', 15, 3);
            $table->decimal('balance_before', 15, 3);
            $table->decimal('balance_after', 15, 3);
            $table->string('source_type')->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vault_movements');
    }
};
