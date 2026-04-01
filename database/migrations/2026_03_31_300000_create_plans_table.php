<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->text('description')->nullable();

            // Pricing
            $table->decimal('price_monthly', 10, 2)->default(0);
            $table->decimal('price_annual', 10, 2)->nullable();
            $table->unsignedInteger('trial_days')->default(0);
            $table->string('currency', 3)->default('DOP');

            // Limits
            $table->unsignedInteger('max_branches')->default(1);
            $table->unsignedInteger('max_menu_items')->default(50);
            $table->unsignedInteger('max_drivers')->default(3);
            $table->unsignedInteger('max_orders_per_month')->default(100);
            $table->unsignedInteger('max_users')->default(2);

            // Feature flags
            $table->boolean('whatsapp_bot_enabled')->default(true);
            $table->boolean('ai_enabled')->default(false);
            $table->boolean('external_menu_enabled')->default(false);
            $table->boolean('custom_domain')->default(false);

            // Add-ons
            $table->boolean('support_addon_available')->default(false);
            $table->decimal('support_addon_price', 10, 2)->default(0);
            $table->boolean('delivery_app_addon_available')->default(false);
            $table->decimal('delivery_app_addon_price', 10, 2)->default(0);

            // Meta
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
