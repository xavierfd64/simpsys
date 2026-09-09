<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Services\PayPalClient;
use App\Support\PayPalException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayPalClientTest extends TestCase
{
    use RefreshDatabase;

    protected function settings(array $overrides = []): PlatformSetting
    {
        $settings = PlatformSetting::current();
        $settings->update(array_merge([
            'paypal_environment' => 'sandbox',
            'paypal_client_id' => 'client-id-123',
            'paypal_client_secret' => 'secret-abc',
            'paypal_webhook_id' => 'WH-123',
            'paypal_currency' => 'PHP',
        ], $overrides));

        return $settings->fresh();
    }

    public function test_sandbox_and_live_use_different_api_base_urls(): void
    {
        $this->assertSame('https://api-m.sandbox.paypal.com', $this->settings(['paypal_environment' => 'sandbox'])->paypalApiBaseUrl());
        $this->assertSame('https://api-m.paypal.com', $this->settings(['paypal_environment' => 'live'])->paypalApiBaseUrl());
    }

    public function test_access_token_is_fetched_via_oauth_and_cached(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-xyz', 'expires_in' => 32000]),
        ]);

        $client = new PayPalClient($this->settings());

        $this->assertSame('token-xyz', $client->accessToken());
        $this->assertSame('token-xyz', $client->accessToken());

        Http::assertSentCount(1);
    }

    public function test_access_token_failure_throws_a_safe_message_without_leaking_the_secret(): void
    {
        Http::fake(['*/v1/oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $client = new PayPalClient($this->settings(['paypal_client_secret' => 'super-secret-value']));

        try {
            $client->accessToken();
            $this->fail('Expected a PayPalException.');
        } catch (PayPalException $e) {
            $this->assertStringNotContainsString('super-secret-value', $e->getMessage());
        }
    }

    public function test_create_order_sends_the_server_computed_amount_and_returns_an_approve_url(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-xyz']),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'ORDER123',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve/ORDER123']],
            ]),
        ]);

        $result = (new PayPalClient($this->settings()))->createOrder(
            referenceId: 'ref-1',
            amountPesos: 999,
            currency: 'PHP',
            description: 'Test plan',
            returnUrl: 'https://app.test/return',
            cancelUrl: 'https://app.test/cancel',
        );

        $this->assertSame('ORDER123', $result['id']);
        $this->assertSame('https://paypal.test/approve/ORDER123', $result['approve_url']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api-m.sandbox.paypal.com/v2/checkout/orders'
                && $request['purchase_units'][0]['amount']['value'] === '999.00'
                && $request['purchase_units'][0]['amount']['currency_code'] === 'PHP';
        });
    }

    public function test_capture_order_falls_back_to_get_order_when_already_captured(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-xyz']),
            '*/v2/checkout/orders/ORDER123/capture' => Http::response([
                'name' => 'UNPROCESSABLE_ENTITY',
                'details' => [['issue' => 'ORDER_ALREADY_CAPTURED']],
            ], 422),
            '*/v2/checkout/orders/ORDER123' => Http::response(['id' => 'ORDER123', 'status' => 'COMPLETED']),
        ]);

        $result = (new PayPalClient($this->settings()))->captureOrder('ORDER123');

        $this->assertSame('COMPLETED', $result['status']);
    }

    public function test_capture_order_throws_a_safe_message_on_a_genuine_failure(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-xyz']),
            '*/v2/checkout/orders/ORDER123/capture' => Http::response(['name' => 'SOME_OTHER_ERROR'], 422),
        ]);

        $this->expectException(PayPalException::class);
        (new PayPalClient($this->settings()))->captureOrder('ORDER123');
    }

    public function test_verify_webhook_signature_reports_success_and_failure(): void
    {
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'token-xyz']),
            '*/v1/notifications/verify-webhook-signature' => Http::sequence()
                ->push(['verification_status' => 'SUCCESS'])
                ->push(['verification_status' => 'FAILURE']),
        ]);

        $client = new PayPalClient($this->settings());
        $headers = ['transmission_id' => 't1', 'transmission_time' => 'now', 'cert_url' => 'https://x', 'auth_algo' => 'SHA256', 'transmission_sig' => 'sig'];

        $this->assertTrue($client->verifyWebhookSignature($headers, ['id' => 'EVT1']));
        $this->assertFalse($client->verifyWebhookSignature($headers, ['id' => 'EVT2']));
    }

    public function test_verify_webhook_signature_is_false_without_a_configured_webhook_id(): void
    {
        $client = new PayPalClient($this->settings(['paypal_webhook_id' => null]));

        $this->assertFalse($client->verifyWebhookSignature([], ['id' => 'EVT1']));
    }

    public function test_test_connection_reports_success_and_failure(): void
    {
        // A single Http::fake() call with a sequence — calling Http::fake()
        // a second time in the same test does not replace an
        // already-registered pattern (Laravel matches fakes in
        // registration order), so a real "first succeeds, then fails"
        // scenario must use one sequence rather than two separate fakes.
        Http::fake([
            '*/v1/oauth2/token' => Http::sequence()
                ->push(['access_token' => 'token-xyz'])
                ->push(['error' => 'invalid_client'], 401),
        ]);

        $client = new PayPalClient($this->settings());

        $this->assertTrue($client->testConnection()['success']);
        $this->assertFalse($client->testConnection()['success']);
    }
}
