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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category')->default('GENERAL'); // CUSTOMER_FOLLOWUP, KARIGAR_WORKSHOP, INVENTORY_AUDIT, BILLING_FINANCE, MAINTENANCE, GENERAL
            $table->string('priority')->default('MEDIUM'); // LOW, MEDIUM, HIGH, URGENT
            $table->string('status')->default('TODO'); // TODO, IN_PROGRESS, COMPLETED, CANCELLED
            $table->date('due_date')->nullable();
            $table->time('due_time')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('checklist')->nullable(); // [{ id: string, text: string, is_completed: bool }]
            $table->boolean('is_pinned')->default(false);
            $table->text('handover_notes')->nullable();
            $table->string('related_type')->nullable(); // customer, order, karigar, invoice, product
            $table->unsignedBigInteger('related_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index('assigned_to');
            $table->index('category');
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
