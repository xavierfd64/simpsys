<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_owner_can_upload_and_remove_a_business_logo(): void
    {
        Storage::fake('public');

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        Livewire::test('pages::tenant.settings')
            ->set('name', $tenant->name)
            ->set('timezone', $tenant->timezone)
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('saveBusinessInfo')
            ->assertHasNoErrors();

        $tenant->refresh();
        $this->assertNotNull($tenant->logo_path);
        Storage::disk('public')->assertExists($tenant->logo_path);

        Livewire::test('pages::tenant.settings')
            ->call('removeLogo')
            ->assertHasNoErrors();

        $this->assertNull($tenant->fresh()->logo_path);
    }

    public function test_cashier_cannot_reach_business_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $cashier = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $cashier->id, 'role' => TenantMembershipRole::Cashier]);

        $this->actingAs($cashier)->get('/app/settings')->assertForbidden();
    }
}
