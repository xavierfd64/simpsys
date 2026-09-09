<?php

namespace App\Services;

use App\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Layered login brute-force protection, per the platform's Security
 * settings (Platform Admin → Settings → Security):
 *
 *  - Account-level failed-attempt counter, keyed by email — reaching
 *    captcha_threshold requires a CAPTCHA on the next attempt; reaching
 *    max_login_attempts locks that account out for lockout_minutes.
 *  - A separate IP-level rate limit (independent of any one account) so
 *    a single source can't cheaply grind through many different accounts'
 *    lockout thresholds at once.
 *
 * Both counters are cache-backed with a TTL matching their own window, so
 * they expire on their own — no unbounded storage growth, no manual
 * cleanup job, and no permanent lockout: a locked account is usable again
 * the moment lockout_minutes elapses, with no admin action required. A
 * successful login clears the account's own counters immediately.
 */
class LoginProtectionService
{
    /**
     * A single source hammering many different accounts is capped
     * independently of any one account's own threshold — generous enough
     * that a shared office/NAT IP doing normal login traffic never trips
     * it, since this only exists to blunt scripted multi-account attacks.
     */
    protected const IP_MAX_ATTEMPTS = 30;

    protected const IP_DECAY_MINUTES = 15;

    protected function settings(): PlatformSetting
    {
        return PlatformSetting::current();
    }

    protected function accountKey(string $email): string
    {
        return 'login_attempts:'.Str::lower($email);
    }

    protected function lockoutKey(string $email): string
    {
        return 'login_lockout:'.Str::lower($email);
    }

    protected function ipKey(string $ip): string
    {
        return 'login_attempts_ip:'.$ip;
    }

    public function isEnabled(): bool
    {
        return $this->settings()->login_protection_enabled;
    }

    public function isRateLimitingEnabled(): bool
    {
        return $this->settings()->rate_limiting_enabled;
    }

    public function isCaptchaEnabled(): bool
    {
        return $this->settings()->captcha_enabled;
    }

    /**
     * Whether this IP alone has made too many login attempts recently,
     * independent of which account(s) it targeted.
     */
    public function ipTooManyAttempts(string $ip): bool
    {
        if (! $this->isRateLimitingEnabled()) {
            return false;
        }

        return RateLimiter::tooManyAttempts($this->ipKey($ip), self::IP_MAX_ATTEMPTS);
    }

    public function ipAvailableInSeconds(string $ip): int
    {
        return RateLimiter::availableIn($this->ipKey($ip));
    }

    protected function hitIp(string $ip): void
    {
        if ($this->isRateLimitingEnabled()) {
            RateLimiter::hit($this->ipKey($ip), self::IP_DECAY_MINUTES * 60);
        }
    }

    /**
     * Failed attempts recorded for this account so far (not reset by a
     * lockout — only by a successful login or the window expiring).
     */
    public function attempts(string $email): int
    {
        return (int) Cache::get($this->accountKey($email), 0);
    }

    public function requiresCaptcha(string $email): bool
    {
        if (! $this->isEnabled() || ! $this->isCaptchaEnabled()) {
            return false;
        }

        return $this->attempts($email) >= $this->settings()->captcha_threshold;
    }

    public function isLockedOut(string $email): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        return Cache::get($this->lockoutKey($email)) !== null;
    }

    /**
     * Seconds until the account's lockout expires, or 0 if it isn't
     * currently locked out.
     */
    public function secondsRemaining(string $email): int
    {
        $until = Cache::get($this->lockoutKey($email));

        if ($until === null) {
            return 0;
        }

        return max(0, (int) now()->diffInSeconds($until, absolute: false));
    }

    /**
     * Records a failed login attempt against both the account-level and
     * IP-level counters, locking the account out once it reaches the
     * configured threshold.
     */
    public function recordFailure(string $email, string $ip): void
    {
        $this->hitIp($ip);

        if (! $this->isEnabled()) {
            return;
        }

        $lockoutMinutes = $this->settings()->lockout_minutes;
        $attempts = $this->attempts($email) + 1;

        Cache::put($this->accountKey($email), $attempts, now()->addMinutes($lockoutMinutes));

        if ($attempts >= $this->settings()->max_login_attempts) {
            Cache::put($this->lockoutKey($email), now()->addMinutes($lockoutMinutes), now()->addMinutes($lockoutMinutes));
        }
    }

    /**
     * Clears an account's failed-attempt and lockout state — called after
     * a genuinely successful login so a locked-then-expired account
     * doesn't carry a stale near-threshold count forever.
     */
    public function recordSuccess(string $email): void
    {
        Cache::forget($this->accountKey($email));
        Cache::forget($this->lockoutKey($email));
    }
}
