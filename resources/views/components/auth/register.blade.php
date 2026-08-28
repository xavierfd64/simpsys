<?php

use App\Models\SubscriptionPlan;
use App\Services\TenantOnboardingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Create Your Account')] class extends Component
{
    public string $business_name = '';

    public string $owner_name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $terms_accepted = false;

    public ?string $planSlug = null;

    public function mount(): void
    {
        $plan = request()->query('plan');

        if ($plan && SubscriptionPlan::query()->where('slug', $plan)->where('is_active', true)->exists()) {
            $this->planSlug = $plan;
        }
    }

    public function getSelectedPlanProperty(): ?SubscriptionPlan
    {
        return $this->planSlug
            ? SubscriptionPlan::query()->where('slug', $this->planSlug)->first()
            : null;
    }

    public function register(TenantOnboardingService $onboarding): void
    {
        $throttleKey = 'register|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        RateLimiter::hit($throttleKey, 3600);

        $this->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'owner_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'terms_accepted' => ['accepted'],
        ]);

        $tenant = $onboarding->register(
            $this->business_name,
            $this->owner_name,
            $this->email,
            $this->password,
            $this->selectedPlan,
        );

        Auth::login($tenant->owner()->user);
        session()->regenerate();

        $this->redirectRoute('app.dashboard', navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Create your business account</h1>
        <p class="mt-1 text-sm text-muted">
            Start your 14-day free trial. No credit card required.
            @if ($this->selectedPlan)
                <span class="mt-1 block font-medium text-primary-600">{{ $this->selectedPlan->name }} plan selected.</span>
            @endif
        </p>
    </div>

    <form wire:submit="register" class="space-y-4">
        <div>
            <label for="business_name" class="mb-1 block text-sm font-medium text-ink">Business Name</label>
            <input wire:model="business_name" id="business_name" type="text" autofocus
                   placeholder="Juan's Fishball Station"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('business_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="owner_name" class="mb-1 block text-sm font-medium text-ink">Owner Name</label>
            <input wire:model="owner_name" id="owner_name" type="text"
                   placeholder="Juan Dela Cruz"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('owner_name') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink">Email Address</label>
            <input wire:model="email" id="email" type="email" autocomplete="username"
                   placeholder="you@example.com"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-ink">Password</label>
            <input wire:model="password" id="password" type="password" autocomplete="new-password"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-ink">Confirm Password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        </div>

        <label class="flex items-start gap-2 text-sm text-muted">
            <input wire:model="terms_accepted" type="checkbox" class="mt-0.5 rounded border-hairline text-primary-600 focus:ring-primary-500">
            I agree to the Terms of Service and Privacy Policy
        </label>
        @error('terms_accepted') <p class="text-sm text-danger-500">{{ $message }}</p> @enderror

        <button type="submit"
                class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                wire:loading.attr="disabled" wire:target="register">
            Create Account
        </button>

        <p class="text-center text-sm text-muted">
            Already have an account?
            <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-700">Log in</a>
        </p>
    </form>
</div>
