<?php

use App\Models\PlatformNotification;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Notifications')] class extends Component
{
    public string $audience = 'all';

    public string $tenant_id = '';

    public string $title = '';

    public string $message = '';

    public function getRecentProperty()
    {
        return PlatformNotification::query()->with('tenant')->latest('id')->limit(10)->get();
    }

    public function getTenantsProperty()
    {
        return Tenant::query()->orderBy('name')->get();
    }

    public function send(): void
    {
        $this->validate([
            'audience' => ['required', 'in:all,active,trial,expired,specific'],
            'tenant_id' => ['required_if:audience,specific', 'nullable', 'exists:tenants,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        PlatformNotification::create([
            'audience' => $this->audience,
            'tenant_id' => $this->audience === 'specific' ? $this->tenant_id : null,
            'title' => $this->title,
            'message' => $this->message,
            'sent_by' => Auth::id(),
        ]);

        $this->reset(['title', 'message']);
        session()->flash('status', 'Notification sent.');
    }
}; ?>

<div class="max-w-3xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Notifications</h1>
        <p class="mt-1 text-sm text-muted">Send announcements to tenants.</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <h2 class="text-base font-semibold text-ink">Create Notification</h2>

        <form wire:submit="send" class="mt-4 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Audience</label>
                    <select wire:model.live="audience" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                        <option value="all">All Businesses</option>
                        <option value="active">Active Businesses</option>
                        <option value="trial">Trial Businesses</option>
                        <option value="expired">Expired Businesses</option>
                        <option value="specific">Specific Tenant</option>
                    </select>
                </div>
                @if ($audience === 'specific')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Tenant</label>
                        <select wire:model="tenant_id" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                            <option value="">Select...</option>
                            @foreach ($this->tenants as $tenant)
                                <option value="{{ $tenant->id }}">{{ $tenant->name }}</option>
                            @endforeach
                        </select>
                        @error('tenant_id') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                @endif
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Title</label>
                <input wire:model="title" type="text" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm">
                @error('title') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-ink">Message</label>
                <textarea wire:model="message" rows="4" class="w-full rounded-lg border border-hairline px-3 py-2 text-sm"></textarea>
                @error('message') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">
                Send Notification
            </button>
        </form>
    </div>

    <div class="rounded-xl border border-hairline bg-surface">
        <div class="border-b border-hairline p-4">
            <h3 class="text-sm font-medium text-ink">Recent Notifications</h3>
        </div>
        <ul class="divide-y divide-hairline">
            @forelse ($this->recent as $notification)
                <li class="p-4">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-ink">{{ $notification->title }}</p>
                        <span class="text-xs text-muted">{{ $notification->created_at->format('M j, Y g:i A') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted">{{ $notification->message }}</p>
                    <p class="mt-1 text-xs text-muted">
                        Sent to {{ $notification->audience === 'specific' ? $notification->tenant?->name : ucfirst($notification->audience).' businesses' }}
                    </p>
                </li>
            @empty
                <li class="p-8 text-center text-sm text-muted">No notifications sent yet.</li>
            @endforelse
        </ul>
    </div>
</div>
