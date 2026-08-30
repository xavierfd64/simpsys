<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Services\TenantContext;
use App\Support\BillingStatement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingStatementTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSubscriptionWithOwner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['monthly_price' => 599]);

        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now()->subDays(10),
            'current_period_end' => now()->addDays(20),
        ]);

        return [$tenant, $owner, $subscription];
    }

    public function test_statement_computes_balance_from_payments_recorded_in_the_period(): void
    {
        [$tenant, $owner, $subscription] = $this->makeSubscriptionWithOwner();

        app(SubscriptionService::class)->recordPayment($subscription, 599, 'GCash', 'REF1', null, null);

        $statement = BillingStatement::for($subscription->fresh());

        $this->assertSame(599, $statement->charges);
        $this->assertSame(599, $statement->paid);
        $this->assertSame(0, $statement->balance);
        $this->assertSame('paid', $statement->status);
    }

    public function test_statement_shows_a_balance_due_when_unpaid_and_period_still_active(): void
    {
        [$tenant, $owner, $subscription] = $this->makeSubscriptionWithOwner();

        $statement = BillingStatement::for($subscription);

        $this->assertSame(599, $statement->balance);
        $this->assertSame('due', $statement->status);
    }

    public function test_statement_shows_overdue_once_the_period_end_has_passed_unpaid(): void
    {
        [$tenant, $owner, $subscription] = $this->makeSubscriptionWithOwner();
        $subscription->update(['current_period_end' => now()->subDay()]);

        $statement = BillingStatement::for($subscription->fresh());

        $this->assertSame('overdue', $statement->status);
    }

    public function test_owner_can_view_their_own_statement(): void
    {
        [$tenant, $owner, $subscription] = $this->makeSubscriptionWithOwner();

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($owner->activeMembership());

        Livewire::test('pages::tenant.billing')
            ->assertSee('Statement of Account')
            ->assertSee($tenant->name)
            ->assertSee('₱599');
    }

    public function test_admin_can_view_any_business_statement(): void
    {
        [$tenant, $owner, $subscription] = $this->makeSubscriptionWithOwner();
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.businesses.statement', ['tenant' => $tenant->uuid])
            ->assertSee('Statement of Account')
            ->assertSee($tenant->name);
    }

    public function test_non_admin_cannot_view_another_businesss_statement_route(): void
    {
        [$tenant, $owner, $subscription] = $this->makeSubscriptionWithOwner();
        $cashier = User::factory()->create();

        $this->actingAs($cashier)->get("/admin/businesses/{$tenant->uuid}/statement")->assertForbidden();
    }
}
