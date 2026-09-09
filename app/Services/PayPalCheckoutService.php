<?php

namespace App\Services;

use App\Enums\BillingPeriod;
use App\Models\AuditLog;
use App\Models\PayPalOrder;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Support\PayPalException;
use Illuminate\Support\Str;

/**
 * Orchestrates a tenant's PayPal-funded plan purchase/renewal on top of the
 * existing subscription architecture: creates a PayPalOrder (the internal
 * "pending payment" record) before ever calling PayPal, and — once PayPal
 * itself confirms a capture completed — records the payment and renews the
 * subscription through the same SubscriptionService::recordPayment() every
 * manually-recorded payment already goes through. This is the one place
 * both the browser-return path and the webhook path complete an order, so
 * idempotency only needs to be solved once.
 */
class PayPalCheckoutService
{
    public function __construct(
        protected PayPalClient $client,
        protected SubscriptionService $subscriptions,
    ) {}

    /**
     * @return array{order: PayPalOrder, approve_url: string}
     */
    public function createOrder(
        Tenant $tenant,
        Subscription $subscription,
        SubscriptionPlan $plan,
        BillingPeriod $period,
        string $returnUrl,
        string $cancelUrl,
    ): array {
        $settings = PlatformSetting::current();

        if (! $settings->isPayPalConfigured()) {
            throw new PayPalException('PayPal is not available right now. Please choose another payment method.');
        }

        $amount = $plan->priceFor($period);
        $currency = $settings->paypal_currency ?: 'PHP';

        $result = $this->client->createOrder(
            referenceId: (string) Str::uuid(),
            amountPesos: $amount,
            currency: $currency,
            description: "{$plan->name} — {$period->label()} subscription",
            returnUrl: $returnUrl,
            cancelUrl: $cancelUrl,
        );

        $order = PayPalOrder::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'subscription_plan_id' => $plan->id,
            'billing_period' => $period->value,
            'amount' => $amount,
            'currency' => $currency,
            'paypal_order_id' => $result['id'],
            'status' => 'created',
        ]);

        AuditLog::record('paypal.payment_created', [
            'tenant_id' => $tenant->id,
            'subject_type' => PayPalOrder::class,
            'subject_id' => $order->id,
            'description' => "PayPal order {$order->paypal_order_id} created for {$plan->name} ({$period->label()}), amount {$currency} {$amount}.",
        ]);

        return ['order' => $order, 'approve_url' => $result['approve_url']];
    }

    /**
     * Captures an approved order and, only once PayPal's own response says
     * the capture genuinely completed, records the payment and activates
     * the subscription. Safe to call more than once for the same order —
     * from the browser-return route, from a webhook retry, or both — the
     * conditional status update below ensures only the first caller to
     * observe a not-yet-completed order actually performs the activation.
     *
     * @return array{success: bool, message: string, order: ?PayPalOrder}
     */
    public function completeOrder(string $paypalOrderId): array
    {
        $order = PayPalOrder::where('paypal_order_id', $paypalOrderId)->first();

        if (! $order) {
            throw new PayPalException('Unknown PayPal order.');
        }

        if ($order->isCompleted()) {
            return ['success' => true, 'message' => 'This payment was already processed.', 'order' => $order];
        }

        $capture = $this->client->captureOrder($paypalOrderId);
        $status = $capture['status'] ?? null;

        if ($status !== 'COMPLETED') {
            // A conditional update (not a blind one) so a slower concurrent
            // caller can't stomp on a completion that a faster one already
            // recorded in between this check and here.
            PayPalOrder::where('id', $order->id)->where('status', '!=', 'completed')
                ->update(['status' => 'failed', 'raw_response' => $this->safeSubset($capture)]);

            AuditLog::record('paypal.payment_failed', [
                'tenant_id' => $order->tenant_id,
                'subject_type' => PayPalOrder::class,
                'subject_id' => $order->id,
                'description' => "PayPal order {$paypalOrderId} did not complete (status: {$status}).",
            ]);

            return ['success' => false, 'message' => 'This payment was not completed.', 'order' => $order->fresh()];
        }

        $captureId = $capture['purchase_units'][0]['payments']['captures'][0]['id'] ?? null;
        $payerEmail = $capture['payer']['email_address'] ?? null;

        // The atomic gate: only one concurrent caller's UPDATE can match
        // "status != completed" and actually change a row — everyone else
        // (webhook retry racing the browser return, or vice versa) sees 0
        // rows affected and falls through to "already processed".
        $claimed = PayPalOrder::where('id', $order->id)
            ->where('status', '!=', 'completed')
            ->update([
                'status' => 'completed',
                'paypal_capture_id' => $captureId,
                'payer_email' => $payerEmail,
                'raw_response' => $this->safeSubset($capture),
            ]);

        $order = $order->fresh();

        if ($claimed === 0) {
            return ['success' => true, 'message' => 'This payment was already processed.', 'order' => $order];
        }

        AuditLog::record('paypal.payment_captured', [
            'tenant_id' => $order->tenant_id,
            'subject_type' => PayPalOrder::class,
            'subject_id' => $order->id,
            'description' => "PayPal order {$paypalOrderId} captured (capture id {$captureId}).",
        ]);

        $subscription = $order->subscription;

        if ($subscription->subscription_plan_id !== $order->subscription_plan_id) {
            $this->subscriptions->changePlan($subscription, $order->subscription_plan_id);
        }

        $this->subscriptions->recordPayment(
            $subscription->fresh(),
            $order->amount,
            'PayPal',
            $captureId,
            "PayPal Order {$order->paypal_order_id}",
            null,
            [
                'paypal_order_id' => $order->paypal_order_id,
                'paypal_capture_id' => $captureId,
                'paypal_payer_email' => $payerEmail,
            ],
        );

        AuditLog::record('paypal.subscription_activated', [
            'tenant_id' => $order->tenant_id,
            'subject_type' => Subscription::class,
            'subject_id' => $subscription->id,
            'description' => "Subscription renewed/activated via PayPal order {$paypalOrderId}.",
        ]);

        return ['success' => true, 'message' => 'Payment completed — your subscription is now active.', 'order' => $order];
    }

    public function markCancelled(string $paypalOrderId): void
    {
        PayPalOrder::where('paypal_order_id', $paypalOrderId)
            ->where('status', 'created')
            ->update(['status' => 'cancelled']);
    }

    /**
     * @param  array<string, mixed>  $raw
     */
    public function markDenied(string $paypalOrderId, array $raw = []): void
    {
        $order = PayPalOrder::where('paypal_order_id', $paypalOrderId)->first();

        if (! $order || $order->isCompleted()) {
            return;
        }

        $order->update(['status' => 'denied', 'raw_response' => $this->safeSubset($raw)]);

        AuditLog::record('paypal.payment_denied', [
            'tenant_id' => $order->tenant_id,
            'subject_type' => PayPalOrder::class,
            'subject_id' => $order->id,
            'description' => "PayPal order {$paypalOrderId} was denied.",
        ]);
    }

    /**
     * Keeps only fields useful for support/audit — no payment_source
     * (card/bank details) or any other payer PII beyond the email address
     * already stored in its own column.
     *
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    protected function safeSubset(array $response): array
    {
        return [
            'id' => $response['id'] ?? null,
            'status' => $response['status'] ?? null,
            'captures' => collect($response['purchase_units'][0]['payments']['captures'] ?? [])
                ->map(fn ($c) => ['id' => $c['id'] ?? null, 'status' => $c['status'] ?? null])
                ->all(),
        ];
    }
}
