<?php

use App\Models\PlatformNotification;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('Notifications')] class extends Component
{
    public bool $showFormModal = false;

    public ?int $editingId = null;

    public string $audience = 'all';

    public string $tenant_id = '';

    public string $title = '';

    public string $message = '';

    public string $expires_at = '';

    public ?int $showingReadsFor = null;

    public function getRecentProperty()
    {
        return PlatformNotification::query()->with('tenant')->withCount('reads')->latest('id')->limit(20)->get();
    }

    public function getTenantsProperty()
    {
        return Tenant::query()->orderBy('name')->get();
    }

    public function openCreate(): void
    {
        $this->reset(['editingId', 'audience', 'tenant_id', 'title', 'message', 'expires_at']);
        $this->resetValidation();
        $this->audience = 'all';
        $this->showFormModal = true;
    }

    public function openEdit(int $id): void
    {
        $notification = PlatformNotification::findOrFail($id);
        $this->editingId = $notification->id;
        $this->audience = $notification->audience;
        $this->tenant_id = (string) $notification->tenant_id;
        $this->title = $notification->title;
        $this->message = $notification->message;
        $this->expires_at = $notification->expires_at?->toDateString() ?? '';
        $this->resetValidation();
        $this->showFormModal = true;
    }

    public function save(): void
    {
        $data = $this->validate([
            'audience' => ['required', 'in:all,active,trial,expired,specific'],
            'tenant_id' => ['required_if:audience,specific', 'nullable', 'exists:tenants,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
            'expires_at' => ['nullable', 'date'],
        ]);

        $attributes = [
            'audience' => $data['audience'],
            'tenant_id' => $data['audience'] === 'specific' ? $data['tenant_id'] : null,
            'title' => $data['title'],
            'message' => $data['message'],
            'expires_at' => $data['expires_at'] ?: null,
        ];

        // Editing always updates the same row — never creates a second
        // notice for what the admin sees as one edit.
        if ($this->editingId) {
            PlatformNotification::findOrFail($this->editingId)->update($attributes);
            session()->flash('status', 'Notification updated.');
        } else {
            PlatformNotification::create([
                ...$attributes,
                'sent_by' => Auth::id(),
                'is_active' => true,
                'published_at' => now(),
            ]);
            session()->flash('status', 'Notification published.');
        }

        $this->showFormModal = false;
    }

    public function togglePublish(int $id): void
    {
        $notification = PlatformNotification::findOrFail($id);
        $notification->update([
            'is_active' => ! $notification->is_active,
            'published_at' => $notification->published_at ?? now(),
        ]);
    }

    public function delete(int $id): void
    {
        PlatformNotification::findOrFail($id)->delete();
        session()->flash('status', 'Notification deleted.');
    }

    public function viewReads(int $id): void
    {
        $this->showingReadsFor = $id;
    }

    public function getReadStatsProperty(): ?array
    {
        if (! $this->showingReadsFor) {
            return null;
        }

        $notification = PlatformNotification::findOrFail($this->showingReadsFor);
        $tenants = $notification->matchingTenants();
        $reads = $notification->reads()->with('user')->get()->keyBy('tenant_id');

        $rows = $tenants->map(function ($tenant) use ($reads) {
            $read = $reads->get($tenant->id);

            return [
                'tenant' => $tenant,
                'read' => $read !== null,
                'read_at' => $read?->read_at,
                'user' => $read?->user,
            ];
        })->values();

        return [
            'notification' => $notification,
            'rows' => $rows,
            'total' => $rows->count(),
            'read' => $rows->where('read', true)->count(),
            'unread' => $rows->where('read', false)->count(),
        ];
    }
}; ?>

<div class="max-w-4xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold text-ink">Notifications</h1>
            <p class="mt-1 text-sm text-muted">Send announcements to tenants and track who's seen them.</p>
        </div>
        <button type="button" wire:click="openCreate" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
            + New Notification
        </button>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ session('status') }}</div>
    @endif

    <div class="rounded-xl border border-hairline bg-surface">
        <div class="border-b border-hairline p-4">
            <h3 class="text-sm font-medium text-ink">Recent Notifications</h3>
        </div>
        <ul class="divide-y divide-hairline">
            @forelse ($this->recent as $notification)
                <li class="p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <p class="font-medium text-ink">{{ $notification->title }}</p>
                                @if ($notification->is_active)
                                    <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Published</span>
                                @else
                                    <span class="rounded-full bg-app-bg px-2 py-0.5 text-xs font-medium text-muted">Unpublished</span>
                                @endif
                                @if ($notification->expires_at)
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">
                                        Expires {{ $notification->expires_at->format('M j, Y') }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-muted">{{ $notification->message }}</p>
                            <p class="mt-1 text-xs text-muted">
                                Sent to {{ $notification->audience === 'specific' ? $notification->tenant?->name : ucfirst($notification->audience).' businesses' }}
                                · {{ $notification->created_at->format('M j, Y g:i A') }}
                                · {{ $notification->reads_count }} read
                            </p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-1">
                            <button type="button" wire:click="viewReads({{ $notification->id }})" class="rounded-lg border border-hairline px-2.5 py-1 text-xs font-semibold text-ink hover:bg-app-bg">
                                Reads
                            </button>
                            <button type="button" wire:click="openEdit({{ $notification->id }})" class="rounded-lg border border-hairline px-2.5 py-1 text-xs font-semibold text-ink hover:bg-app-bg">
                                Edit
                            </button>
                            <button type="button" wire:click="togglePublish({{ $notification->id }})" class="rounded-lg border border-hairline px-2.5 py-1 text-xs font-semibold text-ink hover:bg-app-bg">
                                {{ $notification->is_active ? 'Unpublish' : 'Publish' }}
                            </button>
                            <button type="button" wire:click="delete({{ $notification->id }})" wire:confirm="Delete this notification?" class="rounded-lg border border-hairline px-2.5 py-1 text-xs font-semibold text-danger-500 hover:bg-app-bg">
                                Delete
                            </button>
                        </div>
                    </div>
                </li>
            @empty
                <li class="p-8 text-center text-sm text-muted">No notifications sent yet.</li>
            @endforelse
        </ul>
    </div>

    @if ($showFormModal)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-lg rounded-xl bg-surface p-6 shadow-xl">
                <h2 class="text-lg font-semibold text-ink">{{ $editingId ? 'Edit Notification' : 'New Notification' }}</h2>

                <form wire:submit="save" class="mt-4 space-y-4">
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

                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Expires On (optional)</label>
                        <input wire:model="expires_at" type="date" class="w-full max-w-xs rounded-lg border border-hairline px-3 py-2 text-sm">
                        @error('expires_at') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <button type="button" wire:click="$set('showFormModal', false)" class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg">
                            Cancel
                        </button>
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                            {{ $editingId ? 'Save Changes' : 'Publish Notification' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if ($this->readStats)
        <div class="fixed inset-0 z-40 flex items-center justify-center bg-black/40 p-4">
            <div class="w-full max-w-2xl rounded-xl bg-surface p-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink">{{ $this->readStats['notification']->title }}</h2>
                    <button type="button" wire:click="$set('showingReadsFor', null)" class="text-muted hover:text-ink">
                        <x-lucide-x class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-3 flex gap-4 text-sm">
                    <span class="text-ink"><strong>{{ $this->readStats['total'] }}</strong> total recipients</span>
                    <span class="text-green-700"><strong>{{ $this->readStats['read'] }}</strong> read</span>
                    <span class="text-muted"><strong>{{ $this->readStats['unread'] }}</strong> unread</span>
                </div>

                <div class="mt-4 max-h-80 overflow-y-auto rounded-lg border border-hairline">
                    <table class="w-full text-left text-sm">
                        <thead class="border-b border-hairline bg-app-bg text-xs font-medium uppercase text-muted">
                            <tr>
                                <th class="px-4 py-2">Business</th>
                                <th class="px-4 py-2">User</th>
                                <th class="px-4 py-2">Status</th>
                                <th class="px-4 py-2">Read At</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-hairline">
                            @forelse ($this->readStats['rows'] as $row)
                                <tr>
                                    <td class="px-4 py-2 text-ink">{{ $row['tenant']->name }}</td>
                                    <td class="px-4 py-2 text-muted">{{ $row['user']?->name ?: '—' }}</td>
                                    <td class="px-4 py-2">
                                        @if ($row['read'])
                                            <span class="rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700">Read</span>
                                        @else
                                            <span class="rounded-full bg-app-bg px-2 py-0.5 text-xs font-medium text-muted">Unread</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-muted">{{ $row['read_at']?->format('M j, Y g:i A') ?: '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-4 py-6 text-center text-muted">No recipients match this notice's audience.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
