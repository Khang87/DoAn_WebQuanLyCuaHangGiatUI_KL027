<?php

use Illuminate\Support\Facades\DB;

function pgWait(callable $condition, string $label, int $seconds = 10): void
{
    $deadline = hrtime(true) + $seconds * 1_000_000_000;
    do {
        if ($condition()) {
            return;
        }
        usleep(20_000);
    } while (hrtime(true) < $deadline);
    throw new RuntimeException('Timed out: '.$label);
}

/** Two independent sessions must both wait on real PostgreSQL row locks. */
function pgRace(string $lockSql, array $bindings, array $payloads): array
{
    $workers = [];
    DB::beginTransaction();
    try {
        DB::select($lockSql, $bindings);
        foreach ($payloads as $payload) {
            $name = 'laundry-worker-'.bin2hex(random_bytes(8));
            $process = proc_open([PHP_BINARY, __DIR__.'/worker.php', $name, json_encode($payload, JSON_THROW_ON_ERROR)], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (! is_resource($process)) {
                throw new RuntimeException('Cannot start PostgreSQL worker.');
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $workers[] = ['process' => $process, 'pipes' => $pipes, 'name' => $name];
        }
        $names = array_column($workers, 'name');
        pgWait(static function () use ($workers, $names): bool {
            foreach ($workers as $worker) {
                if (! proc_get_status($worker['process'])['running']) {
                    throw new RuntimeException('Worker stopped before overlap: '.stream_get_contents($worker['pipes'][2]).stream_get_contents($worker['pipes'][1]));
                }
            }
            DB::selectOne('SELECT pg_stat_clear_snapshot()');

            return (int) DB::selectOne("SELECT count(*) AS blocked FROM pg_stat_activity WHERE application_name IN (?, ?) AND wait_event_type = 'Lock' AND cardinality(pg_blocking_pids(pid)) > 0", $names)->blocked === 2;
        }, 'both worker sessions blocked', 10);
        pgExpect(count($workers), 2, 'Two worker sessions observed blocked before release');
        DB::commit();
        $results = [];
        foreach ($workers as $worker) {
            $output = '';
            $errors = '';
            $exit = null;
            pgWait(static function () use ($worker, &$output, &$errors, &$exit): bool {
                $output .= stream_get_contents($worker['pipes'][1]);
                $errors .= stream_get_contents($worker['pipes'][2]);
                $status = proc_get_status($worker['process']);
                if (! $status['running']) {
                    $exit = $status['exitcode'];

                    return true;
                }

                return false;
            }, 'worker completion');
            $output .= stream_get_contents($worker['pipes'][1]);
            $errors .= stream_get_contents($worker['pipes'][2]);
            if ($exit !== 0) {
                throw new RuntimeException('PostgreSQL worker failed: '.substr($errors.$output, 0, 2000));
            }
            $results[] = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        foreach ($workers as $worker) {
            if (proc_get_status($worker['process'])['running']) {
                proc_terminate($worker['process'], 9);
            }
            fclose($worker['pipes'][1]);
            fclose($worker['pipes'][2]);
            proc_close($worker['process']);
        }
    }
}
