<?php

use App\Services\PlatformUpdateService;
use Illuminate\Support\Facades\File;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.admin')] #[Title('System Update')] class extends Component
{
    public ?array $pendingManifest = null;

    public ?string $installResult = null;

    public string $installMessage = '';

    public bool $installing = false;

    public function mount(): void
    {
        $pending = session('pending_update');
        $this->pendingManifest = $pending['manifest'] ?? null;
    }

    public function getCurrentVersionProperty(): string
    {
        return app(PlatformUpdateService::class)->currentVersion();
    }

    public function cancel(): void
    {
        $pending = session('pending_update');

        if ($pending && File::exists($pending['path'])) {
            File::delete($pending['path']);
        }

        session()->forget('pending_update');
        $this->pendingManifest = null;
    }

    public function confirmInstall(PlatformUpdateService $updateService): void
    {
        $pending = session('pending_update');

        if (! $pending) {
            $this->installResult = 'failure';
            $this->installMessage = 'No pending update found — please upload the package again.';

            return;
        }

        $result = $updateService->install($pending['path']);

        if (File::exists($pending['path'])) {
            File::delete($pending['path']);
        }

        session()->forget('pending_update');
        $this->pendingManifest = null;

        $this->installResult = $result['success'] ? 'success' : 'failure';
        $this->installMessage = $result['message'];
    }
}; ?>

<div class="max-w-2xl space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">System Update</h1>
        <p class="mt-1 text-sm text-muted">Upload an official BizManager update package to update this installation — no server access required.</p>
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-6">
        <div class="flex items-center justify-between">
            <span class="text-sm font-medium text-muted">Current Version</span>
            <span class="rounded-full bg-app-bg px-3 py-1 text-sm font-semibold text-ink">{{ $this->currentVersion }}</span>
        </div>
    </div>

    @if (session('update_error'))
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">{{ session('update_error') }}</div>
    @endif

    @if ($installResult === 'success')
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ $installMessage }}</div>
    @elseif ($installResult === 'failure')
        <div class="rounded-lg bg-red-50 px-4 py-3 text-sm text-danger-500">{{ $installMessage }}</div>
    @endif

    @if ($pendingManifest)
        <div class="rounded-xl border border-primary-200 bg-primary-50 p-6">
            <h2 class="text-base font-semibold text-ink">Ready to Install</h2>
            <dl class="mt-3 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-muted">Incoming Version</dt><dd class="font-medium text-ink">{{ $pendingManifest['version'] }}</dd></div>
                @if (! empty($pendingManifest['release_notes']))
                    <div><dt class="text-muted">Release Notes</dt><dd class="mt-1 text-ink">{{ $pendingManifest['release_notes'] }}</dd></div>
                @endif
            </dl>
            <p class="mt-4 text-sm text-amber-800">
                This will back up the files it replaces, apply the update, and run any required database migrations.
                If anything fails partway through, file changes are automatically rolled back.
            </p>
            <div class="mt-4 flex gap-3">
                <button type="button" wire:click="cancel" class="rounded-lg border border-hairline px-4 py-2.5 text-sm font-semibold text-ink hover:bg-app-bg" wire:loading.attr="disabled">
                    Cancel
                </button>
                <button type="button" wire:click="confirmInstall" wire:confirm="Install this update now? This cannot be undone once complete."
                        class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700"
                        wire:loading.attr="disabled" wire:target="confirmInstall">
                    <span wire:loading.remove wire:target="confirmInstall">Confirm &amp; Install</span>
                    <span wire:loading wire:target="confirmInstall">Installing&hellip;</span>
                </button>
            </div>
        </div>
    @else
        <div class="rounded-xl border border-hairline bg-surface p-6">
            <h2 class="text-base font-semibold text-ink">Upload Update Package</h2>
            <p class="mt-1 text-sm text-muted">Accepts an official <code>.zip</code> update package only.</p>

            <form method="POST" action="{{ route('admin.updates.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                @csrf
                <input type="file" name="update_zip" accept=".zip" required class="w-full text-sm">
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">
                    Upload &amp; Validate
                </button>
            </form>
        </div>
    @endif
</div>
