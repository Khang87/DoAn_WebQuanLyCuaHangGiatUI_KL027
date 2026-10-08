<?php

use Illuminate\Foundation\Bootstrap\LoadConfiguration;

[$host, $port, $password, $pdo] = require dirname(__DIR__).'/Postgres/connection.php';
$runtime = getenv('WEB_E2E_RUNTIME');
$key = getenv('WEB_E2E_KEY');
$url = getenv('WEB_E2E_URL');
if (! is_string($runtime) || realpath($runtime) !== $runtime
    || ! preg_match('#^'.preg_quote(sys_get_temp_dir(), '#').'/laundry-e2e-[a-zA-Z0-9]+$#', $runtime)
    || (fileperms($runtime) & 0777) !== 0700 || ! is_file($runtime.'/owner')
    || ! is_string($key) || ! str_starts_with($key, 'base64:') || strlen((string) base64_decode(substr($key, 7), true)) !== 32
    || ! preg_match('#^http://127\.0\.0\.1:[0-9]+$#', (string) $url)) {
    throw new RuntimeException('Unsafe HTTP runtime: private owned directory, stable key and loopback URL required.');
}
require dirname(__DIR__, 2).'/vendor/autoload.php';
foreach (['APP_ENV' => 'e2e', 'APP_KEY' => $key, 'APP_URL' => $url, 'APP_CONFIG_CACHE' => $runtime.'/config.php'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
unset($_ENV['VERCEL'], $_SERVER['VERCEL']);
putenv('VERCEL');
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($runtime);
$app->useStoragePath($runtime);
$app->afterBootstrapping(LoadConfiguration::class, static function ($app) use ($host, $port, $password, $runtime): void {
    $app['config']->set([
        'app.env' => 'e2e', 'app.debug' => false, 'app.timezone' => 'Asia/Ho_Chi_Minh',
        'database.default' => 'pgsql',
        'database.connections' => ['pgsql' => [
            'driver' => 'pgsql', 'host' => $host, 'port' => $port,
            'database' => 'laundry_rpc_test', 'username' => 'postgres', 'password' => $password,
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable',
        ]],
        'session.driver' => 'file', 'session.files' => $runtime.'/framework/sessions',
        'session.secure' => false, 'session.cookie' => 'e2e_'.basename($runtime),
        'cache.default' => 'array', 'queue.default' => 'sync', 'mail.default' => 'array', 'logging.default' => 'stderr',
    ]);
});

return $app;
