<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessSettingsTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function actingAsOwner(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $tenant->settings()->create([]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        return [$tenant, $owner];
    }

    public function test_owner_can_update_order_type_settings(): void
    {
        [$tenant] = $this->actingAsOwner();

        Livewire::test('pages::tenant.settings')
            ->set('order_types_enabled', true)
            ->set('dine_in_enabled', true)
            ->set('to_go_enabled', true)
            ->set('default_order_type', 'dine_in')
            ->call('saveOrderTypeSettings')
            ->assertHasNoErrors();

        $settings = $tenant->settings()->first();
        $this->assertTrue($settings->order_types_enabled);
        $this->assertSame('dine_in', $settings->default_order_type->value);
    }

    public function test_owner_can_add_and_toggle_a_payment_method(): void
    {
        $this->actingAsOwner();

        Livewire::test('pages::tenant.settings')
            ->call('openCreatePaymentMethod')
            ->set('payment_method_name', 'GCash')
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $method = PaymentMethod::where('name', 'GCash')->firstOrFail();
        $this->assertTrue($method->is_enabled);

        Livewire::test('pages::tenant.settings')->call('togglePaymentMethod', $method->id);
        $this->assertFalse($method->fresh()->is_enabled);
    }
}
