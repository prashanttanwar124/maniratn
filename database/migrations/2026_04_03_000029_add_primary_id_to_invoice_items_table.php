<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('invoice_items') || Schema::hasColumn('invoice_items', 'id')) {
            return;
        }

        DB::statement('ALTER TABLE invoice_items ADD COLUMN id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST');
    }

    public function down(): void
    {
        if (! Schema::hasTable('invoice_items') || ! Schema::hasColumn('invoice_items', 'id')) {
            return;
        }

        DB::statement('ALTER TABLE invoice_items DROP PRIMARY KEY, DROP COLUMN id');
    }
};
