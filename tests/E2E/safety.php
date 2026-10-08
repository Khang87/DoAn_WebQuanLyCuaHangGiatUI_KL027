<?php

// This probe must fail before a DB connection or application request.
$process = proc_open([PHP_BINARY, __DIR__.'/bootstrap.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, array_merge(getenv(), ['PG_TEST_HOST' => 'example.com']));
$output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
if (proc_close($process) === 0 || ! str_contains($output, 'Unsafe PostgreSQL test target')) {
    fwrite(STDERR, "Unsafe HTTP bootstrap was not refused by the target guard\n");
    exit(1);
}
echo "PASS: unsafe HTTP target refused\n";
