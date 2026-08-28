<?php

/**
 * Zero-dependency compatibility gate. This runs before Composer's
 * autoloader is even required, so it must never reference a Composer or
 * Laravel class — an incompatible PHP version can fatal the instant a
 * vendor file is merely parsed, which is exactly the case this guards
 * against. Without this, the very first symptom of "wrong PHP version" or
 * "forgot to upload vendor/" is a bare, unexplained HTTP 500 — this file's
 * whole job is to turn that into a page that says what's actually wrong.
 *
 * The required PHP version is read from composer.json rather than
 * hardcoded, so it can never silently drift out of sync with what the app
 * actually needs (see also App\Services\InstallerService::requirements(),
 * which repeats this same read for the in-app installer wizard step — it
 * can't share code with this file since that one only runs once Laravel,
 * and therefore Composer's autoloader, is already booted).
 */
$basePath = dirname(__DIR__);

$composer = json_decode((string) @file_get_contents($basePath.'/composer.json'), true);
$constraint = $composer['require']['php'] ?? '^8.3';
$displayVersion = ltrim($constraint, '^>=~ ');
$requiredVersion = implode('.', array_slice(explode('.', $displayVersion.'.0.0'), 0, 3));

$requiredExtensions = [
    'pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml',
    'ctype', 'json', 'bcmath', 'fileinfo', 'curl', 'dom',
];

$checks = [];

$checks[] = [
    'label' => "PHP {$displayVersion} or higher",
    'passed' => version_compare(PHP_VERSION, $requiredVersion, '>='),
    'detail' => 'This server is running PHP '.PHP_VERSION,
    'fix' => 'Ask your hosting provider to switch your account to PHP '.$displayVersion.' or newer — shared hosts almost always offer this as a dropdown in the control panel (sometimes labelled "PHP Version" or "MultiPHP Manager"), not a manual server change.',
];

foreach ($requiredExtensions as $extension) {
    $loaded = extension_loaded($extension);
    $checks[] = [
        'label' => "PHP extension: {$extension}",
        'passed' => $loaded,
        'detail' => $loaded ? 'Enabled' : 'Not enabled on this server',
        'fix' => "Ask your hosting provider to enable the {$extension} PHP extension for your account.",
    ];
}

$vendorExists = file_exists($basePath.'/vendor/autoload.php');
$checks[] = [
    'label' => 'Application files uploaded completely',
    'passed' => $vendorExists,
    'detail' => $vendorExists ? 'Found' : 'The vendor/ folder is missing',
    'fix' => 'Re-upload the complete application package, including the vendor/ folder — it must be uploaded as-is and never generated on the server.',
];

// Best-effort auto-repair: create/loosen permissions on the directories
// Laravel needs to write to before reporting them as failed, per the
// "automate what can be automated" requirement — a fresh upload's
// directories often just need creating, not a manual permissions fix.
foreach ([
    '/storage',
    '/storage/app',
    '/storage/app/public',
    '/storage/framework',
    '/storage/framework/cache',
    '/storage/framework/sessions',
    '/storage/framework/testing',
    '/storage/framework/views',
    '/storage/logs',
    '/bootstrap/cache',
] as $dir) {
    $path = $basePath.$dir;

    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }

    if (is_dir($path) && ! is_writable($path)) {
        @chmod($path, 0775);
    }
}

foreach ([
    'storage/' => $basePath.'/storage',
    'bootstrap/cache/' => $basePath.'/bootstrap/cache',
    'project root (to create .env)' => $basePath,
] as $label => $path) {
    $writable = file_exists($path) ? is_writable($path) : is_writable(dirname($path));

    $checks[] = [
        'label' => "Writable: {$label}",
        'passed' => $writable,
        'detail' => $writable ? 'Writable' : 'Not writable by PHP',
        'fix' => 'Using your hosting File Manager, set folder permissions to 755 (or 775) on this path.',
    ];
}

$failed = array_values(array_filter($checks, fn ($check) => ! $check['passed']));

if ($failed) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    require __DIR__.'/preflight-error.php';
    exit;
}
