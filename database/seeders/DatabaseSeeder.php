<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed platform-level payment methods
        $this->call(PaymentMethodSeeder::class);

        // Create demo tenant
        $tenant = Tenant::create([
            'name' => 'Pizzeria Don Mario',
            'slug' => 'don-mario',
            'whatsapp_phone_number_id' => 'DEMO_PHONE_ID',
            'whatsapp_business_account_id' => 'DEMO_BUSINESS_ID',
            'whatsapp_access_token' => 'DEMO_TOKEN',
            'timezone' => 'America/Santo_Domingo',
            'currency' => 'DOP',
            'locale' => 'es',
            'settings' => [
                'delivery_fee' => 150,
                'min_order' => 500,
                'estimated_time' => 30,
                'menu_source' => 'internal',
            ],
        ]);

        app()->instance('tenant', $tenant);

        // Create default branch
        $branch = Branch::create([
            'tenant_id' => $tenant->id,
            'name' => 'Sucursal Principal',
            'address' => 'Av. Winston Churchill, Santo Domingo',
            'latitude' => 18.4861,
            'longitude' => -69.9312,
            'phone' => '+1 809 000 0000',
            'max_delivery_distance_km' => 10,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        // Create admin user
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin',
            'email' => 'admin@waorder.com',
            'password' => Hash::make('password'),
            'role' => 'admin',
        ]);

        // Assign admin to branch
        $admin->branches()->attach($branch->id);

        // Create menu
        $pizzas = MenuCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Pizzas',
            'description' => 'Nuestras deliciosas pizzas artesanales',
            'sort_order' => 1,
        ]);

        $bebidas = MenuCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Bebidas',
            'description' => 'Refrescos y jugos',
            'sort_order' => 2,
        ]);

        $hamburguesas = MenuCategory::create([
            'tenant_id' => $tenant->id,
            'name' => 'Hamburguesas',
            'description' => 'Hamburguesas a la parrilla',
            'sort_order' => 3,
        ]);

        // Pizza items
        $pizzaItems = [
            ['name' => 'Pizza Margarita', 'description' => 'Salsa de tomate, mozzarella fresca, albahaca', 'price' => 650],
            ['name' => 'Pizza Pepperoni Mediana', 'description' => 'Pepperoni, mozzarella, salsa', 'price' => 750],
            ['name' => 'Pizza Pepperoni Grande', 'description' => 'Pepperoni, mozzarella, salsa - tamano grande', 'price' => 850, 'modifiers' => [['name' => 'Extra queso', 'price' => 50], ['name' => 'Sin cebolla', 'price' => 0]]],
            ['name' => 'Pizza Hawaiana', 'description' => 'Jamon, pina, mozzarella', 'price' => 800],
            ['name' => 'Pizza Suprema', 'description' => 'Pepperoni, jamon, pimiento, cebolla, aceitunas, champiñones', 'price' => 950],
        ];

        foreach ($pizzaItems as $i => $item) {
            MenuItem::create(array_merge($item, [
                'tenant_id' => $tenant->id,
                'category_id' => $pizzas->id,
                'sort_order' => $i + 1,
            ]));
        }

        // Beverage items
        $beverageItems = [
            ['name' => 'Coca-Cola 2L', 'description' => 'Coca-Cola botella 2 litros', 'price' => 150],
            ['name' => 'Coca-Cola Lata', 'description' => 'Coca-Cola lata 355ml', 'price' => 75],
            ['name' => 'Agua Mineral', 'description' => 'Agua mineral 500ml', 'price' => 50],
            ['name' => 'Jugo de Chinola', 'description' => 'Jugo natural de chinola 16oz', 'price' => 120],
        ];

        foreach ($beverageItems as $i => $item) {
            MenuItem::create(array_merge($item, [
                'tenant_id' => $tenant->id,
                'category_id' => $bebidas->id,
                'sort_order' => $i + 1,
            ]));
        }

        // Hamburger items
        $burgerItems = [
            ['name' => 'Hamburguesa Clasica', 'description' => 'Carne 1/4 lb, lechuga, tomate, queso americano', 'price' => 450],
            ['name' => 'Hamburguesa Doble', 'description' => 'Doble carne, doble queso, bacon', 'price' => 650],
            ['name' => 'Hamburguesa BBQ', 'description' => 'Carne, bacon, cebolla caramelizada, salsa BBQ', 'price' => 550],
        ];

        foreach ($burgerItems as $i => $item) {
            MenuItem::create(array_merge($item, [
                'tenant_id' => $tenant->id,
                'category_id' => $hamburguesas->id,
                'sort_order' => $i + 1,
            ]));
        }
    }
}
