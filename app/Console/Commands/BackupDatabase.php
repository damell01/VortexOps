<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--prune=14 : Delete local backups older than this many days (0 = keep all)}';

    protected $description = 'Dump the database, verify it, write a checksum, optionally mirror it, and prune old local files';

    public function handle(): int
    {
        $connection = config('database.default');

        $fullPath = match ($connection) {
            'mysql'  => $this->dumpMysql(),
            'sqlite' => $this->copySqlite(),
            default  => throw new \RuntimeException("Unsupported DB connection for backup: {$connection}"),
        };

        $this->verifyBackup($fullPath, $connection);
        $checksumPath = $this->writeChecksum($fullPath);
        $this->mirrorBackup($fullPath, $checksumPath);
        $this->pruneOldBackups((int) $this->option('prune'));

        return self::SUCCESS;
    }

    private function dumpMysql(): string
    {
        $db   = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $filename = 'backups/' . date('Y-m-d_His') . '_' . $db . '.sql.gz';
        $fullPath = storage_path('app/' . $filename);

        $this->ensureDir(dirname($fullPath));

        $cnfFile = tempnam(sys_get_temp_dir(), 'mybackup');
        file_put_contents($cnfFile, "[client]\npassword={$pass}\n");
        chmod($cnfFile, 0600);

        try {
            $cmd = sprintf(
                'set -o pipefail; mysqldump --single-transaction --quick --routines --triggers --defaults-extra-file=%s -h %s -P %s -u %s %s | gzip -1 > %s',
                escapeshellarg($cnfFile),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($db),
                escapeshellarg($fullPath),
            );

            $output = [];
            $exit = 0;
            exec('/bin/bash -c ' . escapeshellarg($cmd) . ' 2>&1', $output, $exit);

            if ($exit !== 0) {
                @unlink($fullPath);
                throw new \RuntimeException('mysqldump failed: ' . implode("\n", $output));
            }
        } finally {
            @unlink($cnfFile);
        }

        $size = round(filesize($fullPath) / 1024, 1);
        $this->info("MySQL backup saved → storage/app/{$filename} ({$size} KB)");

        return $fullPath;
    }

    private function copySqlite(): string
    {
        $source = config('database.connections.sqlite.database');
        $filename = 'backups/' . date('Y-m-d_His') . '_database.sqlite';
        $fullPath = storage_path('app/' . $filename);

        $this->ensureDir(dirname($fullPath));

        if (! copy($source, $fullPath)) {
            throw new \RuntimeException("Failed to copy SQLite database to {$fullPath}");
        }

        $size = round(filesize($fullPath) / 1024, 1);
        $this->info("SQLite backup saved → storage/app/{$filename} ({$size} KB)");

        return $fullPath;
    }

    private function verifyBackup(string $fullPath, string $connection): void
    {
        clearstatcache(true, $fullPath);
        if (! is_file($fullPath) || filesize($fullPath) < 128) {
            throw new \RuntimeException("Backup verification failed: {$fullPath} is missing or unexpectedly small");
        }

        if ($connection === 'mysql') {
            $output = [];
            $exit = 0;
            exec('gzip -t ' . escapeshellarg($fullPath) . ' 2>&1', $output, $exit);
            if ($exit !== 0) {
                throw new \RuntimeException('Backup gzip verification failed: ' . implode("\n", $output));
            }
        }

        $this->line('Backup verification passed.');
    }

    private function writeChecksum(string $fullPath): string
    {
        $checksum = hash_file('sha256', $fullPath);
        if ($checksum === false) {
            throw new \RuntimeException("Unable to checksum backup {$fullPath}");
        }

        $checksumPath = $fullPath . '.sha256';
        file_put_contents($checksumPath, $checksum . '  ' . basename($fullPath) . PHP_EOL);

        return $checksumPath;
    }

    private function mirrorBackup(string $fullPath, string $checksumPath): void
    {
        $mirror = trim((string) env('BACKUP_MIRROR_PATH', ''));
        if ($mirror === '') {
            return;
        }

        $this->ensureDir($mirror);

        foreach ([$fullPath, $checksumPath] as $source) {
            $target = rtrim($mirror, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($source);
            if (! copy($source, $target)) {
                throw new \RuntimeException("Failed to mirror backup to {$target}");
            }
        }

        $this->info("Backup mirrored → {$mirror}");
    }

    private function pruneOldBackups(int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $dir = storage_path('app/backups');
        $cutoff = time() - ($days * 86400);
        $pruned = 0;

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            $this->line("Pruned {$pruned} backup file(s) older than {$days} days.");
        }
    }

    private function ensureDir(string $path): void
    {
        if (! is_dir($path) && ! mkdir($path, 0755, true) && ! is_dir($path)) {
            throw new \RuntimeException("Unable to create backup directory {$path}");
        }
    }
}
