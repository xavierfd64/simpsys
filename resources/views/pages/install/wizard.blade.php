<?php

use App\Services\InstallerService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.install')] #[Title('Install BizManager')] class extends Component
{
    public int $step = 1;

    /** @var array<int, array{label: string, passed: bool, detail: string}> */
    public array $requirements = [];

    public bool $requirementsPassed = false;

    public string $db_host = '127.0.0.1';

    public string $db_port = '3306';

    public string $db_database = '';

    public string $db_username = '';

    public string $db_password = '';

    public ?string $db_error = null;

    public string $admin_name = '';

    public string $admin_email = '';

    public string $admin_password = '';

    public string $admin_password_confirmation = '';

    public function mount(): void
    {
        $this->refreshRequirements();
    }

    protected function refreshRequirements(): void
    {
        $installer = app(InstallerService::class);
        $this->requirements = $installer->requirements();
        $this->requirementsPassed = $installer->requirementsPassed();
    }

    public function continueFromWelcome(): void
    {
        $this->refreshRequirements();

        if ($this->requirementsPassed) {
            $this->step = 2;
        }
    }

    public function submitDatabase(): void
    {
        $this->validate([
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['required', 'numeric'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
        ]);

        $installer = app(InstallerService::class);

        $config = [
            'host' => $this->db_host,
            'port' => $this->db_port,
            'database' => $this->db_database,
            'username' => $this->db_username,
            'password' => $this->db_password,
        ];

        $error = $installer->testConnection($config);

        if ($error) {
            $this->db_error = $error;

            return;
        }

        $this->db_error = null;

        $installer->applyDatabaseConfig([...$config, 'app_url' => request()->getSchemeAndHttpHost()]);
        $installer->migrateAndSeed();

        $this->step = 3;
    }

    public function submitAdmin(): void
    {
        $this->validate([
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $installer = app(InstallerService::class);

        $installer->createAdmin([
            'name' => $this->admin_name,
            'email' => $this->admin_email,
            'password' => $this->admin_password,
        ]);

        $installer->lock();

        $this->step = 4;
    }
}; ?>

<div class="space-y-6">
    <div class="flex items-center justify-center gap-2">
        @foreach ([1 => 'Requirements', 2 => 'Database', 3 => 'Admin Account', 4 => 'Done'] as $number => $label)
            <div class="flex items-center gap-2 {{ $number < 4 ? 'flex-1' : '' }}">
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-xs font-semibold
                    {{ $step >= $number ? 'bg-primary-600 text-white' : 'bg-app-bg text-muted' }}">
                    {{ $number }}
                </div>
                @if ($number < 4)
                    <div class="h-0.5 flex-1 {{ $step > $number ? 'bg-primary-600' : 'bg-hairline' }}"></div>
                @endif
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-hairline bg-surface p-6 sm:p-8">
        @if ($step === 1)
            <h1 class="text-xl font-semibold text-ink">Welcome to BizManager</h1>
            <p class="mt-1 text-sm text-muted">Let's check your server can run it before we go any further.</p>

            <ul class="mt-6 divide-y divide-hairline">
                @foreach ($requirements as $check)
                    <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <span class="text-ink">{{ $check['label'] }}</span>
                        <span class="flex items-center gap-1.5 {{ $check['passed'] ? 'text-green-700' : 'text-danger-500' }}">
                            @if ($check['passed'])
                                <x-lucide-check class="h-4 w-4" />
                            @else
                                <x-lucide-x class="h-4 w-4" />
                            @endif
                            {{ $check['detail'] }}
                        </span>
                    </li>
                @endforeach
            </ul>

            @unless ($requirementsPassed)
                <p class="mt-4 text-sm text-danger-500">Fix the items above, then reload this page to check again.</p>
            @endunless

            <button type="button" wire:click="continueFromWelcome" @disabled(! $requirementsPassed)
                    class="mt-6 w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700 disabled:cursor-not-allowed disabled:opacity-50">
                Continue
            </button>
        @endif

        @if ($step === 2)
            <h1 class="text-xl font-semibold text-ink">Database Connection</h1>
            <p class="mt-1 text-sm text-muted">Enter the MySQL/MariaDB details your host gave you. Nothing is saved until the connection is verified.</p>

            @if ($db_error)
                <div class="mt-4 rounded-lg border border-danger-500/30 bg-red-50 p-3 text-sm text-danger-500">
                    Couldn't connect: {{ $db_error }}
                </div>
            @endif

            <form wire:submit="submitDatabase" class="mt-6 space-y-4">
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-ink">Database Host</label>
                        <input wire:model="db_host" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('db_host') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-ink">Port</label>
                        <input wire:model="db_port" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                        @error('db_port') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Database Name</label>
                    <input wire:model="db_database" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('db_database') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Username</label>
                    <input wire:model="db_username" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('db_username') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Password</label>
                    <input wire:model="db_password" type="password" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('db_password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                        wire:loading.attr="disabled" wire:target="submitDatabase">
                    <span wire:loading.remove wire:target="submitDatabase">Test Connection &amp; Continue</span>
                    <span wire:loading wire:target="submitDatabase">Connecting and setting up your database&hellip;</span>
                </button>
            </form>
        @endif

        @if ($step === 3)
            <h1 class="text-xl font-semibold text-ink">Create Your Admin Account</h1>
            <p class="mt-1 text-sm text-muted">This is the Super Admin account for managing the whole platform.</p>

            <form wire:submit="submitAdmin" class="mt-6 space-y-4">
                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Name</label>
                    <input wire:model="admin_name" type="text" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('admin_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Email Address</label>
                    <input wire:model="admin_email" type="email" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('admin_email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Password</label>
                    <input wire:model="admin_password" type="password" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                    @error('admin_password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Confirm Password</label>
                    <input wire:model="admin_password_confirmation" type="password" class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                </div>

                <button type="submit"
                        class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                        wire:loading.attr="disabled" wire:target="submitAdmin">
                    Create Account &amp; Finish
                </button>
            </form>
        @endif

        @if ($step === 4)
            <div class="text-center">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-50">
                    <x-lucide-check class="h-7 w-7 text-green-700" />
                </div>
                <h1 class="mt-4 text-xl font-semibold text-ink">Installation Complete</h1>
                <p class="mt-1 text-sm text-muted">BizManager is ready. You can now log in with your Super Admin account.</p>

                <a href="{{ route('login') }}"
                   class="mt-6 inline-block w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700">
                    Go to Login
                </a>
            </div>
        @endif
    </div>
</div>
