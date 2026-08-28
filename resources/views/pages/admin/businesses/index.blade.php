<?php

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Businesses')] class extends Component
{
    use WithPagination;

    public string $search = '';

    public string $status = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getBusinessesProperty()
    {
        return Tenant::query()
            ->withCount('memberships')
            ->with(['subscriptions' => fn ($q) => $q->latest('id')->limit(1), 'subscriptions.plan'])
            ->when($this->search, fn ($q) => $q->where('name', 'like', '%'.$this->search.'%'))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->latest('id')
            ->paginate(15);
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Businesses</h1>
        <p class="mt-1 text-sm text-muted">All tenants on the platform.</p>
    </div>

    <div class="flex flex-wrap gap-3">
        <input wire:model.live.debounce.300ms="search" type="text" placeholder="Search businesses..."
               class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm">
        <select wire:model.live="status" class="rounded-lg border border-hairline px-3 py-2 text-sm">
            <option value="">All Status</option>
            @foreach (TenantStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[720px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Business</th>
                    <th class="px-4 py-3">Plan</th>
                    <th class="px-4 py-3">Users</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Joined</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->businesses as $tenant)
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">{{ $tenant->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $tenant->subscriptions->first()?->plan?->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $tenant->memberships_count }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $tenant->status->badgeClasses() }}">
                                {{ $tenant->status->label() }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $tenant->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('admin.businesses.show', $tenant) }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">View</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-12 text-center text-muted">No businesses match.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->businesses->links() }}
</div>
