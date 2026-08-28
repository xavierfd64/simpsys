<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoundationSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_away_from_tenant_and_admin_areas(): void
    {
        $this->get('/app/dashboard')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
    }

    public function test_tenant_owner_can_reach_the_tenant_dashboard_but_not_the_admin_area(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner)
            ->get('/app/dashboard')
            ->assertOk()
            ->assertSee('Dashboard');

        $this->actingAs($owner)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_platform_admin_can_reach_the_admin_dashboard_but_not_the_tenant_area(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/app/dashboard')
            ->assertForbidden();
    }

    public function test_a_user_from_tenant_a_cannot_see_tenant_b_in_their_context(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        $userA = User::factory()->create();
        $tenantA->memberships()->create(['user_id' => $userA->id, 'role' => TenantMembershipRole::Owner]);

        $response = $this->actingAs($userA)->get('/app/dashboard');

        $response->assertOk();
        $response->assertDontSee($tenantB->name);
    }

    public function test_cashier_can_reach_the_tenant_area(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        // The dashboard itself is owner-only; POS is the cashier's home area.
        $this->actingAs($cashier)->get('/app/pos')->assertOk();
        $this->actingAs($cashier)->get('/app/dashboard')->assertForbidden();
    }

    public function test_deactivated_membership_blocks_tenant_access(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $tenant->memberships()->create([
            'user_id' => $user->id,
            'role' => TenantMembershipRole::Owner,
            'status' => 'inactive',
        ]);

        $this->actingAs($user)->get('/app/dashboard')->assertForbidden();
    }
}
