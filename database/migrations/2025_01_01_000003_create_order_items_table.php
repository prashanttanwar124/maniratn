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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // LINK TO PARENT
            $table->foreignId('order_id')->constrained()->onDelete('cascade');

            // ITEM DETAILS
            $table->string('item_name'); // "Gold Chain"
            $table->decimal('target_weight', 10, 3);
            $table->decimal('purity', 5, 2)->default(91.60); // 22k for chain, 18k for others
            $table->text('notes')->nullable();
            // STATUS & ASSIGNMENT (Moved here so you can track items individually)
            $table->enum('status', ['NEW', 'ASSIGNED', 'READY', 'DELIVERED'])->default('NEW');
            $table->nullableMorphs('assignee'); // Karigar A for Item 1, Karigar B for Item 2

            // PRODUCTION RESULT
            $table->decimal('finished_weight', 10, 3)->nullable();
            $table->decimal('wastage', 10, 3)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
