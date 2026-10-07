<?php

try {
    $mode = $argv[1] ?? 'all';
    if (! in_array($mode, ['all', 'contracts', 'concurrency'], true)) {
        throw new RuntimeException('Unknown PostgreSQL suite.');
    }
    require __DIR__.'/bootstrap.php';
    require __DIR__.'/support.php';
    echo "PASS: guarded Laravel PostgreSQL bootstrap\n";
    if ($mode !== 'concurrency') {
        require __DIR__.'/contracts.php';
    }
    if ($mode !== 'contracts') {
        require __DIR__.'/concurrency.php';
    }
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1); // Laravel's console exception renderer must not turn failures green.
}
