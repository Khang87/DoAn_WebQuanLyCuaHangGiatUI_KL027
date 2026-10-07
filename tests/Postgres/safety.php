<?php

// This test does not need a database: unsafe settings must fail before connecting.
foreach ([['PG_TEST_HOST' => 'example.com'], ['PG_TEST_DATABASE' => 'production'], ['PG_TEST_PORT' => '0'], ['PG_TEST_PASSWORD' => '']] as $invalid) {
    $environment = array_merge(getenv(), ['PG_TEST_HOST' => '127.0.0.1', 'PG_TEST_DATABASE' => 'laundry_rpc_test', 'PG_TEST_PORT' => '5432', 'PG_TEST_PASSWORD' => 'test-only'], $invalid);
    $process = proc_open([PHP_BINARY, __DIR__.'/bootstrap.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start safety test.');
    }
    $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    if (proc_close($process) === 0 || ! str_contains($output, 'Unsafe PostgreSQL test target')) {
        throw new RuntimeException('Unsafe target was not refused before connection.');
    }
}
echo "PASS: 4 PostgreSQL target refusals\n";
