<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Admin Dashboard')] class extends Component
{
    //
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Platform Overview</h1>
        <p class="mt-1 text-sm text-muted">Business, subscription, and plan management will be built in a later stage.</p>
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <p class="text-sm text-muted">
            Super Admin authentication and authorization are in place. Platform management modules come next.
        </p>
    </div>
</div>
