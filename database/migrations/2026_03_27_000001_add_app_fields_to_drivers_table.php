<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->string('push_token')->nullable()->after('completed_deliveries');
            $table->string('device_platform', 10)->nullable()->after('push_token');
            $table->string('linking_token', 64)->nullable()->unique()->after('device_platform');
            $table->timestamp('linking_token_expires_at')->nullable()->after('linking_token');
            $table->timestamp('linked_at')->nullable()->after('linking_token_expires_at');
            $table->boolean('app_linked')->default(false)->after('linked_at');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn([
                'push_token', 'device_platform', 'linking_token',
                'linking_token_expires_at', 'linked_at', 'app_linked',
            ]);
        });
    }
};
