<?php

use App\Services\TenantContext;
use App\Support\BillingStatement;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Billing')] class extends Component
{
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
}; ?>

<div>
    @if ($this->statement)
        @include('partials.billing-statement', ['tenant' => $this->tenant, 'statement' => $this->statement, 'backUrl' => route('app.dashboard')])
    @else
        <div class="rounded-xl border border-hairline bg-surface p-8 text-center text-muted">
            No subscription record found.
        </div>
    @endif
</div>
