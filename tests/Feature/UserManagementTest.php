<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsOwnerWithPlan(int $userLimit): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create(['user_limit' => $userLimit]);
        $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => SubscriptionStatus::Active,
        ]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        return [$tenant, $owner];
    }

    public function test_owner_can_add_a_user_within_the_plan_limit(): void
    {
        [$tenant] = $this->actingAsOwnerWithPlan(5);

        Livewire::test('pages::tenant.users.index')
            ->call('openCreate')
            ->set('name', 'New Cashier')
            ->set('email', 'newcashier@example.test')
            ->set('password', 'password123')
            ->set('role', 'cashier')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'newcashier@example.test']);
        $this->assertSame(2, $tenant->memberships()->count());
    }

    public function test_cannot_add_a_user_beyond_the_plan_limit(): void
    {
        // Limit of 1 — the owner themself already fills it.
        $this->actingAsOwnerWithPlan(1);

        Livewire::test('pages::tenant.users.index')
            ->call('openCreate')
            ->assertHasErrors('limit')
            ->assertSet('showFormModal', false);
    }

    public function test_owner_can_deactivate_another_user_but_not_themselves(): void
    {
        [$tenant, $owner] = $this->actingAsOwnerWithPlan(5);
        $cashier = User::factory()->create();
        $cashierMembership = $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);
        $ownerMembership = $tenant->memberships()->where('user_id', $owner->id)->first();

        Livewire::test('pages::tenant.users.index')->call('toggleActive', $cashierMembership->id);
        $this->assertSame('inactive', $cashierMembership->fresh()->status->value);

        Livewire::test('pages::tenant.users.index')
            ->call('toggleActive', $ownerMembership->id)
            ->assertHasErrors('self');
        $this->assertSame('active', $ownerMembership->fresh()->status->value);
    }

    public function test_owner_can_reset_another_users_password(): void
    {
        [$tenant] = $this->actingAsOwnerWithPlan(5);
        $cashier = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        Livewire::test('pages::tenant.users.index')
            ->call('openResetPassword', $membership->id)
            ->set('new_password', 'newpassword123')
            ->call('resetPassword')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('newpassword123', $cashier->fresh()->password));
    }

    public function test_cashier_cannot_access_user_management(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/users')->assertForbidden();
    }
}
