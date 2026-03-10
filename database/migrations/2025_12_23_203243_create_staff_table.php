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
        Schema::create('staff', function (Blueprint $table) {
            $table->id();

            // 1. Identity
            $table->string('name');
            $table->string('mobile');
            $table->text('address')->nullable();

            // 2. Job Role
            $table->string('designation'); // Manager, Salesman, Accountant, Helper
            $table->date('joining_date');
            $table->boolean('is_active')->default(true); // Don't delete staff, just deactivate them

            // 3. Money
            $table->decimal('salary_amount', 10, 2)->default(0); // Fixed Monthly Salary

            // 4. Authentication (Optional: If they login to this software)
            // You can link this to the 'users' table if they need login access.
            $table->foreignId('user_id')->nullable()->constrained();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
