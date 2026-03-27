<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE message_log MODIFY COLUMN message_type VARCHAR(30) NOT NULL DEFAULT 'text'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE message_log MODIFY COLUMN message_type ENUM('text','image','interactive','template','location') NOT NULL DEFAULT 'text'");
    }
};
