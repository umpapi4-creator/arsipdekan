<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
 |--------------------------------------------------------------------------
 | Vercel runtime compatibility
 |--------------------------------------------------------------------------
 | Vercel Functions have a read-only application filesystem. Laravel needs a
 | writable location for compiled views, cache files, logs, and temporary
 | session data. Keep those runtime-only files in /tmp without changing the
 | application's routes, controllers, views, or business logic.
 */
$storagePath = '/tmp/laravel-storage';
$bootstrapPath = '/tmp/laravel-bootstrap';

foreach ([
    $bootstrapPath,
    $bootstrapPath.'/cache',
    $storagePath,
    $storagePath.'/app',
    $storagePath.'/app/public',
    $storagePath.'/framework',
    $storagePath.'/framework/cache',
    $storagePath.'/framework/cache/data',
    $storagePath.'/framework/sessions',
    $storagePath.'/framework/views',
    $storagePath.'/logs',
] as $directory) {
    if (!is_dir($directory)) {
        @mkdir($directory, 0775, true);
    }
}

$runtimeDefaults = [
    'SESSION_DRIVER' => 'cookie',
    'SESSION_SECURE_COOKIE' => 'true',
    'CACHE_DRIVER' => 'array',
    'LOG_CHANNEL' => 'stderr',
    'VIEW_COMPILED_PATH' => $storagePath.'/framework/views',
];

foreach ($runtimeDefaults as $key => $value) {
    if (getenv($key) === false && !isset($_ENV[$key]) && !isset($_SERVER[$key])) {
        putenv($key.'='.$value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

require __DIR__.'/../vendor/autoload.php';

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->useBootstrapPath($bootstrapPath);
$app->useStoragePath($storagePath);

$kernel = $app->make(Kernel::class);

// Vercel terminates HTTPS at its proxy. Make the captured request reflect
// the public HTTPS request even before Laravel evaluates URL/cookie state.
if (getenv('VERCEL')) {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
}

$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
