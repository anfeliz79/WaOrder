<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::updateOrCreate(['slug' => 'cardnet'], [
            'name'        => 'Tarjeta de Credito/Debito',
            'description' => 'Pago con tarjeta via Cardnet',
            'icon'        => 'CreditCard',
            'is_active'   => false,
            'sort_order'  => 1,
        ]);

        PaymentMethod::updateOrCreate(['slug' => 'bank_transfer'], [
            'name'        => 'Transferencia Bancaria',
            'description' => 'Pago por transferencia con verificacion manual',
            'icon'        => 'Building2',
            'is_active'   => true,
            'sort_order'  => 2,
        ]);

        PaymentMethod::updateOrCreate(['slug' => 'paypal'], [
            'name'        => 'PayPal',
            'description' => 'Suscripcion automatica via PayPal',
            'icon'        => 'Wallet',
            'is_active'   => false,
            'sort_order'  => 3,
        ]);
    }
}
