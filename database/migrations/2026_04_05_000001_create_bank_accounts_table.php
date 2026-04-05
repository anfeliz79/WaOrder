<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bank_name');                          // Banco Popular, BHD León, etc.
            $table->string('account_holder_name');                // Titular de la cuenta
            $table->string('account_number');                     // Número de cuenta
            $table->enum('account_type', ['savings', 'checking'])->default('savings');
            $table->string('currency', 10)->default('DOP');
            $table->text('instructions')->nullable();             // Instrucciones adicionales al usuario
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
};
