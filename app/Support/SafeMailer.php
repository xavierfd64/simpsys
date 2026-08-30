<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Every automated notification email in the app goes through here rather
 * than the Mail facade directly. An SMTP failure (unreachable host, bad
 * credentials, a host that blocks outbound port 25/587) must never break
 * the transaction that triggered it — a branch approval, a registration,
 * a billing reminder — so this always swallows the exception and reports
 * it to the log for the admin to diagnose, instead of letting it bubble up.
 */
class SafeMailer
{
    /**
     * @return bool whether the send succeeded — callers may use this for
     *              their own bookkeeping (e.g. "reminder sent" flags),
     *              but are never required to check it.
     */
    public static function send(?string $to, Mailable $mailable): bool
    {
        if (blank($to)) {
            return false;
        }

        try {
            Mail::to($to)->send($mailable);

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
