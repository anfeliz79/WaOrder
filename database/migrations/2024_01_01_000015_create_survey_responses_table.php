<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('survey_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('customer_phone', 20);
            $table->integer('rating')->nullable(); // 1-5 stars
            $table->string('food_quality')->nullable(); // excellent, good, regular, bad
            $table->string('delivery_speed')->nullable(); // fast, normal, slow
            $table->text('comment')->nullable(); // free text comment
            $table->boolean('completed')->default(false);
            $table->timestamps();

            $table->unique('order_id');
            $table->index(['tenant_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('survey_responses');
    }
};
