<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing roles to new values
        DB::table('users')->where('role', 'owner')->update(['role' => 'admin']);
        DB::table('users')->where('role', 'staff')->update(['role' => 'gestor']);

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'gestor') NOT NULL DEFAULT 'gestor'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('owner', 'admin', 'staff') NOT NULL DEFAULT 'staff'");
        }
        DB::table('users')->where('role', 'admin')->update(['role' => 'owner']);
        DB::table('users')->where('role', 'gestor')->update(['role' => 'staff']);
    }
};
