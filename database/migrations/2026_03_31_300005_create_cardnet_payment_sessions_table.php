<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardnet_payment_sessions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('session_id', 200)->nullable();
            $table->string('session_key', 200)->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('DOP');
            $table->enum('status', ['pending', 'approved', 'rejected', 'expired'])->default('pending');
            $table->json('cardnet_response')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardnet_payment_sessions');
    }
};
