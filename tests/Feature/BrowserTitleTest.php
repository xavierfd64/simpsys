<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrowserTitleTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_title_uses_the_configured_platform_name_not_bizmanager(): void
    {
        PlatformSetting::current()->update(['platform_name' => 'Gatang']);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Gatang</title>', false)
            ->assertDontSee('<title>BizManager', false);
    }

    public function test_login_page_title_uses_the_configured_platform_name(): void
    {
        PlatformSetting::current()->update(['platform_name' => 'Gatang']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Log In — Gatang</title>', false);
    }

    public function test_admin_page_title_uses_the_configured_platform_name(): void
    {
        PlatformSetting::current()->update(['platform_name' => 'Gatang']);
        $admin = User::factory()->create(['is_platform_admin' => true]);

        $this->actingAs($admin)
            ->get('/admin/businesses')
            ->assertOk()
            ->assertSee('<title>Businesses — Gatang</title>', false);
    }

    public function test_tenant_page_title_uses_the_tenants_own_business_name(): void
    {
        PlatformSetting::current()->update(['platform_name' => 'Gatang']);
        $tenant = Tenant::factory()->create(['name' => 'Jilad Native Cakes']);
        $owner = User::factory()->create();
        $membership = $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner);
        app(TenantContext::class)->setMembership($membership);

        $this->get('/app/dashboard')
            ->assertOk()
            ->assertSee('<title>Dashboard — Jilad Native Cakes</title>', false);
    }
}
