<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Supply;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SupplyManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_a_supply(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.supplies.index')
            ->call('openCreate')
            ->set('name', 'Cooking Oil')
            ->set('unit', 'liters')
            ->set('low_stock_threshold', '5')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('supplies', ['name' => 'Cooking Oil', 'tenant_id' => $tenant->id, 'unit' => 'liters']);
    }

    public function test_supplies_are_scoped_to_the_current_tenant(): void
    {
        $tenantA = Tenant::factory()->create();
        $tenantB = Tenant::factory()->create();

        Supply::factory()->for($tenantA)->create(['name' => 'Tenant A Sugar']);
        Supply::factory()->for($tenantB)->create(['name' => 'Tenant B Sugar']);

        $owner = User::factory()->create();
        $membership = $tenantA->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        app(TenantContext::class)->setMembership($membership);

        $names = Supply::query()->pluck('name');

        $this->assertTrue($names->contains('Tenant A Sugar'));
        $this->assertFalse($names->contains('Tenant B Sugar'));
    }

    public function test_cashier_cannot_reach_supplies(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/supplies')->assertForbidden();
    }
}
