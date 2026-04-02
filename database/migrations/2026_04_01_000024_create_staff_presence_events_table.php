<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_presence_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_attendance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('event_time');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_presence_events');
    }
};
