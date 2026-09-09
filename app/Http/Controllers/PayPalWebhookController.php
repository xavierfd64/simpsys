<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PayPalWebhookEvent;
use App\Services\PayPalCheckoutService;
use App\Services\PayPalClient;
use App\Support\PayPalException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

/**
 * Receives PayPal webhook events. Every event is verified via PayPal's own
 * server-side signature-verification API before anything in it is trusted
 * (see PayPalClient::verifyWebhookSignature()) — an unverified event is
 * rejected outright, never acted on. Every event id is recorded before
 * being processed so a redelivery (PayPal explicitly may resend) is
 * recognized and skipped rather than double-applying a payment.
 *
 * A PAYMENT.CAPTURE.PENDING event deliberately does nothing — pending is
 * not completed, and must never activate a subscription.
 */
class PayPalWebhookController extends Controller
{
    public function __invoke(Request $request, PayPalClient $client, PayPalCheckoutService $checkout): JsonResponse
    {
        $body = $request->json()->all();
        $eventId = $body['id'] ?? null;
        $eventType = $body['event_type'] ?? null;

        if (blank($eventId) || blank($eventType)) {
            return response()->json(['status' => 'ignored']);
        }

        $event = PayPalWebhookEvent::firstOrCreate(['event_id' => $eventId], ['event_type' => $eventType]);

        if (! $event->wasRecentlyCreated && $event->processed_at !== null) {
            return response()->json(['status' => 'duplicate']);
        }

        $headers = [
            'transmission_id' => $request->header('Paypal-Transmission-Id'),
            'transmission_time' => $request->header('Paypal-Transmission-Time'),
            'cert_url' => $request->header('Paypal-Cert-Url'),
            'auth_algo' => $request->header('Paypal-Auth-Algo'),
            'transmission_sig' => $request->header('Paypal-Transmission-Sig'),
        ];

        AuditLog::record('paypal.webhook_received', [
            'description' => "Received PayPal webhook {$eventType} ({$eventId}).",
        ]);

        if (! $client->verifyWebhookSignature($headers, $body)) {
            AuditLog::record('paypal.webhook_rejected', [
                'description' => "Rejected unverified PayPal webhook {$eventType} ({$eventId}).",
            ]);

            return response()->json(['status' => 'signature verification failed'], 400);
        }

        AuditLog::record('paypal.webhook_verified', [
            'description' => "Verified PayPal webhook {$eventType} ({$eventId}).",
        ]);

        $resource = $body['resource'] ?? [];

        try {
            match ($eventType) {
                'CHECKOUT.ORDER.APPROVED' => $checkout->completeOrder($resource['id'] ?? ''),
                'PAYMENT.CAPTURE.COMPLETED' => $this->completeFromCaptureResource($resource, $checkout),
                'PAYMENT.CAPTURE.DENIED' => $checkout->markDenied($this->orderIdFromCapture($resource), $resource),
                'CHECKOUT.PAYMENT-APPROVAL.REVERSED' => $checkout->markDenied($resource['id'] ?? '', $resource),
                // PAYMENT.CAPTURE.PENDING and anything else: no action —
                // pending must never activate a subscription.
                default => null,
            };
        } catch (PayPalException $e) {
            // A permanent, not-retry-fixable condition (e.g. an event for
            // an order this installation has no record of) — mark it
            // handled so PayPal stops redelivering something that will
            // never resolve differently.
            $event->update(['processed_at' => now(), 'note' => $e->getMessage()]);

            return response()->json(['status' => 'ok']);
        } catch (Throwable $e) {
            report($e);

            // Anything else is treated as possibly transient — respond
            // with an error so PayPal retries later, and leave the event
            // unmarked so a retry is actually reprocessed.
            return response()->json(['status' => 'error'], 500);
        }

        $event->update(['processed_at' => now()]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function completeFromCaptureResource(array $resource, PayPalCheckoutService $checkout): void
    {
        $orderId = $this->orderIdFromCapture($resource);

        if (filled($orderId)) {
            $checkout->completeOrder($orderId);
        }
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    protected function orderIdFromCapture(array $resource): ?string
    {
        return $resource['supplementary_data']['related_ids']['order_id'] ?? null;
    }
}
