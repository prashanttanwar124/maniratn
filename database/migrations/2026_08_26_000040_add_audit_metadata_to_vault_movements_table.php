<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vault_movements', function (Blueprint $table) {
            $table->string('correlation_id', 120)->nullable()->after('reference')->index();
            $table->char('operation_key', 64)->nullable()->after('correlation_id')->unique();
            $table->decimal('gross_weight', 15, 3)->nullable()->after('amount');
            $table->decimal('fine_weight', 15, 3)->nullable()->after('gross_weight');
            $table->decimal('purity_percent', 7, 4)->nullable()->after('fine_weight');
        });
    }

    public function down(): void
    {
        Schema::table('vault_movements', function (Blueprint $table) {
            $table->dropUnique(['operation_key']);
            $table->dropIndex(['correlation_id']);
            $table->dropColumn([
                'correlation_id',
                'operation_key',
                'gross_weight',
                'fine_weight',
                'purity_percent',
            ]);
        });
    }
};
