<?php

namespace App\Support;

use App\Models\Subscription;
use Illuminate\Support\Collection;

/**
 * Computes a subscription's current billing period into a printable/
 * emailable statement — shared by the web statement view and the billing
 * reminder email so both always agree on the numbers.
 */
class BillingStatement
{
    public function __construct(
        public Subscription $subscription,
        public string $number,
        public int $charges,
        public int $paid,
        public int $balance,
        public string $status,
        public Collection $payments,
    ) {}

    public static function for(Subscription $subscription): self
    {
        $charges = $subscription->plan->priceFor($subscription->billing_period);

        // BillingPayment::paid_at is a `date` cast, which stores a
        // zero-time datetime in the DB (see the CLAUDE.md note on
        // Expense::expense_date) — whereDate(), not whereBetween() with
        // bare date strings, is what correctly matches it.
        $payments = $subscription->tenant->billingPayments()
            ->whereDate('paid_at', '>=', $subscription->current_period_start->toDateString())
            ->whereDate('paid_at', '<=', $subscription->current_period_end->toDateString())
            ->orderByDesc('paid_at')
            ->get();

        $paid = (int) $payments->sum('amount');
        $balance = max(0, $charges - $paid);

        $status = match (true) {
            $balance <= 0 => 'paid',
            now()->greaterThan($subscription->current_period_end) => 'overdue',
            default => 'due',
        };

        $number = sprintf('STMT-%06d-%s', $subscription->id, $subscription->current_period_start->format('Ym'));

        return new self($subscription, $number, $charges, $paid, $balance, $status, $payments);
    }
}
