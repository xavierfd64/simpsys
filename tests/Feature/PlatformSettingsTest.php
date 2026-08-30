<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
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

    public function test_admin_can_save_smtp_settings_and_the_password_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('mail_mailer', 'smtp')
            ->set('mail_host', 'smtp.example.com')
            ->set('mail_port', '587')
            ->set('mail_encryption', 'tls')
            ->set('mail_username', 'no-reply@example.com')
            ->set('mail_password', 'secret-password')
            ->set('mail_from_address', 'no-reply@example.com')
            ->set('mail_from_name', 'Sukli')
            ->call('saveMail')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertSame('smtp', $settings->mail_mailer);
        $this->assertSame('smtp.example.com', $settings->mail_host);
        $this->assertSame('secret-password', $settings->mail_password);

        $raw = \DB::table('platform_settings')->where('id', $settings->id)->value('mail_password');
        $this->assertNotSame('secret-password', $raw);
    }

    public function test_saving_mail_settings_with_a_blank_password_keeps_the_existing_one(): void
    {
        PlatformSetting::current()->update([
            'mail_mailer' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => '587',
            'mail_password' => 'original-secret',
            'mail_from_address' => 'no-reply@example.com',
            'mail_from_name' => 'Sukli',
        ]);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('mail_host', 'smtp.newhost.com')
            ->call('saveMail')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertSame('smtp.newhost.com', $settings->mail_host);
        $this->assertSame('original-secret', $settings->mail_password);
    }

    public function test_test_email_reports_success_when_sending_works(): void
    {
        Mail::fake();

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('mail_mailer', 'smtp')
            ->set('mail_host', 'smtp.example.com')
            ->set('mail_port', '587')
            ->set('mail_from_address', 'no-reply@example.com')
            ->set('mail_from_name', 'Sukli')
            ->set('test_email_address', 'owner@example.com')
            ->call('sendTestEmail')
            ->assertSet('test_email_status', 'success');
    }

    public function test_test_email_reports_failure_when_smtp_is_unreachable(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('mail_mailer', 'smtp')
            ->set('mail_host', '127.0.0.1')
            ->set('mail_port', '1')
            ->set('mail_from_address', 'no-reply@example.com')
            ->set('mail_from_name', 'Sukli')
            ->set('test_email_address', 'owner@example.com')
            ->call('sendTestEmail')
            ->assertSet('test_email_status', 'failure');
    }
}
