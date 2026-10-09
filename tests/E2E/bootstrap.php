<?php

use Illuminate\Foundation\Bootstrap\BootProviders;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Foundation\Http\Events\RequestHandled;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

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

if (getenv('WEB_E2E_PROFILE') === '1') {
    $delay = getenv('WEB_E2E_QUERY_DELAY_MS') ?: '0';
    if (! ctype_digit($delay) || (int) $delay > 50) {
        throw new RuntimeException('Profiling query delay must be between 0 and 50 ms.');
    }
    $app->afterBootstrapping(BootProviders::class, static function () use ($delay): void {
        $queries = 0;
        DB::listen(static function () use (&$queries, $delay): void {
            $queries++;
            if ((int) $delay > 0) {
                usleep((int) $delay * 1000);
            }
        });
        Event::listen(RequestHandled::class, static function ($event) use (&$queries): void {
            $event->response->headers->set('X-E2E-Queries', (string) $queries);
        });
    });
}

// Test-only provider double; reachable only after the owned loopback/PG guards above.
$app->afterBootstrapping(BootProviders::class, static function ($app) use ($runtime): void {
    if (! is_file($runtime.'/avatar-mock-enabled')) {
        return;
    }
    $app['config']->set([
        'services.supabase.project_url' => 'https://avatar-fixture.supabase.co',
        'services.supabase.anon_key' => 'isolated-anon-key',
        'services.supabase.service_role_key' => 'isolated-service-key',
        'services.supabase.avatar_bucket' => 'avatars',
    ]);
    Http::preventStrayRequests();
    Http::fake(static function ($request) use ($runtime) {
        $url = $request->url();
        if ($request->method() === 'POST' && $url === 'https://avatar-fixture.supabase.co/storage/v1/object/upload/sign/avatars/avatars/2') {
            return Http::response(['token' => 'isolated-upload-token'], 200);
        }
        if ($request->method() === 'HEAD' && $url === 'https://avatar-fixture.supabase.co/storage/v1/object/public/avatars/avatars/2') {
            $path = $runtime.'/avatar-object.png';
            if (! is_file($path) || getimagesize($path) === false) {
                return Http::response('', 404);
            }

            return Http::response('', 200, ['Content-Type' => 'image/png', 'Content-Length' => (string) filesize($path)]);
        }
        throw new RuntimeException('Unexpected isolated avatar provider request.');
    });
});

return $app;
