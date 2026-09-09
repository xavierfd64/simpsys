<?php

namespace App\Support;

/**
 * A minimal server-generated math CAPTCHA — no external service, no new
 * dependency, works identically on shared hosting. The question and answer
 * live only in the session: the question text is safe to render, the
 * answer is never sent to the browser in any form (not even a hidden
 * field), and verify() always consumes the challenge so a given question
 * can only ever be answered once, whether right or wrong.
 */
class Captcha
{
    protected const SESSION_ANSWER_KEY = 'captcha_answer';

    protected const SESSION_QUESTION_KEY = 'captcha_question';

    protected const SESSION_EXPIRES_KEY = 'captcha_expires_at';

    protected const LIFETIME_MINUTES = 5;

    /**
     * Generates a new question, storing its answer server-side, and
     * returns only the question text to show the user. The question text
     * (not the answer) is also kept in session so a page/component that
     * remounts mid-challenge (a reload, a fresh Livewire request) can
     * redisplay the same still-active question via currentQuestion()
     * instead of being forced to wait for a wasted round trip.
     */
    public static function generate(): string
    {
        $a = random_int(1, 10);
        $b = random_int(1, 10);
        $question = "What is {$a} + {$b}?";

        session()->put(self::SESSION_ANSWER_KEY, $a + $b);
        session()->put(self::SESSION_QUESTION_KEY, $question);
        session()->put(self::SESSION_EXPIRES_KEY, now()->addMinutes(self::LIFETIME_MINUTES)->timestamp);

        return $question;
    }

    /**
     * The active challenge's question text, or null if none is active —
     * safe to call from mount() to restore state a fresh component
     * instance wouldn't otherwise know about.
     */
    public static function currentQuestion(): ?string
    {
        return self::hasActiveChallenge() ? session()->get(self::SESSION_QUESTION_KEY) : null;
    }

    /**
     * Verifies the given answer against the stored challenge and always
     * consumes it (single-use) regardless of the result, so a captured or
     * guessed answer can't be replayed against the same challenge.
     */
    public static function verify(?string $input): bool
    {
        $answer = session()->pull(self::SESSION_ANSWER_KEY);
        $expiresAt = session()->pull(self::SESSION_EXPIRES_KEY);
        session()->forget(self::SESSION_QUESTION_KEY);

        if ($answer === null || $expiresAt === null || now()->timestamp > $expiresAt) {
            return false;
        }

        $trimmed = trim((string) $input);

        // Strict digit check first — casting "11abc" to (int) would
        // otherwise silently match a correct answer typed with junk after it.
        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return false;
        }

        return (int) $trimmed === (int) $answer;
    }

    public static function hasActiveChallenge(): bool
    {
        $expiresAt = session()->get(self::SESSION_EXPIRES_KEY);

        return $expiresAt !== null && now()->timestamp <= $expiresAt;
    }
}
