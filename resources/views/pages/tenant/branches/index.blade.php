<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\BranchService;
use App\Services\TenantContext;
use App\Support\TenantStorage;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.app')] #[Title('Branches')] class extends Component
{
    use WithFileUploads;

    public bool $showCreateModal = false;

    public string $name = '';

    public string $branch_code = '';

    public string $branch_address = '';

    public string $branch_contact_number = '';

    public string $branch_contact_email = '';

    public ?int $manager_user_id = null;

    public $logo = null;

    public function getBusinessProperty(): Tenant
    {
        return app(TenantContext::class)->tenant()->businessRoot();
    }

    public function getBranchesProperty()
    {
        $root = $this->business->loadMissing('manager');

        return collect([$root])
            ->concat($root->branches()->with('manager')->orderBy('id')->get());
    }

    public function getAvailableManagersProperty()
    {
        $tenantIds = $this->branches->pluck('id');

        return User::query()
            ->whereHas('memberships', fn ($q) => $q->whereIn('tenant_id', $tenantIds))
            ->orderBy('name')
            ->get();
    }

    public function openCreate(): void
    {
        $this->reset(['name', 'branch_code', 'branch_address', 'branch_contact_number', 'branch_contact_email', 'manager_user_id', 'logo']);
        $this->resetValidation();
        $this->manager_user_id = Auth::id();
        $this->showCreateModal = true;
    }

    public function create(BranchService $branchService): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'branch_code' => ['nullable', 'string', 'max:50'],
            'branch_address' => ['nullable', 'string', 'max:255'],
            'branch_contact_number' => ['nullable', 'string', 'max:50'],
            'branch_contact_email' => ['nullable', 'email', 'max:255'],
            'manager_user_id' => ['nullable', 'integer', 'in:'.$this->availableManagers->pluck('id')->implode(',')],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $logoPath = $this->logo ? TenantStorage::storeImage($this->logo, 'branch-logos', $this->business) : null;

        $branchService->createBranch($this->business, Auth::user(), [
            'name' => $data['name'],
            'branch_code' => $data['branch_code'] ?: null,
            'branch_address' => $data['branch_address'] ?: null,
            'branch_contact_number' => $data['branch_contact_number'] ?: null,
            'branch_contact_email' => $data['branch_contact_email'] ?: null,
            'manager_user_id' => $data['manager_user_id'] ?: null,
            'logo_path' => $logoPath,
        ]);

        $this->showCreateModal = false;
        session()->flash('status', 'Branch submitted — it will become active once a Platform Admin approves it.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Branches</h1>
            <p class="mt-1 text-sm text-muted">Manage your business's locations. New branches need Platform Admin approval before they're active.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
            + Add Branch
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-hairline text-xs font-medium uppercase text-muted">
                <tr>
                    <th class="px-4 py-3">Branch</th>
                    <th class="px-4 py-3">Code</th>
                    <th class="px-4 py-3">Manager</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3">Created</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @foreach ($this->branches as $branch)
                    <tr>
                        <td class="px-4 py-3 font-medium text-ink">
                            {{ $branch->name }}
                            @if (! $branch->isBranch())
                                <span class="ml-1 rounded bg-app-bg px-1.5 py-0.5 text-xs font-normal text-muted">Main</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $branch->branch_code ?: '—' }}</td>
                        <td class="px-4 py-3 text-muted">{{ $branch->manager?->name ?: '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($branch->isBranch())
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $branch->branch_status->badgeClasses() }}">
                                    {{ $branch->branch_status->label() }}
                                </span>
                                @if ($branch->branch_status->value === 'rejected' && $branch->branch_rejection_reason)
                                    <p class="mt-1 text-xs text-muted">{{ $branch->branch_rejection_reason }}</p>
                                @endif
                            @else
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $branch->status->badgeClasses() }}">
                                    {{ $branch->status->label() }}
                                </span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-muted">{{ $branch->created_at->format('M j, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showCreateModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Add Branch</h2>

                <form wire:submit="create" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Branch Name</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Branch Code</label>
                            <input wire:model="branch_code" type="text" placeholder="BR-002" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @error('branch_code') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Manager</label>
                            <select wire:model="manager_user_id" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm">
                                @foreach ($this->availableManagers as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                            @error('manager_user_id') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Address</label>
                        <input wire:model="branch_address" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('branch_address') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Contact Number</label>
                            <input wire:model="branch_contact_number" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @error('branch_contact_number') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Contact Email</label>
                            <input wire:model="branch_contact_email" type="email" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                            @error('branch_contact_email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Branch Logo (optional)</label>
                        <input wire:model="logo" type="file" accept="image/*" class="w-full text-sm">
                        @error('logo') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showCreateModal', false)" class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700" wire:loading.attr="disabled" wire:target="create">
                            Submit for Approval
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
