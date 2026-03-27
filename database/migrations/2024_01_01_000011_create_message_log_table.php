<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('session_id')->nullable();
            $table->enum('direction', ['inbound', 'outbound']);
            $table->string('customer_phone', 20);
            $table->enum('message_type', ['text', 'image', 'interactive', 'template', 'location'])->default('text');
            $table->text('content')->nullable();
            $table->string('meta_message_id', 100)->nullable();
            $table->boolean('ai_used')->default(false);
            $table->string('ai_model', 50)->nullable();
            $table->integer('ai_tokens_used')->nullable();
            $table->decimal('ai_cost_usd', 8, 6)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['tenant_id', 'customer_phone', 'created_at']);
            $table->index('session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_log');
    }
};
