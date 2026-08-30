<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Mail\BillingReminderMail;
use App\Mail\PaymentOverdueMail;
use App\Models\Subscription;
use App\Support\BillingStatement;
use App\Support\SafeMailer;

/**
 * Runs (at most once a day, via OpportunisticScheduler) to send upcoming-
 * renewal reminders and expire subscriptions whose period has lapsed
 * without a recorded payment. Purely date-driven — no payment-gateway
 * confirmation is assumed or required, since expiring on a passed due
 * date is a fact about time, not a claim about payment.
 */
class BillingReminderService
{
    public const REMINDER_DAYS_BEFORE_DUE = 7;

    /**
     * Emails every subscription in good standing whose period ends within
     * the reminder window, skipping any that already got a reminder for
     * this exact period (tracked via last_reminder_period_end so renewing
     * automatically makes a subscription eligible again next cycle).
     */
    public function sendDueReminders(): int
    {
        $windowEnd = now()->addDays(self::REMINDER_DAYS_BEFORE_DUE)->endOfDay();

        $due = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Trial, SubscriptionStatus::Active])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', $windowEnd)
            ->where('current_period_end', '>=', now())
            ->with(['tenant', 'plan'])
            ->get()
            ->filter(fn (Subscription $s) => $s->last_reminder_period_end?->toDateString() !== $s->current_period_end->toDateString());

        foreach ($due as $subscription) {
            $owner = $subscription->tenant->owner()?->user;
            $statement = BillingStatement::for($subscription);

            if (SafeMailer::send($owner?->email, new BillingReminderMail($statement))) {
                $subscription->update(['last_reminder_period_end' => $subscription->current_period_end->toDateString()]);
            }
        }

        return $due->count();
    }

    /**
     * Auto-expires subscriptions whose period has already ended — the
     * business's own root tenant only; branches don't carry a subscription
     * of their own (see Tenant::isOperational(), which already blocks a
     * branch once its business's subscription lapses).
     */
    public function expireLapsedSubscriptions(): int
    {
        $lapsed = Subscription::query()
            ->whereIn('status', [SubscriptionStatus::Trial, SubscriptionStatus::Active])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->with('tenant')
            ->get();

        foreach ($lapsed as $subscription) {
            $subscription->update(['status' => SubscriptionStatus::Expired]);

            $owner = $subscription->tenant->owner()?->user;
            SafeMailer::send($owner?->email, new PaymentOverdueMail($subscription->tenant));
        }

        return $lapsed->count();
    }
}
