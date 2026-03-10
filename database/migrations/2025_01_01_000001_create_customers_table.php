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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();

            // 1. Basic Info
            $table->string('name');
            $table->string('mobile')->unique(); // Vital for WhatsApp/SMS Bills
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->default('Virar'); // Default to your local area

            // 2. KYC (Critical for Jewellery Bills > ₹2 Lakhs)
            $table->string('pan_no')->nullable();      // Income Tax Requirement
            $table->string('aadhaar_no')->nullable();  // Address Proof

            // 3. Marketing (The "Gold Mine")
            $table->date('dob')->nullable();           // Send Birthday Offer
            $table->date('anniversary_date')->nullable(); // Send Anniversary Offer

            // 4. Loyalty
            $table->string('membership_id')->nullable(); // If you have a scheme

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
