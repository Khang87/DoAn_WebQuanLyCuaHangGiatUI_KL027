<?php

// Vercel invokes this function for every Laravel path. Preserve the original
// route so /api/v1/payment-config and payment webhooks reach Laravel.
if (isset($_GET['__vercel_path'])) {
    $path = '/'.ltrim((string) $_GET['__vercel_path'], '/');
    unset($_GET['__vercel_path']);
    $query = http_build_query($_GET, '', '&', PHP_QUERY_RFC3986);
    $_SERVER['REQUEST_URI'] = $path.($query !== '' ? '?'.$query : '');
    $_SERVER['QUERY_STRING'] = $query;
    // Keep Laravel from treating Vercel's /api function directory as the app base path.
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['PHP_SELF'] = '/index.php';
}

require __DIR__ . '/../public/index.php';
