<?php

use App\Enums\SubscriptionStatus;
use App\Enums\TenantStatus;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Business Details')] class extends Component
{
    public Tenant $business;

    public bool $showPaymentModal = false;

    public string $payment_amount = '';

    public string $payment_method_label = 'Cash';

    public string $payment_reference = '';

    public string $payment_notes = '';

    public string $selected_plan_id = '';

    public function mount(string $tenant): void
    {
        // Type-hinted as string, not Tenant, so Laravel's implicit route
        // binding (which excludes soft-deleted models by default) never
        // gets a chance to 404 before we can look the tenant up ourselves
        // including trashed ones — an admin must still be able to view a
        // soft-deleted business's record.
        $this->business = Tenant::withTrashed()->with([
            'memberships.user',
            'subscriptions' => fn ($q) => $q->latest('id')->limit(1),
            'subscriptions.plan',
            'billingPayments' => fn ($q) => $q->latest('paid_at')->limit(10),
        ])->where('uuid', $tenant)->firstOrFail();

        $this->selected_plan_id = (string) $this->business->currentSubscription()?->subscription_plan_id;
    }

    public function getOwnerProperty()
    {
        return $this->business->memberships->firstWhere('role', \App\Enums\TenantMembershipRole::Owner)?->user;
    }

    public function getSubscriptionProperty()
    {
        return $this->business->currentSubscription();
    }

    public function getPlansProperty()
    {
        return SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    protected function refreshTenant(): void
    {
        $this->business = $this->business->fresh(['memberships.user', 'subscriptions.plan', 'billingPayments']);
    }

    public function suspendBusiness(): void
    {
        $this->business->update(['status' => TenantStatus::Suspended]);
        $this->refreshTenant();
        session()->flash('status', 'Business suspended.');
    }

    public function reactivateBusiness(): void
    {
        $this->business->update(['status' => TenantStatus::Active]);
        $this->refreshTenant();
        session()->flash('status', 'Business reactivated.');
    }

    public function deleteBusiness(): void
    {
        $this->business->delete();
        $this->redirectRoute('admin.businesses.index', navigate: true);
    }

    public function changePlan(): void
    {
        $this->validate(['selected_plan_id' => ['required', 'exists:subscription_plans,id']]);

        $subscription = $this->subscription;

        if ($subscription) {
            $subscription->update(['subscription_plan_id' => $this->selected_plan_id]);
        }

        $this->refreshTenant();
        session()->flash('status', 'Plan updated.');
    }

    public function subscriptionAction(string $action, SubscriptionService $service): void
    {
        $subscription = $this->subscription;

        if (! $subscription) {
            return;
        }

        match ($action) {
            'activate' => $service->activate($subscription),
            'renew' => $service->renew($subscription),
            'extend' => $service->extend($subscription, 30),
            'expire' => $service->expire($subscription),
            'suspend' => $service->suspend($subscription),
            'cancel' => $service->cancel($subscription),
            default => null,
        };

        $this->refreshTenant();
        session()->flash('status', 'Subscription updated.');
    }

    public function recordPayment(SubscriptionService $service): void
    {
        $this->validate([
            'payment_amount' => ['required', 'numeric', 'min:1'],
            'payment_method_label' => ['required', 'string', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'payment_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $subscription = $this->subscription;

        if ($subscription) {
            $service->recordPayment(
                $subscription,
                (int) round($this->payment_amount),
                $this->payment_method_label,
                $this->payment_reference ?: null,
                $this->payment_notes ?: null,
                Auth::user(),
            );
        }

        $this->showPaymentModal = false;
        $this->reset(['payment_amount', 'payment_reference', 'payment_notes']);
        $this->refreshTenant();
        session()->flash('status', 'Payment recorded and subscription renewed.');
    }
}; ?>

<div class="max-w-4xl space-y-6">
    <div class="flex items-center gap-3">
        <a href="{{ route('admin.businesses.index') }}" class="text-muted hover:text-ink">
            <x-lucide-arrow-left class="h-5 w-5" />
        </a>
        <h1 class="text-2xl font-semibold text-ink">{{ $business->name }}</h1>
        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $business->status->badgeClasses() }}">{{ $business->status->label() }}</span>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h3 class="text-sm font-medium text-muted">Business Information</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Slug</dt><dd class="text-ink">{{ $business->slug }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Timezone</dt><dd class="text-ink">{{ $business->timezone }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Joined</dt><dd class="text-ink">{{ $business->created_at->format('M j, Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Users</dt><dd class="text-ink">{{ $business->memberships->count() }}</dd></div>
            </dl>

            <div class="mt-4 flex gap-2">
                @if ($business->status->value === 'suspended')
                    <button type="button" wire:click="reactivateBusiness" class="rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Reactivate Business
                    </button>
                @else
                    <button type="button" wire:click="suspendBusiness" wire:confirm="Suspend this business? Its users will be unable to log in." class="rounded-lg border border-danger-500 px-3 py-2 text-xs font-semibold text-danger-500 hover:bg-red-50">
                        Suspend Business
                    </button>
                @endif
                @unless ($business->trashed())
                    <button type="button" wire:click="deleteBusiness" wire:confirm="Soft-delete this business? This can be reversed by a developer if needed." class="rounded-lg border border-hairline px-3 py-2 text-xs font-semibold text-muted hover:bg-app-bg">
                        Delete Business
                    </button>
                @endunless
            </div>
        </div>

        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h3 class="text-sm font-medium text-muted">Owner</h3>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Name</dt><dd class="text-ink">{{ $this->owner?->name ?? '—' }}</dd></div>
                <div class="flex justify-between"><dt class="text-muted">Email</dt><dd class="text-ink">{{ $this->owner?->email ?? '—' }}</dd></div>
            </dl>

            <h3 class="mt-6 text-sm font-medium text-muted">All Members</h3>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($business->memberships as $membership)
                    <li class="flex justify-between">
                        <span class="text-ink">{{ $membership->user->name }}</span>
                        <span class="text-muted">{{ $membership->role->label() }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-medium text-muted">Subscription</h3>
            @if ($this->subscription)
                <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $this->subscription->status->badgeClasses() }}">{{ $this->subscription->status->label() }}</span>
            @endif
        </div>

        @if ($this->subscription)
            <dl class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                <div><dt class="text-muted">Plan</dt><dd class="text-ink">{{ $this->subscription->plan->name }}</dd></div>
                <div><dt class="text-muted">Billing</dt><dd class="text-ink">{{ $this->subscription->billing_period->label() }}</dd></div>
                <div><dt class="text-muted">Period Start</dt><dd class="text-ink">{{ $this->subscription->current_period_start?->format('M j, Y') ?? '—' }}</dd></div>
                <div><dt class="text-muted">Period End</dt><dd class="text-ink">{{ $this->subscription->current_period_end?->format('M j, Y') ?? '—' }}</dd></div>
            </dl>

            <div class="mt-4 flex flex-wrap gap-2">
                <button type="button" wire:click="subscriptionAction('activate')" class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">Activate</button>
                <button type="button" wire:click="subscriptionAction('extend')" class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">Extend 30 Days</button>
                <button type="button" wire:click="subscriptionAction('renew')" class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">Renew</button>
                <button type="button" wire:click="subscriptionAction('expire')" class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">Expire</button>
                <button type="button" wire:click="subscriptionAction('suspend')" class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-medium text-ink hover:bg-app-bg">Suspend</button>
                <button type="button" wire:click="subscriptionAction('cancel')" wire:confirm="Cancel this subscription?" class="rounded-lg border border-danger-500 px-3 py-1.5 text-xs font-medium text-danger-500 hover:bg-red-50">Cancel</button>
                <button type="button" wire:click="$set('showPaymentModal', true)" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">Record Payment</button>
            </div>

            <div class="mt-4 flex items-end gap-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-muted">Change Plan</label>
                    <select wire:model="selected_plan_id" class="rounded-lg border border-hairline px-3 py-2 text-sm">
                        @foreach ($this->plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="button" wire:click="changePlan" class="rounded-lg border border-hairline px-3 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                    Update Plan
                </button>
            </div>
        @else
            <p class="mt-3 text-sm text-muted">No subscription record.</p>
        @endif
    </div>

    <div class="rounded-xl border border-hairline bg-surface">
        <div class="border-b border-hairline p-4">
            <h3 class="text-sm font-medium text-ink">Billing History</h3>
        </div>
        <table class="w-full text-left text-sm">
            <thead class="text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Amount</th>
                    <th class="px-4 py-2">Method</th>
                    <th class="px-4 py-2">Reference</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($business->billingPayments as $payment)
                    <tr>
                        <td class="px-4 py-2 text-muted">{{ $payment->paid_at->format('M j, Y') }}</td>
                        <td class="px-4 py-2 text-ink">₱{{ number_format($payment->amount) }}</td>
                        <td class="px-4 py-2 text-muted">{{ $payment->payment_method_label }}</td>
                        <td class="px-4 py-2 text-muted">{{ $payment->reference ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-muted">No payments recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($showPaymentModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Record Payment</h2>
                <p class="mt-1 text-sm text-muted">This renews the subscription by one billing period.</p>

                <form wire:submit="recordPayment" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Amount (₱)</label>
                        <input wire:model="payment_amount" type="number" min="1" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('payment_amount') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Payment Method</label>
                        <input wire:model="payment_method_label" type="text" placeholder="Cash, GCash, Bank Transfer..." class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Reference (optional)</label>
                        <input wire:model="payment_reference" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Notes (optional)</label>
                        <textarea wire:model="payment_notes" rows="2" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm"></textarea>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showPaymentModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Record Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
