<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_scheme_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_gold_scheme_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('installment_no');
            $table->date('due_date');
            $table->decimal('amount_due', 15, 2);
            $table->decimal('amount_paid', 15, 2)->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_method', 20)->nullable();
            $table->enum('status', ['PENDING', 'PAID', 'LATE', 'CANCELLED'])->default('PENDING');
            $table->text('note')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['customer_gold_scheme_id', 'installment_no'], 'gold_scheme_installments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_scheme_installments');
    }
};
