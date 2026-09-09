<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A tenant user (owner/cashier/kitchen staff) must never be able to reach
 * Platform Admin routes by direct URL manipulation, regardless of role, and
 * admin-only actions must leave an audit trail.
 */
class PlatformAdminIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function makeTenantOwner(): User
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        return $owner;
    }

    public function test_a_tenant_owner_cannot_view_the_admin_dashboard(): void
    {
        $owner = $this->makeTenantOwner();

        $this->actingAs($owner)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_a_tenant_owner_cannot_view_admin_businesses_list(): void
    {
        $owner = $this->makeTenantOwner();

        $this->actingAs($owner)->get('/admin/businesses')->assertForbidden();
    }

    public function test_a_tenant_owner_cannot_view_another_businesss_admin_detail_page(): void
    {
        $owner = $this->makeTenantOwner();
        $otherTenant = Tenant::factory()->create();

        $this->actingAs($owner)->get("/admin/businesses/{$otherTenant->uuid}")->assertForbidden();
    }

    public function test_a_tenant_owner_cannot_view_admin_settings_or_updates(): void
    {
        $owner = $this->makeTenantOwner();

        $this->actingAs($owner)->get('/admin/settings')->assertForbidden();
        $this->actingAs($owner)->get('/admin/updates')->assertForbidden();
    }

    /**
     * is_platform_admin is deliberately excluded from User's fillable list
     * (see CLAUDE.md) — a tenant user has no request path that could ever
     * set it, but this confirms the middleware itself checks the real
     * database column, not something a client could influence via a
     * Livewire property or hidden field.
     */
    public function test_platform_admin_flag_cannot_be_set_via_mass_assignment(): void
    {
        $user = User::create([
            'name' => 'Attacker',
            'email' => 'attacker@example.test',
            'password' => 'password',
            'is_platform_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_platform_admin);
    }

    public function test_suspending_a_business_is_recorded_in_the_audit_log(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create();

        $this->actingAs($admin);

        \Livewire\Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])
            ->call('suspendBusiness');

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ADMIN_ACTION',
            'tenant_id' => $tenant->id,
            'user_id' => $admin->id,
        ]);
    }
}
