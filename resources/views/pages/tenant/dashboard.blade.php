<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Dashboard')] class extends Component
{
    //
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Good morning, {{ auth()->user()->name }}</h1>
        <p class="mt-1 text-sm text-muted">Here's what's happening in your business today.</p>
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <p class="text-sm text-muted">
            The dashboard, POS, and other operational modules will be built in the next development stages.
            The foundation &mdash; authentication, tenant isolation, roles, and the shared layout &mdash; is now in place.
        </p>
    </div>
</div>
