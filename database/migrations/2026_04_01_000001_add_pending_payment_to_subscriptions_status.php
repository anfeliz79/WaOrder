<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('trialing','active','past_due','cancelled','suspended','expired','pending_payment') NOT NULL DEFAULT 'trialing'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE subscriptions MODIFY COLUMN status ENUM('trialing','active','past_due','cancelled','suspended','expired') NOT NULL DEFAULT 'trialing'");
    }
};
