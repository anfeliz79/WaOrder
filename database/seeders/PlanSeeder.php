<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Free',
                'slug' => 'free',
                'description' => 'Ideal para probar la plataforma. Incluye funcionalidades basicas para empezar.',
                'price_monthly' => 0,
                'price_annual' => null,
                'trial_days' => 0,
                'max_branches' => 1,
                'max_menu_items' => 20,
                'max_drivers' => 1,
                'max_orders_per_month' => 50,
                'max_users' => 1,
                'whatsapp_bot_enabled' => true,
                'ai_enabled' => false,
                'external_menu_enabled' => false,
                'custom_domain' => false,
                'support_addon_available' => false,
                'support_addon_price' => 0,
                'delivery_app_addon_available' => false,
                'delivery_app_addon_price' => 0,
                'is_active' => true,
                'sort_order' => 0,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Para restaurantes en crecimiento. Mas capacidad y funcionalidades avanzadas.',
                'price_monthly' => 1500,
                'price_annual' => 15000,
                'trial_days' => 0,
                'max_branches' => 3,
                'max_menu_items' => 100,
                'max_drivers' => 5,
                'max_orders_per_month' => 500,
                'max_users' => 5,
                'whatsapp_bot_enabled' => true,
                'ai_enabled' => true,
                'external_menu_enabled' => false,
                'custom_domain' => false,
                'support_addon_available' => true,
                'support_addon_price' => 500,
                'delivery_app_addon_available' => true,
                'delivery_app_addon_price' => 800,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Para operaciones grandes. Sin limites practicos, todas las funcionalidades.',
                'price_monthly' => 3500,
                'price_annual' => 35000,
                'trial_days' => 0,
                'max_branches' => 10,
                'max_menu_items' => 500,
                'max_drivers' => 20,
                'max_orders_per_month' => 5000,
                'max_users' => 15,
                'whatsapp_bot_enabled' => true,
                'ai_enabled' => true,
                'external_menu_enabled' => true,
                'custom_domain' => true,
                'support_addon_available' => true,
                'support_addon_price' => 500,
                'delivery_app_addon_available' => true,
                'delivery_app_addon_price' => 500,
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(
                ['slug' => $plan['slug']],
                $plan
            );
        }
    }
}
