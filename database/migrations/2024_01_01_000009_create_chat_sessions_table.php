<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 20);
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('conversation_state', 50)->default('greeting');
            $table->json('cart_data')->nullable();
            $table->json('collected_info')->nullable();
            $table->json('context_data')->nullable();
            $table->foreignId('active_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->enum('status', ['active', 'expired', 'completed'])->default('active');
            $table->integer('message_count')->default(0);
            $table->timestamps();
            $table->timestamp('expires_at');
            $table->index(['tenant_id', 'customer_phone', 'status']);
            $table->index(['expires_at', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
