<?php

use App\Models\User;
use App\Services\LoginProtectionService;
use App\Support\Captcha;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Log In')] class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public string $captcha_answer = '';

    public ?string $captchaQuestion = null;

    public int $lockoutSecondsRemaining = 0;

    public function mount(): void
    {
        // A fresh component instance (page load, reload) otherwise has no
        // way to know a CAPTCHA challenge is already active in session —
        // without this, a legitimate reload mid-challenge would silently
        // discard a perfectly valid question and force an extra round trip.
        $this->captchaQuestion = Captcha::currentQuestion();
    }

    protected function protection(): LoginProtectionService
    {
        return app(LoginProtectionService::class);
    }

    protected function lockoutMessage(): string
    {
        $minutes = intdiv($this->lockoutSecondsRemaining, 60);
        $seconds = $this->lockoutSecondsRemaining % 60;

        return sprintf(
            'Too many failed login attempts. Your account is temporarily locked. Try again in %d:%02d.',
            $minutes,
            $seconds,
        );
    }

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $ip = request()->ip();
        $protection = $this->protection();

        // IP-level layer — independent of any one account, so a script
        // grinding through many different accounts' credentials from one
        // source is capped without needing any of those accounts to
        // individually reach their own lockout threshold first.
        if ($protection->ipTooManyAttempts($ip)) {
            throw ValidationException::withMessages([
                'email' => 'Too many login attempts from this location. Please try again later.',
            ]);
        }

        // Account-level lockout — enforced here regardless of what a
        // client-side countdown displays; a locked account stays blocked
        // until this server-side check itself says otherwise.
        if ($protection->isLockedOut($this->email)) {
            $this->lockoutSecondsRemaining = $protection->secondsRemaining($this->email);

            throw ValidationException::withMessages(['email' => $this->lockoutMessage()]);
        }

        // CAPTCHA gate — must be solved before a password attempt is even
        // tried, so a wrong CAPTCHA answer never itself counts as (or
        // masks) a credential guess.
        if ($protection->requiresCaptcha($this->email)) {
            if (blank($this->captchaQuestion) || ! Captcha::hasActiveChallenge()) {
                $this->captchaQuestion = Captcha::generate();

                throw ValidationException::withMessages([
                    'captcha_answer' => 'Please answer the security question below to continue.',
                ]);
            }

            if (! Captcha::verify($this->captcha_answer)) {
                $this->captchaQuestion = Captcha::generate();
                $this->captcha_answer = '';

                throw ValidationException::withMessages([
                    'captcha_answer' => 'Incorrect answer. Please try again.',
                ]);
            }

            $this->captchaQuestion = null;
            $this->captcha_answer = '';
        }

        if (! Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            $protection->recordFailure($this->email, $ip);

            if ($protection->isLockedOut($this->email)) {
                $this->lockoutSecondsRemaining = $protection->secondsRemaining($this->email);

                throw ValidationException::withMessages(['email' => $this->lockoutMessage()]);
            }

            if ($protection->requiresCaptcha($this->email)) {
                $this->captchaQuestion = Captcha::generate();
            }

            // Deliberately identical whether the email doesn't exist or the
            // password is simply wrong — never confirm which one it was.
            throw ValidationException::withMessages([
                'email' => 'These credentials could not be verified.',
            ]);
        }

        $protection->recordSuccess($this->email);
        session()->regenerate();

        /** @var User $user */
        $user = Auth::user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'These credentials could not be verified.',
            ]);
        }

        if ($user->is_platform_admin) {
            $this->redirectRoute('admin.dashboard', navigate: true);

            return;
        }

        if ($user->activeMembership()) {
            $this->redirectRoute($user->homeRouteName(), navigate: true);

            return;
        }

        Auth::logout();

        throw ValidationException::withMessages([
            'email' => 'These credentials could not be verified.',
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

    @if ($lockoutSecondsRemaining > 0)
        <div x-data="{ seconds: {{ $lockoutSecondsRemaining }} }"
             x-init="setInterval(() => { if (seconds > 0) seconds-- }, 1000)"
             class="rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-medium">Your account is temporarily locked.</p>
            <p class="mt-1">
                Try again in
                <span class="font-mono font-semibold" x-text="String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0')"></span>
            </p>
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

        @if ($captchaQuestion)
            <div>
                <label for="captcha_answer" class="mb-1 block text-sm font-medium text-ink">Security Question: {{ $captchaQuestion }}</label>
                <input wire:model="captcha_answer" id="captcha_answer" type="text" inputmode="numeric" autocomplete="off"
                       placeholder="Your answer"
                       class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
                @error('captcha_answer') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
            </div>
        @endif

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
