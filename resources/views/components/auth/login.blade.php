<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Log In')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'These credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        if ($user->is_platform_admin) {
            $this->redirectRoute('admin.dashboard', navigate: true);

            return;
        }

        if ($user->activeMembership()) {
            $this->redirectRoute('app.dashboard', navigate: true);

            return;
        }

        Auth::logout();

        throw ValidationException::withMessages([
            'email' => 'No active business was found for this account.',
        ]);
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Welcome back</h1>
        <p class="mt-1 text-sm text-muted">Log in to your account</p>
    </div>

    @if (session('status'))
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">
            {{ session('status') }}
        </div>
    @endif

    <form wire:submit="login" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink">Email Address</label>
            <input wire:model="email" id="email" type="email" autofocus autocomplete="username"
                   placeholder="you@example.com"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-ink">Password</label>
            <input wire:model="password" id="password" type="password" autocomplete="current-password"
                   placeholder="••••••••"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-muted">
                <input wire:model="remember" type="checkbox" class="rounded border-hairline text-primary-600 focus:ring-primary-500">
                Remember me
            </label>

            <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">
                Forgot password?
            </a>
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                wire:loading.attr="disabled" wire:target="login">
            Log In
        </button>
    </form>
</div>
