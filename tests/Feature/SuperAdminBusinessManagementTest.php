<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
