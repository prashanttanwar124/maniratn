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
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();

            // 1. Identity
            $table->string('company_name'); // e.g., "Raj Gold House"
            $table->string('contact_person'); // e.g., "Rajesh Bhai"
            $table->string('mobile');

            // 2. Taxation (Critical for Input Tax Credit)
            $table->string('gst_number')->nullable(); // MANDATORY for B2B
            $table->string('pan_no')->nullable();

            // 3. Bank Details (For NEFT/RTGS Payouts)
            $table->string('bank_name')->nullable();
            $table->string('account_no')->nullable();
            $table->string('ifsc_code')->nullable();

            // 4. Category
            $table->string('type')->default('GOLD'); // GOLD, DIAMOND, PACKAGING, SILVER

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
