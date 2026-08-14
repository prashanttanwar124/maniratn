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
        Schema::table('customers', function (Blueprint $table) {
            $table->string('vault_token', 64)->nullable()->unique()->after('membership_id');
            $table->string('nfc_card_uid', 100)->nullable()->index()->after('vault_token');
            $table->string('card_status', 20)->default('NOT_ISSUED')->after('nfc_card_uid');
            $table->timestamp('card_issued_at')->nullable()->after('card_status');
            $table->timestamp('card_written_at')->nullable()->after('card_issued_at');
            $table->timestamp('card_locked_at')->nullable()->after('card_written_at');
            $table->timestamp('card_last_accessed_at')->nullable()->after('card_locked_at');
            $table->unsignedInteger('card_access_count')->default(0)->after('card_last_accessed_at');
            $table->string('card_pin', 60)->nullable()->after('card_access_count');
            $table->text('card_notes')->nullable()->after('card_pin');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'vault_token',
                'nfc_card_uid',
                'card_status',
                'card_issued_at',
                'card_written_at',
                'card_locked_at',
                'card_last_accessed_at',
                'card_access_count',
                'card_pin',
                'card_notes',
            ]);
        });
    }
};
