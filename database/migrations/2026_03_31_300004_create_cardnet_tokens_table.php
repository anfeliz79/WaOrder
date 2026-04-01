<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cardnet_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('cardnet_customer_id', 100);
            $table->text('trx_token'); // encrypted via model cast
            $table->string('card_brand', 20)->nullable();
            $table->string('card_last_four', 4)->nullable();
            $table->string('card_expiry', 7)->nullable();
            $table->boolean('is_default')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cardnet_tokens');
    }
};
