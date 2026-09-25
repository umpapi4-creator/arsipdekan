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

foreach ([
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
$app->useStoragePath($storagePath);

$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
