<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);  // WhatsApp number E.164 format
            $table->string('vehicle_type')->nullable(); // moto, carro, bicicleta
            $table->string('vehicle_plate')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(true); // currently available for orders
            $table->integer('completed_deliveries')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'phone']);
            $table->index(['tenant_id', 'is_active', 'is_available']);
        });

        // Add driver_id to orders table
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('driver_id')->nullable()->after('customer_id')->constrained('drivers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['driver_id']);
            $table->dropColumn('driver_id');
        });
        Schema::dropIfExists('drivers');
    }
};
