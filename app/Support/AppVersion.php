<?php

namespace App\Support;

/**
 * Deployment version for all admin pages, including PHP/Blade-only releases.
 * A Vite-manifest-only version misses releases that don't rebuild JS/CSS.
 */
final class AppVersion
{
    public static function current(): string
    {
        $manifest = public_path('build/manifest.json');
        $assetHash = is_file($manifest) ? (md5_file($manifest) ?: '') : '';

        // Read the deployed Git revision directly (no shell or build step).
        $gitDir = base_path('.git');
        if (is_file($gitDir)) {
            $pointer = trim((string) file_get_contents($gitDir));
            if (str_starts_with($pointer, 'gitdir: ')) {
                $gitDir = realpath(base_path(substr($pointer, 8))) ?: $gitDir;
            }
        }

        $head = is_dir($gitDir) && is_file($gitDir.'/HEAD')
            ? trim((string) file_get_contents($gitDir.'/HEAD')) : '';
        $revision = $head;
        if (str_starts_with($head, 'ref: ')) {
            $ref = substr($head, 5);
            $refFile = $gitDir.'/'.$ref;
            $revision = is_file($refFile) ? trim((string) file_get_contents($refFile)) : '';
            if ($revision === '' && is_file($gitDir.'/packed-refs')) {
                foreach (file($gitDir.'/packed-refs', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                    if (str_ends_with($line, ' '.$ref)) {
                        $revision = strtok($line, ' ');
                        break;
                    }
                }
            }
        }

        return substr(hash('sha256', $assetHash.'|'.$revision), 0, 16);
    }
}
