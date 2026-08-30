<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Shared-hosting-friendly stand-in for a system cron job: since customers
 * can't be asked to configure one (see the installer's whole no-manual-
 * commands philosophy), daily background work instead piggybacks on
 * whichever real request happens to be the first one of the day. Cheap to
 * check (one cache read) on every other request, so this can be called
 * unconditionally from a service provider's boot() without adding
 * meaningful overhead.
 */
class OpportunisticScheduler
{
    public static function runDaily(string $key, callable $callback): void
    {
        $cacheKey = "scheduler:{$key}:last_run_date";
        $today = now()->toDateString();

        if (Cache::get($cacheKey) === $today) {
            return;
        }

        Cache::put($cacheKey, $today, now()->addDay());

        $callback();
    }
}
