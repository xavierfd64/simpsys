<?php

use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Forgot Password')] class extends Component
{
    public string $email = '';

    public ?string $status = null;

    /**
     * Laravel's own password broker already throttles repeat emails to the
     * *same* address (config/auth.php's passwords.users.throttle, 60s) —
     * this is a separate, IP-scoped limit so a single source can't cheaply
     * flood many different victims' inboxes, or just hammer the endpoint's
     * own DB lookups/SMTP send, in one burst. Same pattern already used for
     * registration.
     */
    public function sendResetLink(): void
    {
        $this->validate(['email' => ['required', 'string', 'email']]);

        $throttleKey = 'password-reset|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->addError('email', "Too many attempts. Please try again in {$seconds} seconds.");

            return;
        }

        RateLimiter::hit($throttleKey, 3600);

        $status = Password::sendResetLink(['email' => $this->email]);

        // Deliberately identical whether this email is registered or not —
        // Laravel's own default INVALID_USER message ("We can't find a
        // user with that email address.") is a textbook account-
        // enumeration oracle, so it is never shown here. A genuine
        // throttling status is still shown as-is since it doesn't reveal
        // anything about whether the account exists.
        if (in_array($status, [Password::RESET_LINK_SENT, Password::INVALID_USER], true)) {
            $this->status = 'If an account exists for that email address, a password reset link has been sent.';
            $this->reset('email');

            return;
        }

        $this->addError('email', __($status));
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Forgot your password?</h1>
        <p class="mt-1 text-sm text-muted">Enter your email and we will send you a reset link.</p>
    </div>

    @if ($status)
        <div class="rounded-lg bg-green-50 px-4 py-3 text-sm text-green-700">{{ $status }}</div>
    @endif

    <form wire:submit="sendResetLink" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink">Email Address</label>
            <input wire:model="email" id="email" type="email" autofocus autocomplete="username"
                   placeholder="you@example.com"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                wire:loading.attr="disabled" wire:target="sendResetLink">
            Send Reset Link
        </button>

        <p class="text-center text-sm text-muted">
            <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700">Back to log in</a>
        </p>
    </form>
</div>
