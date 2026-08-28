<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_business_information(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Old Name']);
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);

        // Livewire::test() mounts the component directly, bypassing route
        // middleware, so TenantContext must be seeded the way IdentifyTenant
        // would seed it on a real request.
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.settings')
            ->set('name', 'New Business Name')
            ->set('timezone', 'Asia/Manila')
            ->call('saveBusinessInfo')
            ->assertHasNoErrors();

        $this->assertSame('New Business Name', $tenant->fresh()->name);
    }

    public function test_cashier_cannot_reach_business_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/settings')->assertForbidden();
    }
}
