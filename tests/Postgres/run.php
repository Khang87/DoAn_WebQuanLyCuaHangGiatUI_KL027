<?php

try {
    $mode = $argv[1] ?? 'all';
    if (! in_array($mode, ['all', 'contracts', 'concurrency', 'beepay'], true)) {
        throw new RuntimeException('Unknown PostgreSQL suite.');
    }
    require __DIR__.'/bootstrap.php';
    require __DIR__.'/support.php';
    echo "PASS: guarded Laravel PostgreSQL bootstrap\n";
    if (in_array($mode, ['all', 'contracts'], true)) {
        require __DIR__.'/contracts.php';
        require __DIR__.'/message-realtime-contract.php';
    }
    if (in_array($mode, ['all', 'concurrency'], true)) {
        require __DIR__.'/concurrency.php';
    }
    if (in_array($mode, ['all', 'beepay'], true)) {
        require __DIR__.'/beepay.php';
    }
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception).': '.$exception->getMessage()."\n");
    exit(1); // Laravel's console exception renderer must not turn failures green.
}
