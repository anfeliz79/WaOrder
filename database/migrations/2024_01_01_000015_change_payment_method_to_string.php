<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support MODIFY COLUMN; column is already string-like in SQLite
            return;
        }
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method VARCHAR(50) NOT NULL DEFAULT 'cash'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash','transfer','card') NOT NULL DEFAULT 'cash'");
    }
};
