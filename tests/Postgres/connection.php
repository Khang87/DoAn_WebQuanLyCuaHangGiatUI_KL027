<?php

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

return [$host, (int) $port, $password, $pdo];
