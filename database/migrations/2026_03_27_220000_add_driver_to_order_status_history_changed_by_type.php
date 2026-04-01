<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        DB::statement("ALTER TABLE order_status_history MODIFY COLUMN changed_by_type ENUM('system', 'staff', 'customer', 'driver') NOT NULL DEFAULT 'system'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') return;
        DB::statement("ALTER TABLE order_status_history MODIFY COLUMN changed_by_type ENUM('system', 'staff', 'customer') NOT NULL DEFAULT 'system'");
    }
};
