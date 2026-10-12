<?php

namespace App\Support;

/**
 * Which front-end build is deployed, so open copies of the app (a home-screen
 * install left open for days) can tell a new version has shipped.
 *
 * The Vite manifest changes on every build that changes any asset, so its
 * hash is the version. Cached against the file's mtime.
 */
final class AppVersion
{
    public static function current(): string
    {
        $path = public_path('build/manifest.json');
        if (! is_file($path)) {
            return 'dev';
        }

        $mtime = (int) filemtime($path);

        return cache()->remember('app-version:'.$mtime, now()->addDay(), fn () => substr(md5_file($path) ?: 'dev', 0, 12));
    }
}
