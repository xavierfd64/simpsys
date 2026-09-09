<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthEnumerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * PoC: Laravel's stock password-broker message for an unknown email
     * ("We can't find a user with that email address.") is a textbook
     * account-enumeration oracle — an attacker submits candidate emails
     * and learns which ones are registered purely from the response text,
     * without ever needing a password. The response for a real account
     * must be indistinguishable from the response for a fake one.
     */
    public function test_forgot_password_gives_an_identical_response_for_a_real_and_a_fake_email(): void
    {
        $user = User::factory()->create();

        $realEmailResponse = Livewire::test('auth.forgot-password')
            ->set('email', $user->email)
            ->call('sendResetLink');

        $fakeEmailResponse = Livewire::test('auth.forgot-password')
            ->set('email', 'definitely-not-registered@example.test')
            ->call('sendResetLink');

        $realText = $realEmailResponse->get('status') ?? collect($realEmailResponse->errors()->all())->first();
        $fakeText = $fakeEmailResponse->get('status') ?? collect($fakeEmailResponse->errors()->all())->first();

        $this->assertSame($realText, $fakeText);
        $this->assertStringNotContainsString("can't find a user", (string) $fakeText);
    }

    public function test_login_gives_an_identical_error_for_an_unknown_email_and_a_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        $wrongPassword = Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login');

        $unknownEmail = Livewire::test('auth.login')
            ->set('email', 'nobody-at-all@example.test')
            ->set('password', 'irrelevant')
            ->call('login');

        $this->assertSame(
            $wrongPassword->errors()->first('email'),
            $unknownEmail->errors()->first('email'),
        );
    }
}
