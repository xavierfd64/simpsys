<?php

use App\Models\Tenant;
use App\Support\BillingStatement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Statement of Account')] class extends Component
{
    public Tenant $business;

    public function mount(string $tenant): void
    {
        $this->business = Tenant::withTrashed()
            ->with(['subscriptions' => fn ($q) => $q->latest('id')->limit(1), 'subscriptions.plan', 'memberships.user'])
            ->where('uuid', $tenant)
            ->firstOrFail();
    }

    public function getStatementProperty(): ?BillingStatement
    {
        $subscription = $this->business->currentSubscription();

        return $subscription ? BillingStatement::for($subscription) : null;
    }
}; ?>

<div>
    @if ($this->statement)
        @include('partials.billing-statement', ['tenant' => $this->business, 'statement' => $this->statement, 'backUrl' => route('admin.businesses.show', $this->business)])
    @else
        <div class="rounded-xl border border-hairline bg-surface p-8 text-center text-muted">
            No active subscription found for this business.
        </div>
    @endif
</div>
