<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlansPromotionsNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_plan(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.plans.index')
            ->call('openCreate')
            ->set('name', 'Enterprise')
            ->set('monthly_price', '1999')
            ->set('yearly_price', '19990')
            ->set('user_limit', '20')
            ->set('features_text', "Priority support\nCustom branding")
            ->call('save')
            ->assertHasNoErrors();

        $plan = SubscriptionPlan::where('name', 'Enterprise')->firstOrFail();
        $this->assertSame(1999, $plan->monthly_price);
        $this->assertSame(['Priority support', 'Custom branding'], $plan->features);
    }

    public function test_admin_can_create_a_promotion(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.promotions.index')
            ->call('openCreate')
            ->set('code', 'launch20')
            ->set('discount_type', 'percentage')
            ->set('discount_value', '20')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('promotions', ['code' => 'LAUNCH20', 'discount_value' => 20]);
    }

    public function test_admin_can_send_a_notification_and_tenant_sees_it_on_their_dashboard(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.notifications.index')
            ->set('audience', 'active')
            ->set('title', 'Scheduled Maintenance')
            ->set('message', 'We will be down for maintenance tonight.')
            ->call('save')
            ->assertHasNoErrors();

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.dashboard')->assertSee('Scheduled Maintenance');
    }

    public function test_notification_targeted_at_trial_businesses_does_not_show_for_active_ones(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.notifications.index')
            ->set('audience', 'trial')
            ->set('title', 'Trial Ending Soon')
            ->set('message', 'Your trial ends in 3 days.')
            ->call('save');

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.dashboard')->assertDontSee('Trial Ending Soon');
    }
}
