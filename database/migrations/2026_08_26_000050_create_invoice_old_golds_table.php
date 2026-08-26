<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_old_golds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->string('metal_type', 20)->default('GOLD'); // GOLD or SILVER
            $table->string('description')->nullable();
            $table->decimal('gross_weight', 10, 3);
            $table->decimal('wastage_weight', 10, 3)->default(0);
            $table->decimal('net_weight', 10, 3);
            $table->string('purity', 50)->default('22K');
            $table->decimal('rate', 10, 2);
            $table->decimal('final_price', 15, 2);
            $table->timestamps();
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->decimal('old_gold_amount', 15, 2)->default(0)->after('discount_amount');
            $table->decimal('old_gold_weight', 10, 3)->default(0)->after('old_gold_amount');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_old_golds');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['old_gold_amount', 'old_gold_weight']);
        });
    }
};
