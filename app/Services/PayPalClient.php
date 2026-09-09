<?php

namespace App\Services;

use App\Models\PlatformSetting;
use App\Support\PayPalException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thin server-side wrapper around PayPal's current REST APIs (OAuth 2.0 +
 * Orders v2 + webhook signature verification) — no SDK dependency, just
 * Laravel's own HTTP client, so nothing new needs to be added to
 * composer.json or the shared-hosting deployment. Every call reads
 * credentials from PlatformSetting (configured entirely through the
 * Platform Admin UI), never from .env — the Client Secret is stored
 * encrypted at rest (see PlatformSetting's 'encrypted' cast) and is never
 * exposed by any method here.
 */
class PayPalClient
{
    public function __construct(protected PlatformSetting $settings) {}

    public static function fromSettings(): self
    {
        return new self(PlatformSetting::current());
    }

    protected function baseUrl(): string
    {
        return $this->settings->paypalApiBaseUrl();
    }

    /**
     * OAuth 2.0 client-credentials token, cached for its own lifetime (with
     * a safety margin) so a burst of requests doesn't re-authenticate every
     * time — keyed by environment + client id so switching sandbox/live or
     * rotating the secret can never serve a stale token for the wrong pair.
     */
    public function accessToken(): string
    {
        $clientId = $this->settings->paypal_client_id;
        $secret = $this->settings->paypal_client_secret;

        if (blank($clientId) || blank($secret)) {
            throw new PayPalException('PayPal is not configured — a Client ID and Client Secret are required.');
        }

        $cacheKey = 'paypal_access_token_'.$this->settings->paypal_environment.'_'.md5($clientId);

        return Cache::remember($cacheKey, 300, function () use ($clientId, $secret) {
            $response = Http::asForm()
                ->withBasicAuth($clientId, $secret)
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials']);

            if (! $response->successful()) {
                throw new PayPalException('Could not authenticate with PayPal. Please check the Client ID and Client Secret and try again.');
            }

            $token = $response->json('access_token');

            if (blank($token)) {
                throw new PayPalException('PayPal did not return an access token.');
            }

            return $token;
        });
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array
    {
        try {
            Cache::forget('paypal_access_token_'.$this->settings->paypal_environment.'_'.md5((string) $this->settings->paypal_client_id));
            $this->accessToken();

            return ['success' => true, 'message' => 'PayPal connection successful.'];
        } catch (PayPalException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        } catch (Throwable $e) {
            report($e);

            return ['success' => false, 'message' => 'PayPal connection failed. Please check your credentials and try again.'];
        }
    }

    /**
     * Creates a PayPal order for a server-computed amount — callers must
     * never pass a client-supplied price. Returns the order id and the
     * "approve" link the customer is redirected to.
     *
     * @return array{id: string, approve_url: string}
     */
    public function createOrder(
        string $referenceId,
        int $amountPesos,
        string $currency,
        string $description,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        $response = Http::withToken($this->accessToken())
            ->post($this->baseUrl().'/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $referenceId,
                    'description' => Str::limit($description, 127, ''),
                    'amount' => [
                        'currency_code' => $currency,
                        'value' => number_format($amountPesos, 2, '.', ''),
                    ],
                ]],
                'application_context' => [
                    'brand_name' => $this->settings->displayName(),
                    'user_action' => 'PAY_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ]);

        if (! $response->successful()) {
            report(new PayPalException('PayPal create-order failed: '.$response->body()));

            throw new PayPalException('Could not start the PayPal checkout. Please try again in a moment.');
        }

        $body = $response->json();
        $approveUrl = collect($body['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        if (blank($body['id'] ?? null) || blank($approveUrl)) {
            throw new PayPalException('PayPal did not return a usable checkout link.');
        }

        return ['id' => $body['id'], 'approve_url' => $approveUrl];
    }

    /**
     * Captures an already-approved order. PayPal itself is the source of
     * truth for whether the payment genuinely completed — the caller must
     * check the returned status (or catch AlreadyCaptured below) rather
     * than assume success just because this didn't throw.
     *
     * @return array<string, mixed> the raw capture response
     */
    public function captureOrder(string $orderId): array
    {
        $response = Http::withToken($this->accessToken())
            ->post($this->baseUrl()."/v2/checkout/orders/{$orderId}/capture");

        $body = $response->json() ?? [];

        // An order already captured (e.g. the browser-return path already
        // completed it before a webhook retry arrives) is not a failure —
        // it's exactly the idempotent case callers must handle gracefully.
        if (! $response->successful()) {
            $issue = $body['details'][0]['issue'] ?? null;

            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                return $this->getOrder($orderId);
            }

            report(new PayPalException('PayPal capture failed: '.$response->body()));

            throw new PayPalException('PayPal could not complete this payment. Please try again or choose another payment method.');
        }

        return $body;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOrder(string $orderId): array
    {
        $response = Http::withToken($this->accessToken())
            ->get($this->baseUrl()."/v2/checkout/orders/{$orderId}");

        if (! $response->successful()) {
            throw new PayPalException('Could not look up this PayPal order.');
        }

        return $response->json();
    }

    /**
     * Official server-side webhook signature verification — PayPal
     * validates the transmission headers and raw event body for us rather
     * than requiring this app to implement certificate-chain/RSA
     * verification itself. Never trust an unverified webhook.
     *
     * @param  array<string, string>  $headers  the five Paypal-Transmission-Id/Time/Sig, Paypal-Cert-Url and Paypal-Auth-Algo headers from the incoming request
     * @param  array<string, mixed>  $eventBody  the decoded webhook JSON body
     */
    public function verifyWebhookSignature(array $headers, array $eventBody): bool
    {
        if (blank($this->settings->paypal_webhook_id)) {
            return false;
        }

        try {
            $response = Http::withToken($this->accessToken())
                ->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
                    'transmission_id' => $headers['transmission_id'] ?? null,
                    'transmission_time' => $headers['transmission_time'] ?? null,
                    'cert_url' => $headers['cert_url'] ?? null,
                    'auth_algo' => $headers['auth_algo'] ?? null,
                    'transmission_sig' => $headers['transmission_sig'] ?? null,
                    'webhook_id' => $this->settings->paypal_webhook_id,
                    'webhook_event' => $eventBody,
                ]);

            return $response->successful() && $response->json('verification_status') === 'SUCCESS';
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
