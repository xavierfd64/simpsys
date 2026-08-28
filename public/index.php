<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Compatibility gate — must run before anything Composer/Laravel provides,
// since an incompatible PHP version or a missing vendor/ folder can fatal
// the instant such a file is even required. Exits with a clear diagnostic
// page instead of a bare HTTP 500 when something is actually wrong.
require __DIR__.'/../bootstrap/preflight.php';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

require __DIR__.'/../bootstrap/ensure-env.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
