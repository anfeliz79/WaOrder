<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('payment_method', 50)->nullable()->after('price');
            $table->string('paypal_subscription_id', 100)->nullable()->after('payment_method');
            $table->string('paypal_plan_id', 100)->nullable()->after('paypal_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'paypal_subscription_id', 'paypal_plan_id']);
        });
    }
};
