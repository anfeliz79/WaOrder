<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\MenuItem;
use App\Models\MenuCategory;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Subscription\PlanEnforcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private PlanEnforcement $enforcement;
    private Tenant $tenant;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enforcement = new PlanEnforcement();

        $this->plan = Plan::create([
            'name' => 'Test Plan',
            'slug' => 'test',
            'price_monthly' => 100,
            'max_branches' => 2,
            'max_menu_items' => 5,
            'max_drivers' => 3,
            'max_users' => 2,
            'max_orders_per_month' => 10,
            'whatsapp_bot_enabled' => true,
            'ai_enabled' => false,
            'external_menu_enabled' => true,
            'custom_domain' => false,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $this->tenant = Tenant::withoutGlobalScope('tenant')->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
            'plan_id' => $this->plan->id,
        ]);

        Subscription::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'plan_id' => $this->plan->id,
            'status' => 'active',
            'billing_period' => 'monthly',
            'price' => 100,
            'current_period_start' => now(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Bind tenant so BelongsToTenant scope works
        app()->instance('tenant', $this->tenant);
    }

    public function test_allows_creating_branch_under_limit(): void
    {
        Branch::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch 1',
            'address' => 'Addr 1',
            'max_delivery_distance_km' => 5,
        ]);

        $result = $this->enforcement->canCreateBranch($this->tenant);
        $this->assertTrue($result);
    }

    public function test_denies_creating_branch_at_limit(): void
    {
        for ($i = 1; $i <= 2; $i++) {
            Branch::withoutGlobalScope('tenant')->create([
                'tenant_id' => $this->tenant->id,
                'name' => "Branch $i",
                'address' => "Addr $i",
                'max_delivery_distance_km' => 5,
            ]);
        }

        $result = $this->enforcement->canCreateBranch($this->tenant);
        $this->assertIsString($result);
        $this->assertStringContainsString('sucursales', $result);
    }

    public function test_allows_creating_driver_under_limit(): void
    {
        $result = $this->enforcement->canCreateDriver($this->tenant);
        $this->assertTrue($result);
    }

    public function test_denies_creating_driver_at_limit(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            Driver::withoutGlobalScope('tenant')->create([
                'tenant_id' => $this->tenant->id,
                'name' => "Driver $i",
                'phone' => "809000000$i",
            ]);
        }

        $result = $this->enforcement->canCreateDriver($this->tenant);
        $this->assertIsString($result);
        $this->assertStringContainsString('repartidores', $result);
    }

    public function test_allows_creating_user_under_limit(): void
    {
        User::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $result = $this->enforcement->canCreateUser($this->tenant);
        $this->assertTrue($result);
    }

    public function test_denies_creating_user_at_limit(): void
    {
        for ($i = 1; $i <= 2; $i++) {
            User::withoutGlobalScope('tenant')->create([
                'tenant_id' => $this->tenant->id,
                'name' => "User $i",
                'email' => "user{$i}@test.com",
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]);
        }

        $result = $this->enforcement->canCreateUser($this->tenant);
        $this->assertIsString($result);
        $this->assertStringContainsString('usuarios', $result);
    }

    public function test_allows_orders_under_monthly_limit(): void
    {
        $result = $this->enforcement->canAcceptOrder($this->tenant);
        $this->assertTrue($result);
    }

    public function test_denies_orders_at_monthly_limit(): void
    {
        $branch = Branch::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch',
            'address' => 'Addr',
            'max_delivery_distance_km' => 5,
        ]);

        $customer = Customer::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '8090001234',
            'name' => 'Test',
        ]);

        for ($i = 1; $i <= 10; $i++) {
            Order::withoutGlobalScope('tenant')->create([
                'tenant_id' => $this->tenant->id,
                'branch_id' => $branch->id,
                'customer_id' => $customer->id,
                'order_number' => "ORD-$i",
                'status' => 'confirmed',
                'customer_name' => 'Test',
                'customer_phone' => '8090001234',
                'delivery_type' => 'delivery',
                'payment_method' => 'cash',
                'subtotal' => 100,
                'delivery_fee' => 0,
                'total' => 100,
            ]);
        }

        $result = $this->enforcement->canAcceptOrder($this->tenant);
        $this->assertIsString($result);
        $this->assertStringContainsString('pedidos', $result);
    }

    public function test_unlimited_when_limit_is_zero(): void
    {
        $this->plan->update(['max_branches' => 0]);

        // Create many branches
        for ($i = 1; $i <= 50; $i++) {
            Branch::withoutGlobalScope('tenant')->create([
                'tenant_id' => $this->tenant->id,
                'name' => "Branch $i",
                'address' => "Addr $i",
                'max_delivery_distance_km' => 5,
            ]);
        }

        $result = $this->enforcement->canCreateBranch($this->tenant);
        $this->assertTrue($result);
    }

    public function test_denies_all_when_no_active_subscription(): void
    {
        // Cancel the subscription
        Subscription::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->update(['status' => 'suspended']);

        $result = $this->enforcement->canCreateBranch($this->tenant);
        $this->assertIsString($result);
        $this->assertStringContainsString('suscripción activa', $result);
    }

    public function test_allows_during_trial(): void
    {
        Subscription::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->update([
                'status' => 'trialing',
                'trial_ends_at' => now()->addDays(14),
            ]);

        $result = $this->enforcement->canCreateBranch($this->tenant);
        $this->assertTrue($result);
    }

    public function test_feature_enabled_check(): void
    {
        $this->assertTrue($this->enforcement->isFeatureEnabled($this->tenant, 'whatsapp_bot_enabled'));
        $this->assertFalse($this->enforcement->isFeatureEnabled($this->tenant, 'ai_enabled'));
        $this->assertTrue($this->enforcement->isFeatureEnabled($this->tenant, 'external_menu_enabled'));
        $this->assertFalse($this->enforcement->isFeatureEnabled($this->tenant, 'custom_domain'));
    }

    public function test_usage_summary_returns_correct_counts(): void
    {
        Branch::withoutGlobalScope('tenant')->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Branch 1',
            'address' => 'Addr 1',
            'max_delivery_distance_km' => 5,
        ]);

        $summary = $this->enforcement->getUsageSummary($this->tenant);

        $this->assertArrayHasKey('branches', $summary);
        $this->assertEquals(1, $summary['branches']['used']);
        $this->assertEquals(2, $summary['branches']['limit']);
    }

    public function test_plan_limits_returns_all_limits_and_features(): void
    {
        $limits = $this->enforcement->getPlanLimits($this->tenant);

        $this->assertEquals('Test Plan', $limits['plan_name']);
        $this->assertEquals(2, $limits['limits']['max_branches']);
        $this->assertEquals(5, $limits['limits']['max_menu_items']);
        $this->assertTrue($limits['features']['whatsapp_bot_enabled']);
        $this->assertFalse($limits['features']['ai_enabled']);
    }
}
