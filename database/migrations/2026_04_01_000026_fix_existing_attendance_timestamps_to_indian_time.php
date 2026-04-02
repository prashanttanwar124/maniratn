<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("UPDATE staff_attendances SET check_in_at = DATE_ADD(check_in_at, INTERVAL 330 MINUTE) WHERE check_in_at IS NOT NULL");
        DB::statement("UPDATE staff_attendances SET check_out_at = DATE_ADD(check_out_at, INTERVAL 330 MINUTE) WHERE check_out_at IS NOT NULL");
        DB::statement("UPDATE staff_presence_events SET event_time = DATE_ADD(event_time, INTERVAL 330 MINUTE) WHERE event_time IS NOT NULL");
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement("UPDATE staff_attendances SET check_in_at = DATE_SUB(check_in_at, INTERVAL 330 MINUTE) WHERE check_in_at IS NOT NULL");
        DB::statement("UPDATE staff_attendances SET check_out_at = DATE_SUB(check_out_at, INTERVAL 330 MINUTE) WHERE check_out_at IS NOT NULL");
        DB::statement("UPDATE staff_presence_events SET event_time = DATE_SUB(event_time, INTERVAL 330 MINUTE) WHERE event_time IS NOT NULL");
    }
};
