<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\LoadConfiguration;
use Illuminate\Support\Facades\DB;

// Validate explicit test settings before Composer, Laravel or any connection.
$host = getenv('PG_TEST_HOST') ?: '127.0.0.1';
$port = getenv('PG_TEST_PORT');
$database = getenv('PG_TEST_DATABASE') ?: 'laundry_rpc_test';
$password = getenv('PG_TEST_PASSWORD');
if ($host !== '127.0.0.1' || $database !== 'laundry_rpc_test'
    || ! ctype_digit((string) $port) || (int) $port < 1 || (int) $port > 65535
    || ! is_string($password) || $password === '') {
    throw new RuntimeException('Unsafe PostgreSQL test target: explicit loopback port/password and laundry_rpc_test required.');
}

$pdo = new PDO("pgsql:host={$host};port={$port};dbname={$database};connect_timeout=5", 'postgres', $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if ($pdo->query("SELECT current_database() = 'laundry_rpc_test' AND obj_description(to_regclass('public.test_fixture_identity')) = 'laundry verification fixture v1'")->fetchColumn() !== true) {
    throw new RuntimeException('Unsafe PostgreSQL test target: fixture identity missing.');
}

require dirname(__DIR__, 2).'/vendor/autoload.php';
$runtime = sys_get_temp_dir().'/laundry-pg-'.bin2hex(random_bytes(12));
foreach (['', '/framework/cache', '/framework/views', '/framework/sessions', '/logs'] as $directory) {
    mkdir($runtime.$directory, 0700, true);
}
register_shutdown_function(static function () use ($runtime): void {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($runtime, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($files as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($runtime);
});
foreach (['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $runtime.'/config.php'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
unset($_ENV['VERCEL'], $_SERVER['VERCEL']);
putenv('VERCEL');
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($runtime); // No repository .env or cached production config.
$app->useStoragePath($runtime);
$app->afterBootstrapping(LoadConfiguration::class, static function ($app) use ($host, $port, $password): void {
    $app['config']->set([
        'app.env' => 'testing', 'app.timezone' => 'Asia/Ho_Chi_Minh',
        'database.default' => 'pgsql',
        'database.connections' => ['pgsql' => [
            'driver' => 'pgsql', 'host' => $host, 'port' => (int) $port,
            'database' => 'laundry_rpc_test', 'username' => 'postgres', 'password' => $password,
            'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'disable',
        ]],
        'cache.default' => 'array', 'session.driver' => 'array',
        'queue.default' => 'sync', 'mail.default' => 'array', 'logging.default' => 'stderr',
    ]);
});
$app->make(Kernel::class)->bootstrap();
DB::statement("SET statement_timeout = '15s'");
DB::statement("SET TIME ZONE 'Asia/Ho_Chi_Minh'");

return $app;
