<?php

use App\Enums\BranchStatus;
use App\Models\Tenant;
use App\Services\BranchService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts.admin')] #[Title('Branches')] class extends Component
{
    use WithPagination;

    public string $status = 'pending_approval';

    public ?int $rejectingId = null;

    public string $rejection_reason = '';

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function getBranchesProperty()
    {
        return Tenant::query()
            ->whereNotNull('parent_tenant_id')
            ->with(['parent', 'manager'])
            ->when($this->status, fn ($q) => $q->where('branch_status', $this->status))
            ->latest('id')
            ->paginate(15);
    }

    public function approve(int $tenantId, BranchService $branchService): void
    {
        $branch = Tenant::findOrFail($tenantId);
        $branchService->approve($branch, Auth::user());
        session()->flash('status', "Branch \"{$branch->name}\" approved.");
    }

    public function openReject(int $tenantId): void
    {
        $this->rejectingId = $tenantId;
        $this->rejection_reason = '';
    }

    public function reject(BranchService $branchService): void
    {
        $this->validate(['rejection_reason' => ['required', 'string', 'max:500']]);

        $branch = Tenant::findOrFail($this->rejectingId);
        $branchService->reject($branch, Auth::user(), $this->rejection_reason);

        $this->rejectingId = null;
        session()->flash('status', "Branch \"{$branch->name}\" rejected.");
    }

    public function suspend(int $tenantId, BranchService $branchService): void
    {
        $branch = Tenant::findOrFail($tenantId);
        $branchService->suspend($branch);
        session()->flash('status', "Branch \"{$branch->name}\" suspended.");
    }

    public function reactivate(int $tenantId, BranchService $branchService): void
    {
        $branch = Tenant::findOrFail($tenantId);
        $branchService->reactivate($branch);
        session()->flash('status', "Branch \"{$branch->name}\" reactivated.");
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Branches</h1>
        <p class="mt-1 text-sm text-muted">Review and approve branches submitted by business owners.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="flex flex-wrap gap-3">
        <select wire:model.live="status" class="rounded-lg border border-hairline px-3 py-2 text-sm">
            <option value="">All Status</option>
            @foreach (BranchStatus::cases() as $case)
                <option value="{{ $case->value }}">{{ $case->label() }}</option>
            @endforeach
        </select>
    </div>

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[820px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Business</th>
                    <th class="px-4 py-3">Branch</th>
                    <th class="px-4 py-3">Manager</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Submitted</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @forelse ($this->branches as $branch)
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('admin.businesses.show', $branch->parent) }}" class="text-ink hover:text-primary-600">
                                {{ $branch->parent?->name }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-ink">
                            {{ $branch->name }}
                            @if ($branch->branch_code)
                                <span class="text-muted">({{ $branch->branch_code }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $branch->manager?->name ?: '—' }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-1 text-xs font-medium {{ $branch->branch_status->badgeClasses() }}">
                                {{ $branch->branch_status->label() }}
                            </span>
                            @if ($branch->branch_status === BranchStatus::Rejected && $branch->branch_rejection_reason)
                                <p class="mt-1 text-xs text-muted">{{ $branch->branch_rejection_reason }}</p>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $branch->created_at->format('M j, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @if ($branch->branch_status === BranchStatus::PendingApproval)
                                <button type="button" wire:click="approve({{ $branch->id }})"
                                        class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">
                                    Approve
                                </button>
                                <button type="button" wire:click="openReject({{ $branch->id }})"
                                        class="ml-1 rounded-lg border border-hairline px-3 py-1.5 text-xs font-semibold text-danger-500 hover:bg-app-bg">
                                    Reject
                                </button>
                            @elseif ($branch->branch_status === BranchStatus::Active)
                                <button type="button" wire:click="suspend({{ $branch->id }})" wire:confirm="Suspend this branch?"
                                        class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-semibold text-danger-500 hover:bg-app-bg">
                                    Suspend
                                </button>
                            @elseif ($branch->branch_status === BranchStatus::Suspended)
                                <button type="button" wire:click="reactivate({{ $branch->id }})"
                                        class="rounded-lg border border-hairline px-3 py-1.5 text-xs font-semibold text-ink hover:bg-app-bg">
                                    Reactivate
                                </button>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-muted">No branches found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $this->branches->links() }}

    @if ($rejectingId)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Reject Branch</h2>
                <form wire:submit="reject" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Reason</label>
                        <textarea wire:model="rejection_reason" rows="3"
                                  class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500"></textarea>
                        @error('rejection_reason') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-3">
                        <button type="button" wire:click="$set('rejectingId', null)" class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-danger-500 px-4 py-2.5 text-sm font-semibold text-white hover:opacity-90">
                            Confirm Reject
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
