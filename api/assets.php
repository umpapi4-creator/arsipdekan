<?php

/*
 |--------------------------------------------------------------------------
 | Static asset bridge for Vercel PHP
 |--------------------------------------------------------------------------
 | The PHP runtime packages the Laravel app as a function. This small bridge
 | exposes only files already present under public/ with the correct MIME
 | type, so CSS/JS/images are not routed through Laravel as HTML.
 */

$publicRoot = realpath(__DIR__ . '/../public');
$requested = isset($_GET['path']) ? ltrim((string) $_GET['path'], '/') : '';

if (!$publicRoot || $requested === '' || str_contains($requested, "\0")) {
    http_response_code(404);
    exit('Not Found');
}

// Only permit web assets that are expected from Laravel's public directory.
$allowedPrefixes = ['css/', 'js/', 'vendor/', 'images/', 'img/', 'fonts/', 'assets/', 'build/'];
$allowedRootFiles = ['favicon.svg', 'favicon.ico', 'robots.txt'];
$allowed = in_array($requested, $allowedRootFiles, true);

if (!$allowed) {
    foreach ($allowedPrefixes as $prefix) {
        if (str_starts_with($requested, $prefix)) {
            $allowed = true;
            break;
        }
    }
}

if (!$allowed) {
    http_response_code(404);
    exit('Not Found');
}

$file = realpath($publicRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $requested));

if (!$file || !is_file($file) || !str_starts_with($file, $publicRoot . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    exit('Not Found');
}

$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
$types = [
    'css' => 'text/css; charset=UTF-8',
    'js' => 'application/javascript; charset=UTF-8',
    'svg' => 'image/svg+xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'ico' => 'image/x-icon',
    'woff' => 'font/woff',
    'woff2' => 'font/woff2',
    'ttf' => 'font/ttf',
    'otf' => 'font/otf',
    'eot' => 'application/vnd.ms-fontobject',
    'map' => 'application/json; charset=UTF-8',
    'json' => 'application/json; charset=UTF-8',
    'txt' => 'text/plain; charset=UTF-8',
];

header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . filesize($file));
readfile($file);
