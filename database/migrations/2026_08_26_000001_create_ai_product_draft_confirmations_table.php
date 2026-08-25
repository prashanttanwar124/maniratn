<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_draft_confirmations', function (Blueprint $table) {
            $table->id();
            $table->string('message_id', 100);
            $table->string('draft_id', 100);
            $table->unsignedInteger('action_index');
            $table->json('result');
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['message_id', 'draft_id'], 'ai_product_draft_message_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_draft_confirmations');
    }
};
