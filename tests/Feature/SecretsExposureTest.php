<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defense-in-depth: PlatformSetting stores the SMTP password, PayPal client
 * secret, and PayPal webhook id — nothing in this codebase currently
 * serializes the whole model (every call site reads one specific field or
 * goes through a computed Livewire property), but #[Hidden(...)] means a
 * future accidental toArray()/toJson()/public-property-binding mistake
 * can't leak them either.
 */
class SecretsExposureTest extends TestCase
{
    use RefreshDatabase;

    public function test_sensitive_platform_settings_are_excluded_from_serialization(): void
    {
        $settings = PlatformSetting::current();
        $settings->update([
            'mail_password' => 'super-secret-smtp-password',
            'paypal_client_secret' => 'super-secret-paypal-client-secret',
            'paypal_webhook_id' => 'WH-12345',
        ]);

        $array = $settings->fresh()->toArray();

        $this->assertArrayNotHasKey('mail_password', $array);
        $this->assertArrayNotHasKey('paypal_client_secret', $array);
        $this->assertArrayNotHasKey('paypal_webhook_id', $array);

        $json = $settings->fresh()->toJson();

        $this->assertStringNotContainsString('super-secret-smtp-password', $json);
        $this->assertStringNotContainsString('super-secret-paypal-client-secret', $json);
    }

    public function test_direct_attribute_access_to_secrets_still_works_for_trusted_server_code(): void
    {
        $settings = PlatformSetting::current();
        $settings->update(['paypal_client_secret' => 'still-readable-directly']);

        $this->assertSame('still-readable-directly', $settings->fresh()->paypal_client_secret);
    }
}
