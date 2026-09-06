<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Mail\PaymentReceivedMail;
use App\Models\BillingPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SafeMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The single place a subscription's status is allowed to change — every
 * method here keeps the owning Tenant's own `status` column in lockstep
 * (see syncTenantStatus()) so there is exactly one reliable answer to "is
 * this account trial/active/expired/suspended/cancelled" no matter which
 * of the two columns a given screen happens to read. Before this, a
 * business that had actually been activated (via activate()/renew()/
 * recordPayment()) still showed "Trial" everywhere that reads
 * Tenant::status (the business list, the business detail page's own
 * top badge, IdentifyTenant's login gate) because only the Subscription
 * row's status was ever updated — the two had no mechanism keeping them
 * in sync at all.
 */
class SubscriptionService
{
    public function activate(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $subscription->current_period_start ?? now(),
            'current_period_end' => $subscription->current_period_end ?? $this->periodEnd($subscription),
        ]);

        $this->syncTenantStatus($subscription, SubscriptionStatus::Active);

        return $subscription->fresh();
    }

    public function extend(Subscription $subscription, int $days): Subscription
    {
        $base = $subscription->current_period_end && $subscription->current_period_end->isFuture()
            ? $subscription->current_period_end
            : now();

        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_end' => $base->copy()->addDays($days),
        ]);

        $this->syncTenantStatus($subscription, SubscriptionStatus::Active);

        return $subscription->fresh();
    }

    public function renew(Subscription $subscription): Subscription
    {
        $days = $subscription->billing_period->value === 'yearly' ? 365 : 30;

        return $this->extend($subscription, $days);
    }

    public function expire(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Expired]);
        $this->syncTenantStatus($subscription, SubscriptionStatus::Expired);

        return $subscription->fresh();
    }

    public function suspend(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Suspended]);
        $this->syncTenantStatus($subscription, SubscriptionStatus::Suspended);

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        $this->syncTenantStatus($subscription, SubscriptionStatus::Cancelled);

        return $subscription->fresh();
    }

    /**
     * A branch (non-root Tenant) never owns a Subscription row of its own
     * — only a business's root tenant does — so this never runs against
     * branch-specific state; `Tenant::isOperational()` layers branch
     * approval and the parent's own status on top independently.
     */
    protected function syncTenantStatus(Subscription $subscription, SubscriptionStatus $status): void
    {
        $subscription->tenant->update(['status' => TenantStatus::from($status->value)]);
    }

    /**
     * Record a manually-confirmed external payment (GCash, bank transfer,
     * etc. verified outside the app) and extend the subscription by one
     * billing period from it.
     */
    public function recordPayment(
        Subscription $subscription,
        int $amount,
        string $paymentMethodLabel,
        ?string $reference,
        ?string $notes,
        ?User $admin,
    ): BillingPayment {
        $payment = DB::transaction(function () use ($subscription, $amount, $paymentMethodLabel, $reference, $notes, $admin) {
            $payment = BillingPayment::create([
                'tenant_id' => $subscription->tenant_id,
                'subscription_id' => $subscription->id,
                'recorded_by' => $admin?->id,
                'amount' => $amount,
                'payment_method_label' => $paymentMethodLabel,
                'reference' => $reference,
                'paid_at' => now()->toDateString(),
                'notes' => $notes,
            ]);

            $this->renew($subscription);

            return $payment;
        });

        $owner = $subscription->tenant->owner()?->user;
        SafeMailer::send($owner?->email, new PaymentReceivedMail($payment));

        return $payment;
    }

    protected function periodEnd(Subscription $subscription): Carbon
    {
        $days = $subscription->billing_period->value === 'yearly' ? 365 : 30;

        return now()->addDays($days);
    }
}
