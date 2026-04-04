<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('verification_tags', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique();
            $table->string('tag_type', 20)->default('NFC');
            $table->string('status', 20)->default('PENDING');
            $table->boolean('is_active')->default(true);

            $table->foreignId('invoice_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('silver_product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('written_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('written_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->unsignedInteger('verified_count')->default(0);

            $table->string('public_url')->nullable();
            $table->string('nfc_uid')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verification_tags');
    }
};
