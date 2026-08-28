<?php

/**
 * Runs on every request, before the framework boots. A fresh shared-hosting
 * deployment ships with no .env (only .env.example) — but Laravel throws
 * MissingAppKeyException the instant anything touches encryption (sessions,
 * cookies), which happens long before the /install wizard could ever
 * render. So a minimal, file-backed .env (no database required yet) is
 * bootstrapped here, as the very first thing that happens on any request;
 * the /install wizard takes it from there once it can actually run.
 */
$basePath = dirname(__DIR__);
$envPath = $basePath.'/.env';
$examplePath = $basePath.'/.env.example';

if (! file_exists($envPath) && file_exists($examplePath)) {
    $env = file_get_contents($examplePath);

    $replacements = [
        'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
        'APP_ENV' => 'production',
        'APP_DEBUG' => 'false',
        'SESSION_DRIVER' => 'file',
        'CACHE_STORE' => 'file',
        'QUEUE_CONNECTION' => 'sync',
    ];

    foreach ($replacements as $key => $value) {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        $env = preg_match($pattern, $env)
            ? preg_replace($pattern, $key.'='.$value, $env, 1)
            : $env.PHP_EOL.$key.'='.$value;
    }

    @file_put_contents($envPath, $env);
}

// A stale compiled config would shadow whatever the installer writes to
// .env next, so one is never left behind on a not-yet-installed instance.
$cachedConfig = $basePath.'/bootstrap/cache/config.php';
if (! file_exists($basePath.'/storage/app/installed.lock') && file_exists($cachedConfig)) {
    @unlink($cachedConfig);
}
