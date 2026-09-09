<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\LoginProtectionService;
use App\Support\Captcha;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LoginBruteForceProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function attemptWrongPassword(string $email)
    {
        return Livewire::test('auth.login')
            ->set('email', $email)
            ->set('password', 'definitely-wrong')
            ->call('login');
    }

    /**
     * A user with no tenant membership never clears the app's own
     * "must belong to an active business" gate regardless of password
     * correctness — every test here that expects a genuinely successful
     * login needs a real membership, or it fails for an unrelated reason.
     */
    protected function makeOwner(string $password = 'correct-password'): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['password' => $password]);
        $tenant->memberships()->create(['user_id' => $user->id, 'role' => TenantMembershipRole::Owner]);

        return $user;
    }

    public function test_a_successful_login_never_requires_captcha_and_is_not_rate_limited(): void
    {
        $user = $this->makeOwner();

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    /**
     * The exact PoC: an attacker submitting the same wrong password
     * against one account 3 times must be shown a CAPTCHA before a 4th
     * attempt is accepted.
     */
    public function test_captcha_is_required_after_three_failed_attempts(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        for ($i = 0; $i < 3; $i++) {
            $test = $this->attemptWrongPassword($user->email);
        }

        $test->assertSet('captchaQuestion', fn ($q) => $q !== null);

        // The 4th attempt, even with the CORRECT password, must be
        // rejected without a CAPTCHA answer — proving the gate blocks the
        // credential check itself, not just the display.
        $blocked = Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login');

        $blocked->assertHasErrors('captcha_answer');
        $this->assertGuest();
    }

    public function test_a_correct_captcha_answer_lets_a_subsequent_correct_password_through(): void
    {
        $user = $this->makeOwner();

        for ($i = 0; $i < 3; $i++) {
            $test = $this->attemptWrongPassword($user->email);
        }

        // Read the real answer straight out of session — never exposed to
        // the component/HTML in real usage, but the test needs it to prove
        // the *correct* answer is accepted (a wrong one is covered below).
        $answer = (string) session('captcha_answer');

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->set('captcha_answer', $answer)
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_captcha_answer_is_rejected_and_a_new_challenge_is_issued(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        for ($i = 0; $i < 3; $i++) {
            $test = $this->attemptWrongPassword($user->email);
        }

        $firstQuestion = $test->get('captchaQuestion');

        $retry = Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->set('captcha_answer', '999999')
            ->call('login');

        $retry->assertHasErrors('captcha_answer');
        $this->assertGuest();
        // A fresh single-use challenge must replace the consumed one.
        $this->assertNotNull($retry->get('captchaQuestion'));
    }

    /**
     * A CAPTCHA answer must never be replayable against a second attempt —
     * verify() must consume the challenge even when it was correct.
     */
    public function test_a_captcha_answer_cannot_be_reused_across_two_attempts(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);

        for ($i = 0; $i < 3; $i++) {
            $this->attemptWrongPassword($user->email);
        }

        $answer = (string) session('captcha_answer');

        $this->assertTrue(Captcha::verify($answer));
        $this->assertFalse(Captcha::verify($answer));
    }

    /**
     * The core requirement: 5 failed attempts locks the account for 15
     * minutes, enforced server-side regardless of what a client displays.
     */
    public function test_five_failed_attempts_locks_the_account_for_fifteen_minutes(): void
    {
        $user = User::factory()->create(['password' => 'correct-password']);
        $answer = null;

        for ($i = 0; $i < 5; $i++) {
            $test = Livewire::test('auth.login')
                ->set('email', $user->email)
                ->set('password', 'definitely-wrong')
                ->set('captcha_answer', $answer ?? '')
                ->call('login');

            $answer = session('captcha_answer');
        }

        // Even the CORRECT password must now be refused — the account is
        // locked, not merely captcha-gated.
        $locked = Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login');

        $locked->assertHasErrors('email');
        $this->assertGuest();
        $this->assertGreaterThan(14 * 60, $locked->get('lockoutSecondsRemaining'));
        $this->assertLessThanOrEqual(15 * 60, $locked->get('lockoutSecondsRemaining'));
    }

    public function test_a_successful_login_resets_the_failed_attempt_counter(): void
    {
        $user = $this->makeOwner();

        $this->attemptWrongPassword($user->email);
        $this->attemptWrongPassword($user->email);

        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertSame(0, app(LoginProtectionService::class)->attempts($user->email));
    }

    /**
     * Layered protection: an attacker deliberately failing one account's
     * password 5 times to lock out its legitimate owner is still possible
     * per-account (this is inherent to any account lockout, and why the
     * lockout is short and auto-expiring) — but a script grinding through
     * MANY different accounts from the same source is capped by the
     * separate IP-level limiter, independent of any single account's own
     * counter.
     */
    public function test_ip_level_limiter_blocks_attempts_across_many_different_accounts(): void
    {
        for ($i = 0; $i < 35; $i++) {
            $email = "victim{$i}@example.test";
            User::factory()->create(['email' => $email, 'password' => 'correct-password']);

            $test = Livewire::test('auth.login')
                ->set('email', $email)
                ->set('password', 'definitely-wrong')
                ->call('login');
        }

        $test->assertSee('Too many login attempts from this location', false);
    }

    public function test_login_protection_can_be_disabled_via_platform_settings(): void
    {
        PlatformSetting::current()->update(['login_protection_enabled' => false]);
        $user = $this->makeOwner();

        for ($i = 0; $i < 10; $i++) {
            $this->attemptWrongPassword($user->email);
        }

        // No lockout and no CAPTCHA gate — the correct password still works.
        Livewire::test('auth.login')
            ->set('email', $user->email)
            ->set('password', 'correct-password')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }
}
