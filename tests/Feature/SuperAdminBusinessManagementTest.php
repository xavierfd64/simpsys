<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class SuperAdminBusinessManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_shows_business_counts(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Tenant::factory()->create(['status' => TenantStatus::Active]);
        Tenant::factory()->create(['status' => TenantStatus::Trial]);
        Tenant::factory()->create(['status' => TenantStatus::Suspended]);

        $this->actingAs($admin);

        Livewire::test('pages::admin.dashboard')->assertSee('3');
    }

    public function test_admin_dashboard_period_filter_scopes_new_business_registrations(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        Carbon::setTestNow(now()->subDays(3));
        Tenant::factory()->create();
        $threeDaysAgo = now()->toDateString();
        Carbon::setTestNow();

        Tenant::factory()->create();

        $this->actingAs($admin);

        $test = Livewire::test('pages::admin.dashboard');
        $this->assertNewBusinessesCount(1, $test->html());

        $test->set('period', 'custom')->set('dateFrom', $threeDaysAgo)->set('dateTo', $threeDaysAgo);
        $this->assertNewBusinessesCount(1, $test->html());
    }

    private function assertNewBusinessesCount(int $expected, string $html): void
    {
        preg_match('/New Businesses<\/p>\s*<p[^>]*>(\d+)/', $html, $matches);
        $this->assertSame($expected, (int) ($matches[1] ?? -1));
    }

    public function test_suspending_a_business_blocks_its_owner_from_logging_in(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])->call('suspendBusiness');

        $this->assertSame(TenantStatus::Suspended, $tenant->fresh()->status);
        $this->actingAs($owner)->get('/app/dashboard')->assertForbidden();
    }

    public function test_reactivating_a_business_restores_owner_access(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])->call('reactivateBusiness');

        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->actingAs($owner)->get('/app/dashboard')->assertOk();
    }

    /**
     * A business with a real subscription record must show one agreeing
     * status everywhere: the top-of-page Tenant badge and the Subscription
     * card's own badge are two separate reads of two separate columns, and
     * suspending/reactivating must keep both in lockstep rather than only
     * updating one of them.
     */
    public function test_suspending_a_business_with_a_subscription_keeps_both_status_badges_in_agreement(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create();
        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Active,
            'current_period_start' => now(),
            'current_period_end' => now()->addDays(30),
        ]);

        $this->actingAs($admin);
        $test = Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])->call('suspendBusiness');

        $this->assertSame(TenantStatus::Suspended, $tenant->fresh()->status);
        $this->assertSame(SubscriptionStatus::Suspended, $subscription->fresh()->status);
        $test->assertSeeInOrder(['Suspended', 'Suspended']);

        $test->call('reactivateBusiness');

        $this->assertSame(TenantStatus::Active, $tenant->fresh()->status);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }

    /**
     * latestSubscription() must keep surfacing a suspended/expired/
     * cancelled subscription's record and management controls — the older
     * currentSubscription() (filtered to trial/active only) would make the
     * whole Subscription card disappear the moment status is anything else.
     */
    public function test_a_suspended_subscriptions_management_card_stays_visible_on_the_business_page(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
        $plan = SubscriptionPlan::factory()->create();
        $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Suspended,
        ]);

        $this->actingAs($admin);

        Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])
            ->assertOk()
            ->assertDontSee('No subscription record.')
            ->assertSee('Reactivate Business');
    }

    public function test_admin_can_still_view_a_soft_deleted_business(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create();
        $tenant->delete();

        $this->actingAs($admin);

        Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])
            ->assertOk()
            ->assertSee($tenant->name);
    }

    public function test_non_admin_cannot_access_business_management(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner)->get('/admin/businesses')->assertForbidden();
    }
}
