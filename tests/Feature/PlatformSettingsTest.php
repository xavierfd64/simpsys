<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\LoginProtectionService;
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

    public function test_admin_can_update_security_settings(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('login_protection_enabled', true)
            ->set('max_login_attempts', 7)
            ->set('lockout_minutes', 30)
            ->set('captcha_enabled', true)
            ->set('captcha_threshold', 4)
            ->set('rate_limiting_enabled', false)
            ->call('saveSecuritySettings')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertTrue($settings->login_protection_enabled);
        $this->assertSame(7, $settings->max_login_attempts);
        $this->assertSame(30, $settings->lockout_minutes);
        $this->assertTrue($settings->captcha_enabled);
        $this->assertSame(4, $settings->captcha_threshold);
        $this->assertFalse($settings->rate_limiting_enabled);

        $this->assertDatabaseHas('audit_logs', ['action' => 'SECURITY_SETTING_CHANGED']);
    }

    public function test_security_settings_reject_a_captcha_threshold_at_or_past_the_lockout_threshold(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('max_login_attempts', 5)
            ->set('captcha_threshold', 5)
            ->call('saveSecuritySettings')
            ->assertHasErrors('captcha_threshold');
    }

    public function test_security_settings_reject_an_out_of_range_lockout_duration(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('lockout_minutes', 0)
            ->call('saveSecuritySettings')
            ->assertHasErrors('lockout_minutes');
    }

    public function test_saved_security_settings_are_actually_used_by_login_protection(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('max_login_attempts', 4)
            ->set('lockout_minutes', 45)
            ->call('saveSecuritySettings');

        $service = app(LoginProtectionService::class);

        for ($i = 0; $i < 4; $i++) {
            $service->recordFailure('victim@example.test', '127.0.0.1');
        }

        $this->assertTrue($service->isLockedOut('victim@example.test'));
        $this->assertGreaterThan(44 * 60, $service->secondsRemaining('victim@example.test'));
    }
}
