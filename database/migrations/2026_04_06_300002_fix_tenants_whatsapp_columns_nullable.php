<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('whatsapp_phone_number_id', 50)->nullable()->change();
            $table->string('whatsapp_business_account_id', 50)->nullable()->change();
            $table->text('whatsapp_access_token')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Not reversible — columns should stay nullable
    }
};
