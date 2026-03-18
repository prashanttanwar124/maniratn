<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_gold_schemes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme_number')->unique();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('maturity_date');
            $table->enum('status', ['ACTIVE', 'MATURED', 'REDEEMED', 'DEFAULTED', 'CANCELLED'])->default('ACTIVE');
            $table->decimal('monthly_amount', 15, 2);
            $table->unsignedInteger('total_months');
            $table->decimal('bonus_amount', 15, 2)->default(0);
            $table->decimal('paid_total', 15, 2)->default(0);
            $table->unsignedInteger('paid_installments_count')->default(0);
            $table->timestamp('bonus_applied_at')->nullable();
            $table->timestamp('redeemed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_gold_schemes');
    }
};
