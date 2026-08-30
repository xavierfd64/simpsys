<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Mail\PaymentReceivedMail;
use App\Models\BillingPayment;
use App\Models\Subscription;
use App\Models\User;
use App\Support\SafeMailer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriptionService
{
    public function activate(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Active,
            'current_period_start' => $subscription->current_period_start ?? now(),
            'current_period_end' => $subscription->current_period_end ?? $this->periodEnd($subscription),
        ]);

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

        return $subscription->fresh();
    }

    public function suspend(Subscription $subscription): Subscription
    {
        $subscription->update(['status' => SubscriptionStatus::Suspended]);

        return $subscription->fresh();
    }

    public function cancel(Subscription $subscription): Subscription
    {
        $subscription->update([
            'status' => SubscriptionStatus::Cancelled,
            'cancelled_at' => now(),
        ]);

        return $subscription->fresh();
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
