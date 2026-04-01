<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', ['subscription', 'order_payment', 'addon']);
            $table->enum('status', ['draft', 'pending', 'paid', 'failed', 'refunded', 'void'])->default('draft');
            $table->decimal('amount', 10, 2);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency', 3)->default('DOP');
            $table->text('description')->nullable();
            $table->string('cardnet_purchase_id', 100)->nullable();
            $table->json('cardnet_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('cardnet_purchase_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
