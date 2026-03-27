<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 100)->unique();
            $table->string('whatsapp_phone_number_id', 50);
            $table->string('whatsapp_business_account_id', 50);
            $table->text('whatsapp_access_token'); // encrypted
            $table->string('timezone', 50)->default('America/Santo_Domingo');
            $table->string('currency', 3)->default('DOP');
            $table->string('locale', 5)->default('es');
            $table->json('settings')->nullable(); // delivery_fee, min_order, hours, menu_source, etc.
            $table->enum('subscription_plan', ['free', 'starter', 'pro'])->default('free');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('whatsapp_phone_number_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};
