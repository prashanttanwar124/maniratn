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
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('cancellation_mode')->nullable()->after('status');
            $table->text('cancellation_reason')->nullable()->after('cancellation_mode');
            $table->foreignId('cancelled_by')->nullable()->after('cancellation_reason')->constrained('users');
            $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'cancellation_mode',
                'cancellation_reason',
                'cancelled_at',
            ]);
        });
    }
};
