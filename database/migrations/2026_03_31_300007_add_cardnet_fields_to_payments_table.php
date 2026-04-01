<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('cardnet_session_id', 200)->nullable()->after('gateway_response');
            $table->string('gateway', 50)->nullable()->after('cardnet_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['cardnet_session_id', 'gateway']);
        });
    }
};
