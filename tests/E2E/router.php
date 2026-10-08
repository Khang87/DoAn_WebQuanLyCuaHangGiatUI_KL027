<?php

use Illuminate\Http\Request;

$public = realpath(dirname(__DIR__, 2).'/public');
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = realpath($public.'/'.ltrim(rawurldecode($path), '/'));
if ($file && str_starts_with($file, $public.'/') && is_file($file)
    && pathinfo($file, PATHINFO_EXTENSION) !== 'php' && ! str_contains($path, '/.')) {
    return false;
}
try {
    $app = require __DIR__.'/bootstrap.php';
    $app->handleRequest(Request::capture());
} catch (Throwable $error) {
    error_log($error->getMessage());
    http_response_code(500);
    echo 'Isolated HTTP bootstrap failed.';
}
