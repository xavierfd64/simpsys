<?php

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts.guest')] #[Title('Reset Password')] class extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', 'min:8'],
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function (User $user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        $this->redirectRoute('login', ['status' => __($status)], navigate: true);
    }
}; ?>

<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-ink">Reset your password</h1>
        <p class="mt-1 text-sm text-muted">Choose a new password for your account.</p>
    </div>

    <form wire:submit="resetPassword" class="space-y-4">
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-ink">Email Address</label>
            <input wire:model="email" id="email" type="email" autocomplete="username"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('email') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password" class="mb-1 block text-sm font-medium text-ink">New Password</label>
            <input wire:model="password" id="password" type="password" autocomplete="new-password"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
            @error('password') <p class="mt-1 text-sm text-danger-500">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1 block text-sm font-medium text-ink">Confirm New Password</label>
            <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password"
                   class="w-full rounded-lg border border-hairline px-3 py-2.5 text-sm focus:border-primary-500 focus:outline-none focus:ring-1 focus:ring-primary-500">
        </div>

        <button type="submit"
                class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-primary-700"
                wire:loading.attr="disabled" wire:target="resetPassword">
            Reset Password
        </button>
    </form>
</div>
