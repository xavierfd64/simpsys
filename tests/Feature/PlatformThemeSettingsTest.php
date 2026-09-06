<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ColorTheme;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformThemeSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_admin_can_save_a_valid_primary_color_and_font(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('theme_primary_color', '#7c3aed')
            ->set('theme_font', 'georgia')
            ->call('saveAppearance')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertSame('#7c3aed', $settings->theme_primary_color);
        $this->assertSame('georgia', $settings->theme_font);
    }

    public function test_a_saved_theme_color_is_applied_as_a_css_variable_on_a_real_page(): void
    {
        PlatformSetting::current()->update(['theme_primary_color' => '#7c3aed', 'theme_font' => 'georgia']);

        $this->get('/')
            ->assertOk()
            ->assertSee('--color-primary-600: #7c3aed', false)
            ->assertSee("Georgia, 'Times New Roman', serif", false);
    }

    public function test_no_theme_style_block_is_rendered_when_nothing_is_configured(): void
    {
        $this->get('/')->assertOk()->assertDontSee('--color-primary-600', false);
    }

    /**
     * The exact constraint the user gave: arbitrary colors must not be
     * allowed to destroy contrast/accessibility (e.g. white text on a pale
     * yellow button would be unreadable).
     */
    public function test_a_low_contrast_color_is_rejected_and_not_saved(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('theme_primary_color', '#fef9c3') // pale yellow — fails contrast against white
            ->set('theme_font', 'inter')
            ->call('saveAppearance')
            ->assertHasErrors('theme_primary_color');

        $this->assertNull(PlatformSetting::current()->theme_primary_color);
    }

    public function test_an_invalid_hex_string_is_rejected(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('theme_primary_color', 'not-a-color')
            ->call('saveAppearance')
            ->assertHasErrors('theme_primary_color');
    }

    public function test_reset_clears_the_saved_theme_back_to_default(): void
    {
        PlatformSetting::current()->update(['theme_primary_color' => '#7c3aed', 'theme_font' => 'georgia']);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')->call('resetAppearance');

        $settings = PlatformSetting::current();
        $this->assertNull($settings->theme_primary_color);
        $this->assertNull($settings->theme_font);

        $this->get('/')->assertOk()->assertDontSee('--color-primary-600', false);
    }

    /**
     * Only Platform Admin / Super Admin may change system-wide appearance —
     * a tenant owner has no route or component that exposes it at all.
     */
    public function test_a_tenant_owner_cannot_reach_the_platform_appearance_settings(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner)->get('/admin/settings')->assertForbidden();
    }

    public function test_color_theme_contrast_check_matches_wcag_expectations(): void
    {
        $this->assertTrue(ColorTheme::isAccessible('#2563eb'));
        $this->assertTrue(ColorTheme::isAccessible('#000000'));
        $this->assertFalse(ColorTheme::isAccessible('#fef9c3'));
        $this->assertFalse(ColorTheme::isAccessible('#ffffff'));
    }
}
