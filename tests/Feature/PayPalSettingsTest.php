<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PayPalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_reach_platform_settings_at_all(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create();
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($owner)->get('/admin/settings')->assertForbidden();
    }

    public function test_admin_can_save_payment_method_toggles(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('manual_payment_enabled', false)
            ->call('savePaymentMethods')
            ->assertHasNoErrors();

        $this->assertFalse(PlatformSetting::current()->manual_payment_enabled);
    }

    public function test_admin_can_save_paypal_settings_and_the_secret_is_encrypted_at_rest(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('paypal_enabled', true)
            ->set('paypal_environment', 'sandbox')
            ->set('paypal_client_id', 'client-id-123')
            ->set('paypal_client_secret', 'super-secret-value')
            ->set('paypal_webhook_id', 'WH-123')
            ->set('paypal_currency', 'php')
            ->call('savePayPalSettings')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertTrue($settings->paypal_enabled);
        $this->assertSame('client-id-123', $settings->paypal_client_id);
        $this->assertSame('super-secret-value', $settings->paypal_client_secret);
        $this->assertSame('PHP', $settings->paypal_currency);

        $raw = \DB::table('platform_settings')->where('id', $settings->id)->value('paypal_client_secret');
        $this->assertNotSame('super-secret-value', $raw);
    }

    public function test_enabling_paypal_without_credentials_is_rejected(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('paypal_enabled', true)
            ->set('paypal_client_id', '')
            ->set('paypal_client_secret', '')
            ->call('savePayPalSettings')
            ->assertHasErrors('paypal_client_id');

        $this->assertFalse(PlatformSetting::current()->paypal_enabled);
    }

    public function test_saving_paypal_settings_with_a_blank_secret_keeps_the_existing_one(): void
    {
        PlatformSetting::current()->update([
            'paypal_enabled' => true,
            'paypal_client_id' => 'client-id-123',
            'paypal_client_secret' => 'original-secret',
        ]);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('paypal_client_id', 'new-client-id')
            ->call('savePayPalSettings')
            ->assertHasNoErrors();

        $settings = PlatformSetting::current();
        $this->assertSame('new-client-id', $settings->paypal_client_id);
        $this->assertSame('original-secret', $settings->paypal_client_secret);
    }

    public function test_test_connection_reports_success_using_unsaved_form_values(): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['access_token' => 'tok'])]);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('paypal_client_id', 'client-id-123')
            ->set('paypal_client_secret', 'secret-value')
            ->call('testPayPalConnection')
            ->assertSet('paypal_test_status', 'success');
    }

    public function test_test_connection_reports_failure_on_bad_credentials(): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('paypal_client_id', 'client-id-123')
            ->set('paypal_client_secret', 'wrong-secret')
            ->call('testPayPalConnection')
            ->assertSet('paypal_test_status', 'failure');
    }

    public function test_test_connection_never_leaks_the_secret_in_the_error_message(): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        $test = Livewire::test('pages::admin.settings')
            ->set('paypal_client_id', 'client-id-123')
            ->set('paypal_client_secret', 'super-secret-value')
            ->call('testPayPalConnection');

        $this->assertStringNotContainsString('super-secret-value', $test->get('paypal_test_message'));
    }

    public function test_sandbox_and_live_environments_are_both_selectable(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $this->actingAs($admin);

        Livewire::test('pages::admin.settings')
            ->set('paypal_environment', 'live')
            ->set('paypal_client_id', 'cid')
            ->set('paypal_client_secret', 'sec')
            ->call('savePayPalSettings')
            ->assertHasNoErrors();

        $this->assertSame('live', PlatformSetting::current()->paypal_environment);
    }
}
