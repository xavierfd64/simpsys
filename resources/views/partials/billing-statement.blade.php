@php
    $platform = \App\Models\PlatformSetting::current();
    $owner = $tenant->owner()?->user;
    $statusClasses = match ($statement->status) {
        'paid' => 'bg-green-50 text-green-700',
        'overdue' => 'bg-red-50 text-red-700',
        default => 'bg-amber-50 text-amber-700',
    };
@endphp
<style>
    @media print {
        .no-print { display: none !important; }
    }
</style>

<div class="mx-auto max-w-3xl space-y-6 print:max-w-none">
    <div class="no-print flex items-center justify-between">
        <a href="{{ $backUrl }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">&larr; Back</a>
        <button type="button" onclick="window.print()" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
            Print / Save as PDF
        </button>
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-8 print:border-0 print:p-0">
        <div class="flex items-start justify-between border-b border-hairline pb-6">
            <div class="flex items-center gap-2">
                @if ($platform->logo_path)
                    <img src="{{ \App\Support\TenantStorage::url($platform->logo_path) }}" class="h-8 w-8 rounded object-cover" alt="{{ $platform->displayName() }}">
                @else
                    <x-lucide-store class="h-7 w-7 text-primary-600" />
                @endif
                <span class="text-lg font-semibold text-ink">{{ $platform->displayName() }}</span>
            </div>
            <div class="text-right">
                <h1 class="text-xl font-semibold text-ink">Statement of Account</h1>
                <p class="text-sm text-muted">{{ $statement->number }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 sm:grid-cols-2">
            <div>
                <p class="text-xs font-medium uppercase text-muted">Billed To</p>
                <p class="mt-1 font-medium text-ink">{{ $tenant->name }}</p>
                <p class="text-sm text-muted">{{ $owner?->email }}</p>
            </div>
            <div class="sm:text-right">
                <p class="text-xs font-medium uppercase text-muted">Status</p>
                <span class="mt-1 inline-block rounded-full px-2.5 py-1 text-xs font-medium {{ $statusClasses }}">
                    {{ ucfirst($statement->status) }}
                </span>
            </div>
        </div>

        <div class="mt-6 grid gap-4 rounded-lg bg-app-bg p-4 sm:grid-cols-3">
            <div>
                <p class="text-xs font-medium uppercase text-muted">Billing Period</p>
                <p class="mt-1 text-sm text-ink">{{ $statement->subscription->current_period_start->format('M j, Y') }} – {{ $statement->subscription->current_period_end->format('M j, Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase text-muted">Due Date</p>
                <p class="mt-1 text-sm text-ink">{{ $statement->subscription->current_period_end->format('M j, Y') }}</p>
            </div>
            <div>
                <p class="text-xs font-medium uppercase text-muted">Plan</p>
                <p class="mt-1 text-sm text-ink">{{ $statement->subscription->plan->name }} ({{ ucfirst($statement->subscription->billing_period->value) }})</p>
            </div>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-medium text-ink">Charges</h3>
            <table class="mt-2 w-full text-left text-sm">
                <thead class="border-b border-hairline text-xs font-medium uppercase text-muted">
                    <tr><th class="py-2">Description</th><th class="py-2 text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    <tr>
                        <td class="py-2 text-ink">{{ $statement->subscription->plan->name }} — {{ ucfirst($statement->subscription->billing_period->value) }} subscription</td>
                        <td class="py-2 text-right text-ink">₱{{ number_format($statement->charges) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="mt-6">
            <h3 class="text-sm font-medium text-ink">Payments</h3>
            <table class="mt-2 w-full text-left text-sm">
                <thead class="border-b border-hairline text-xs font-medium uppercase text-muted">
                    <tr><th class="py-2">Date</th><th class="py-2">Method</th><th class="py-2">Reference</th><th class="py-2 text-right">Amount</th></tr>
                </thead>
                <tbody class="divide-y divide-hairline">
                    @forelse ($statement->payments as $payment)
                        <tr>
                            <td class="py-2 text-muted">{{ $payment->paid_at->format('M j, Y') }}</td>
                            <td class="py-2 text-muted">{{ $payment->payment_method_label }}</td>
                            <td class="py-2 text-muted">{{ $payment->reference ?: '—' }}</td>
                            <td class="py-2 text-right text-ink">₱{{ number_format($payment->amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-4 text-center text-muted">No payments recorded for this period yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-6 flex justify-end">
            <div class="w-full max-w-xs space-y-1 text-sm">
                <div class="flex justify-between"><span class="text-muted">Total Charges</span><span class="text-ink">₱{{ number_format($statement->charges) }}</span></div>
                <div class="flex justify-between"><span class="text-muted">Total Paid</span><span class="text-ink">₱{{ number_format($statement->paid) }}</span></div>
                <div class="flex justify-between border-t border-hairline pt-1 font-semibold"><span class="text-ink">Balance Due</span><span class="text-ink">₱{{ number_format($statement->balance) }}</span></div>
            </div>
        </div>

        @if ($platform->support_email || $platform->support_phone)
            <div class="mt-8 border-t border-hairline pt-4 text-xs text-muted">
                Questions about this statement?
                @if ($platform->support_email) <a href="mailto:{{ $platform->support_email }}" class="text-primary-600">{{ $platform->support_email }}</a> @endif
                @if ($platform->support_phone) · {{ $platform->support_phone }} @endif
            </div>
        @endif
    </div>
</div>
