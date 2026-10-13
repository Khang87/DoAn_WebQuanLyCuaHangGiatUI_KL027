<?php

namespace App\Support;

use Illuminate\Foundation\Application;
use RuntimeException;

final class VercelRuntime
{
    public static function prepare(Application $app, string $temporaryDirectory): void
    {
        $storagePath = rtrim($temporaryDirectory, '/\\').'/storage';
        $cachePath = $storagePath.'/bootstrap/cache';

        foreach ([
            $cachePath,
            $storagePath.'/framework/cache/data',
            $storagePath.'/framework/sessions',
            $storagePath.'/framework/views',
            $storagePath.'/logs',
        ] as $directory) {
            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw new RuntimeException("Cannot create the Laravel runtime directory: {$directory}");
            }
        }

        $app->useStoragePath($storagePath);
        $app->addAbsoluteCachePathPrefix($storagePath);

        // Deployed code is read-only. Laravel may rebuild these files on a cold start.
        foreach ([
            'APP_CONFIG_CACHE' => $cachePath.'/config.php',
            'APP_SERVICES_CACHE' => $cachePath.'/services.php',
            'APP_PACKAGES_CACHE' => $cachePath.'/packages.php',
            'APP_ROUTES_CACHE' => $cachePath.'/routes-v7.php',
            'APP_EVENTS_CACHE' => $cachePath.'/events.php',
            'VIEW_COMPILED_PATH' => $storagePath.'/framework/views',
        ] as $name => $path) {
            $_ENV[$name] = $path;
            $_SERVER[$name] = $path;
            putenv($name.'='.$path);
        }
    }
}
