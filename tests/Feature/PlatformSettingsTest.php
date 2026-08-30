<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_platform_branding_and_contact_info(): void
    {
        Storage::fake('public');

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('platform_name', 'Sukli')
            ->set('support_email', 'help@sukli.test')
            ->set('support_phone', '+63 900 000 0000')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->set('favicon', UploadedFile::fake()->image('favicon.png'))
            ->call('save')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertSame('Sukli', $settings->platform_name);
        $this->assertSame('help@sukli.test', $settings->support_email);
        $this->assertSame('+63 900 000 0000', $settings->support_phone);
        $this->assertNotNull($settings->logo_path);
        $this->assertNotNull($settings->favicon_path);
        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_public_site_reflects_configured_platform_branding(): void
    {
        PlatformSetting::current()->update([
            'platform_name' => 'Sukli',
            'support_email' => 'help@sukli.test',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Sukli')
            ->assertSee('help@sukli.test');
    }

    public function test_cashier_and_guests_cannot_reach_platform_settings(): void
    {
        $user = User::factory()->create(['is_platform_admin' => false]);
        $this->actingAs($user)->get('/admin/settings')->assertForbidden();
    }
}
