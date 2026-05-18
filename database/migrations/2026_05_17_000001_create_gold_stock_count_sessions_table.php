<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gold_stock_count_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_register_id')->constrained()->cascadeOnDelete();
            $table->date('count_date');
            $table->string('status')->default('OPEN');
            $table->foreignId('started_by')->constrained('users');
            $table->timestamp('started_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique('daily_register_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gold_stock_count_sessions');
    }
};
