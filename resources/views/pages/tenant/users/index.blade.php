<?php

use App\Enums\TenantMembershipRole;
use App\Enums\TenantMembershipStatus;
use App\Models\TenantMembership;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.app')] #[Title('Users')] class extends Component
{
    public bool $showFormModal = false;

    public bool $showPasswordModal = false;

    public ?int $editingMembershipId = null;

    public ?int $resettingMembershipId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $role = 'cashier';

    public string $new_password = '';

    public function getMembershipsProperty()
    {
        return app(TenantContext::class)->tenant()->memberships()->with('user')->orderBy('id')->get();
    }

    public function getPlanProperty()
    {
        return app(TenantContext::class)->tenant()->currentSubscription()?->plan;
    }

    public function getAtUserLimitProperty(): bool
    {
        $plan = $this->plan;

        return $plan && $this->memberships->count() >= $plan->user_limit;
    }

    public function openCreate(): void
    {
        if ($this->atUserLimit) {
            $this->addError('limit', "You've reached your plan's user limit ({$this->plan->user_limit}). Upgrade to add more users.");

            return;
        }

        $this->reset(['editingMembershipId', 'name', 'email', 'password']);
        $this->role = 'cashier';
        $this->showFormModal = true;
    }

    public function openEdit(int $membershipId): void
    {
        $membership = TenantMembership::with('user')->findOrFail($membershipId);

        $this->editingMembershipId = $membership->id;
        $this->name = $membership->user->name;
        $this->email = $membership->user->email;
        $this->role = $membership->role->value;
        $this->password = '';
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $tenant = app(TenantContext::class)->tenant();

        if ($this->editingMembershipId) {
            $membership = TenantMembership::with('user')->findOrFail($this->editingMembershipId);

            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($membership->user_id)],
                'role' => ['required', 'in:owner,cashier,kitchen_staff'],
            ]);

            $membership->user->update(['name' => $this->name, 'email' => $this->email]);
            $membership->update(['role' => $this->role]);
        } else {
            $this->validate([
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', Rule::unique('users', 'email')],
                'password' => ['required', 'string', 'min:8'],
                'role' => ['required', 'in:owner,cashier,kitchen_staff'],
            ]);

            $user = User::create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'email_verified_at' => now(),
            ]);

            $tenant->memberships()->create([
                'user_id' => $user->id,
                'role' => $this->role,
            ]);
        }

        $this->showFormModal = false;
        session()->flash('status', 'User saved.');
    }

    public function toggleActive(int $membershipId): void
    {
        $membership = TenantMembership::findOrFail($membershipId);

        if ($membership->user_id === Auth::id()) {
            $this->addError('self', 'You cannot deactivate your own account.');

            return;
        }

        $membership->update([
            'status' => $membership->status === TenantMembershipStatus::Active
                ? TenantMembershipStatus::Inactive
                : TenantMembershipStatus::Active,
        ]);
    }

    public function openResetPassword(int $membershipId): void
    {
        $this->resettingMembershipId = $membershipId;
        $this->new_password = '';
        $this->showPasswordModal = true;
    }

    public function resetPassword(): void
    {
        $this->validate(['new_password' => ['required', 'string', 'min:8']]);

        $membership = TenantMembership::with('user')->findOrFail($this->resettingMembershipId);
        $membership->user->update(['password' => Hash::make($this->new_password)]);

        $this->showPasswordModal = false;
        session()->flash('status', 'Password reset.');
    }
}; ?>

<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Users</h1>
            <p class="mt-1 text-sm text-muted">
                Manage staff access.
                @if ($this->plan)
                    {{ $this->memberships->count() }} / {{ $this->plan->user_limit }} users on the {{ $this->plan->name }} plan.
                @endif
            </p>
        </div>
        <button type="button" wire:click="openCreate"
                class="flex items-center gap-2 rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
            <x-lucide-plus class="h-4 w-4" /> Add User
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif
    @error('limit') <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ $message }}</div> @enderror
    @error('self') <div class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-700">{{ $message }}</div> @enderror

    <div class="overflow-x-auto rounded-xl border border-hairline bg-surface">
        <table class="w-full min-w-[600px] text-left text-sm">
            <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase tracking-wide text-muted">
                <tr>
                    <th class="px-4 py-3">Name</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Role</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-hairline">
                @foreach ($this->memberships as $membership)
                    <tr>
                        <td class="px-4 py-3 text-ink">{{ $membership->user->name }}</td>
                        <td class="px-4 py-3 text-muted">{{ $membership->user->email }}</td>
                        <td class="px-4 py-3 text-muted">{{ $membership->role->label() }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $membership->status->value === 'active' ? 'bg-green-50 text-green-700' : 'bg-app-bg text-muted' }}">
                                {{ ucfirst($membership->status->value) }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex justify-end gap-1">
                                <button type="button" wire:click="openResetPassword({{ $membership->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink" title="Reset password">
                                    <x-lucide-key-round class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="openEdit({{ $membership->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-pencil class="h-4 w-4" />
                                </button>
                                <button type="button" wire:click="toggleActive({{ $membership->id }})" class="rounded-lg p-2 text-muted hover:bg-app-bg hover:text-ink">
                                    <x-lucide-power class="h-4 w-4" />
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-md rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingMembershipId ? 'Edit User' : 'Add User' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Name</label>
                        <input wire:model="name" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Email</label>
                        <input wire:model="email" type="email" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    @unless ($editingMembershipId)
                        <div>
                            <label class="mb-1 block text-sm font-medium text-ink">Password</label>
                            <input wire:model="password" type="password" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @error('password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                        </div>
                    @endunless
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Role</label>
                        <select wire:model="role" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            @foreach (TenantMembershipRole::cases() as $case)
                                <option value="{{ $case->value }}">{{ $case->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Save
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($showPasswordModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-sm rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">Reset Password</h2>

                <form wire:submit="resetPassword" class="mt-4 space-y-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">New Password</label>
                        <input wire:model="new_password" type="password" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('new_password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showPasswordModal', false)" class="rounded-lg border border-hairline px-4 py-2 text-sm font-medium text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                            Reset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
