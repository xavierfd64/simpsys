<?php

use App\Enums\BillingPeriod;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Services\PayPalCheckoutService;
use App\Services\TenantContext;
use App\Support\BillingStatement;
use App\Support\PayPalException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Billing')] class extends Component
{
    public string $selected_plan_id = '';

    public string $selected_period = 'monthly';

    public string $payment_method = '';

    public ?string $paypal_error = null;

    public function mount(): void
    {
        $subscription = $this->tenant->latestSubscription();

        $this->selected_plan_id = (string) ($subscription?->subscription_plan_id ?? '');
        $this->selected_period = $subscription?->billing_period->value ?? 'monthly';

        $settings = PlatformSetting::current();
        $this->payment_method = $settings->isPayPalConfigured() ? 'paypal' : 'manual';
    }

    public function getTenantProperty()
    {
        return app(TenantContext::class)->tenant()->businessRoot();
    }

    public function getStatementProperty(): ?BillingStatement
    {
        // latestSubscription(), not currentSubscription() — an owner whose
        // account is suspended/expired should still see their last real
        // statement (with the correct status on it), not a "no
        // subscription" message that reads like they never had one.
        $subscription = $this->tenant->latestSubscription();

        return $subscription ? BillingStatement::for($subscription) : null;
    }

    public function getPlansProperty()
    {
        return SubscriptionPlan::query()->where('is_active', true)->orderBy('sort_order')->get();
    }

    public function getSelectedPlanProperty(): ?SubscriptionPlan
    {
        return $this->plans->firstWhere('id', (int) $this->selected_plan_id);
    }

    public function getPriceProperty(): int
    {
        $plan = $this->selectedPlan;

        return $plan ? $plan->priceFor(BillingPeriod::from($this->selected_period)) : 0;
    }

    public function getSettingsProperty(): PlatformSetting
    {
        return PlatformSetting::current();
    }

    /**
     * Creates a PayPal order for the selected plan/period — the amount is
     * always computed here, server-side, from the plan the admin
     * configured; the browser never supplies (and this method never
     * trusts) a price.
     */
    public function payWithPayPal(PayPalCheckoutService $checkout)
    {
        $this->paypal_error = null;

        $this->validate([
            'selected_plan_id' => ['required', 'exists:subscription_plans,id'],
            'selected_period' => ['required', 'in:monthly,yearly'],
        ]);

        if (! $this->settings->isPayPalConfigured()) {
            $this->paypal_error = 'PayPal is not available right now. Please choose another payment method.';

            return;
        }

        $subscription = $this->tenant->latestSubscription();

        if (! $subscription) {
            $this->paypal_error = 'No subscription record found for this business.';

            return;
        }

        try {
            $result = $checkout->createOrder(
                tenant: $this->tenant,
                subscription: $subscription,
                plan: $this->selectedPlan,
                period: BillingPeriod::from($this->selected_period),
                returnUrl: route('app.billing.paypal.return'),
                cancelUrl: route('app.billing.paypal.cancel'),
            );
        } catch (PayPalException $e) {
            $this->paypal_error = $e->getMessage();

            return;
        }

        return redirect()->away($result['approve_url']);
    }
}; ?>

<div class="space-y-6">
    @if (session('billing_status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('billing_status') }}</div>
    @endif
    @if (session('billing_error'))
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">{{ session('billing_error') }}</div>
    @endif
    @if ($paypal_error)
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">{{ $paypal_error }}</div>
    @endif

    @if ($this->plans->isNotEmpty())
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h2 class="text-base font-semibold text-ink">Upgrade or Renew Your Plan</h2>
            <p class="mt-1 text-sm text-muted">Select a plan and billing period, then choose how you'd like to pay.</p>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Plan</label>
                    <select wire:model.live="selected_plan_id" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                        @foreach ($this->plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                        @endforeach
                    </select>
                    @error('selected_plan_id') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Billing Period</label>
                    <select wire:model.live="selected_period" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>
            </div>

            <div class="mt-4 rounded-lg bg-app-bg p-4">
                <p class="text-xs font-medium uppercase tracking-wide text-muted">Amount Due</p>
                <p class="mt-1 text-2xl font-semibold text-ink">₱{{ number_format($this->price) }} <span class="text-sm font-normal text-muted">/ {{ $selected_period }}</span></p>
            </div>

            <div class="mt-4">
                <p class="mb-2 text-sm font-medium text-ink">Payment Method</p>
                <div class="space-y-2">
                    @if ($this->settings->manual_payment_enabled)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-hairline px-4 py-3 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input wire:model.live="payment_method" type="radio" value="manual" class="h-4 w-4 text-primary-600">
                            <span class="text-sm font-medium text-ink">Manual / Fund Transfer</span>
                        </label>
                    @endif
                    @if ($this->settings->isPayPalConfigured())
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-hairline px-4 py-3 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                            <input wire:model.live="payment_method" type="radio" value="paypal" class="h-4 w-4 text-primary-600">
                            <span class="text-sm font-medium text-ink">PayPal</span>
                        </label>
                    @endif
                </div>
            </div>

            @if ($payment_method === 'manual' && $this->settings->manual_payment_enabled)
                <div class="mt-4 rounded-lg border border-hairline bg-app-bg p-4 text-sm text-muted">
                    <p class="font-medium text-ink">How to pay manually</p>
                    <p class="mt-1">Please arrange payment for ₱{{ number_format($this->price) }} via bank transfer or GCash, then contact us so we can confirm it and activate your plan.</p>
                    @if ($this->settings->support_email || $this->settings->support_phone)
                        <p class="mt-2">
                            @if ($this->settings->support_email) <a href="mailto:{{ $this->settings->support_email }}" class="text-primary-600 hover:underline">{{ $this->settings->support_email }}</a> @endif
                            @if ($this->settings->support_phone) · {{ $this->settings->support_phone }} @endif
                        </p>
                    @endif
                </div>
            @elseif ($payment_method === 'paypal' && $this->settings->isPayPalConfigured())
                <button type="button" wire:click="payWithPayPal"
                        class="mt-4 flex w-full items-center justify-center gap-2 rounded-lg bg-[#ffc439] px-4 py-3 text-sm font-semibold text-[#003087] hover:bg-[#f0b93d]"
                        wire:loading.attr="disabled" wire:target="payWithPayPal">
                    <span wire:loading.remove wire:target="payWithPayPal">Pay with PayPal</span>
                    <span wire:loading wire:target="payWithPayPal">Redirecting to PayPal&hellip;</span>
                </button>
            @endif
        </div>
    @endif

    @if ($this->statement)
        @include('partials.billing-statement', ['tenant' => $this->tenant, 'statement' => $this->statement, 'backUrl' => route('app.dashboard')])
    @else
        <div class="rounded-xl border border-hairline bg-surface p-8 text-center text-muted">
            No subscription record found.
        </div>
    @endif
</div>
