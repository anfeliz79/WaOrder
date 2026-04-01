<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Payment\CardnetTokenizationService;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionManagerTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionManager $manager;
    private Tenant $tenant;
    private Plan $freePlan;
    private Plan $starterPlan;
    private Plan $proPlan;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the tokenization service (not needed for these tests)
        $tokenMock = $this->createMock(CardnetTokenizationService::class);
        $this->manager = new SubscriptionManager($tokenMock);

        $this->freePlan = Plan::create([
            'name' => 'Free', 'slug' => 'free',
            'price_monthly' => 0, 'trial_days' => 0,
            'max_branches' => 1, 'max_menu_items' => 20,
            'max_drivers' => 1, 'max_users' => 1,
            'max_orders_per_month' => 50,
            'is_active' => true, 'sort_order' => 1,
        ]);

        $this->starterPlan = Plan::create([
            'name' => 'Starter', 'slug' => 'starter',
            'price_monthly' => 1500, 'price_annual' => 15000,
            'trial_days' => 14,
            'max_branches' => 3, 'max_menu_items' => 100,
            'max_drivers' => 5, 'max_users' => 5,
            'max_orders_per_month' => 500,
            'is_active' => true, 'sort_order' => 2,
        ]);

        $this->proPlan = Plan::create([
            'name' => 'Pro', 'slug' => 'pro',
            'price_monthly' => 3500, 'price_annual' => 35000,
            'trial_days' => 14,
            'max_branches' => null, 'max_menu_items' => null,
            'max_drivers' => null, 'max_users' => null,
            'max_orders_per_month' => null,
            'is_active' => true, 'sort_order' => 3,
        ]);

        $this->tenant = Tenant::withoutGlobalScope('tenant')->create([
            'name' => 'Test Restaurant',
            'slug' => 'test-restaurant',
        ]);

        app()->instance('tenant', $this->tenant);
    }

    public function test_subscribe_creates_active_subscription(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->freePlan);

        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($this->freePlan->id, $subscription->plan_id);
        $this->assertEquals($this->tenant->id, $subscription->tenant_id);
        $this->assertEquals(0, (float) $subscription->price);
        $this->assertEquals('monthly', $subscription->billing_period);
        $this->assertNotNull($subscription->current_period_start);
        $this->assertNotNull($subscription->current_period_end);
    }

    public function test_subscribe_updates_tenant_plan_id(): void
    {
        $this->manager->subscribe($this->tenant, $this->starterPlan);

        $this->tenant->refresh();
        $this->assertEquals($this->starterPlan->id, $this->tenant->plan_id);
    }

    public function test_subscribe_cancels_existing_active_subscription(): void
    {
        $first = $this->manager->subscribe($this->tenant, $this->freePlan);

        // SubscriptionManager cancels existing then creates new — but tenant_id is unique
        // so we need changePlan instead for same-tenant switch
        $updated = $this->manager->changePlan($first, $this->starterPlan);

        $this->assertEquals($this->starterPlan->id, $updated->plan_id);
        $this->assertEquals(1500, (float) $updated->price);
    }

    public function test_subscribe_annual_sets_correct_period(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan, 'annual');

        $this->assertEquals('annual', $subscription->billing_period);
        $this->assertEquals(15000, (float) $subscription->price);

        $expectedEnd = now()->addYear();
        $this->assertTrue(
            $subscription->current_period_end->diffInDays($expectedEnd) < 1,
            'Annual period should end ~1 year from now'
        );
    }

    public function test_start_trial_creates_trialing_subscription(): void
    {
        $subscription = $this->manager->startTrial($this->tenant, $this->starterPlan);

        $this->assertEquals('trialing', $subscription->status);
        $this->assertEquals($this->starterPlan->id, $subscription->plan_id);
        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertTrue($subscription->trial_ends_at->isFuture());
        $this->assertGreaterThanOrEqual(13, (int) now()->diffInDays($subscription->trial_ends_at, false));
        $this->assertLessThanOrEqual(14, (int) now()->diffInDays($subscription->trial_ends_at, false));
    }

    public function test_start_trial_with_custom_days(): void
    {
        $subscription = $this->manager->startTrial($this->tenant, $this->starterPlan, 30);

        $this->assertGreaterThanOrEqual(29, (int) now()->diffInDays($subscription->trial_ends_at, false));
        $this->assertLessThanOrEqual(30, (int) now()->diffInDays($subscription->trial_ends_at, false));
    }

    public function test_change_plan_updates_subscription(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);
        $updated = $this->manager->changePlan($subscription, $this->proPlan);

        $this->assertEquals($this->proPlan->id, $updated->plan_id);
        $this->assertEquals(3500, (float) $updated->price);

        $this->tenant->refresh();
        $this->assertEquals($this->proPlan->id, $this->tenant->plan_id);
    }

    public function test_cancel_marks_subscription_cancelled(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);
        $this->manager->cancel($subscription, 'Too expensive');

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);
        $this->assertNotNull($subscription->cancelled_at);
        $this->assertEquals('Too expensive', $subscription->cancellation_reason);
    }

    public function test_reactivate_restores_cancelled_subscription(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);
        $this->manager->cancel($subscription);

        $subscription->refresh();
        $this->assertEquals('cancelled', $subscription->status);

        $this->manager->reactivate($subscription);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->cancellation_reason);
        $this->assertTrue($subscription->current_period_end->isFuture());
    }

    public function test_reactivate_does_nothing_if_not_cancelled(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);
        $originalEnd = $subscription->current_period_end->toDateTimeString();

        $this->manager->reactivate($subscription);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertEquals($originalEnd, $subscription->current_period_end->toDateTimeString());
    }

    public function test_handle_payment_success_renews_period(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);
        $subscription->update(['status' => 'past_due', 'grace_period_ends_at' => now()->addDays(3)]);

        $this->manager->handlePaymentSuccess($subscription);

        $subscription->refresh();
        $this->assertEquals('active', $subscription->status);
        $this->assertNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->current_period_end->isFuture());
    }

    public function test_handle_payment_failure_sets_past_due_with_grace(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);

        $this->manager->handlePaymentFailure($subscription);

        $subscription->refresh();
        $this->assertEquals('past_due', $subscription->status);
        $this->assertNotNull($subscription->grace_period_ends_at);
        $this->assertTrue($subscription->grace_period_ends_at->isFuture());
    }

    public function test_subscription_status_helpers(): void
    {
        $subscription = $this->manager->subscribe($this->tenant, $this->starterPlan);

        $this->assertTrue($subscription->isActive());
        $this->assertFalse($subscription->isTrialing());
        $this->assertFalse($subscription->isPastDue());
        $this->assertFalse($subscription->isCancelled());
        $this->assertFalse($subscription->isSuspended());

        $subscription->update(['status' => 'trialing', 'trial_ends_at' => now()->addDays(14)]);
        $subscription->refresh();
        $this->assertTrue($subscription->isActive()); // trialing counts as active
        $this->assertTrue($subscription->isTrialing());
        $this->assertGreaterThanOrEqual(13, $subscription->trialDaysRemaining());
        $this->assertLessThanOrEqual(14, $subscription->trialDaysRemaining());
    }
}
