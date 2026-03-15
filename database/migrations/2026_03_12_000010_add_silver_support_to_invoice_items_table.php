<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->foreignId('silver_product_id')->nullable()->after('product_id')->constrained('silver_products');
            $table->unsignedInteger('quantity')->default(1)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('silver_product_id');
            $table->dropColumn('quantity');
        });
    }
};
