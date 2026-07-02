<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RestoreDatabase extends Command
{
    protected $signature = 'db:restore
                            {file? : Path to backup file (omit to pick from the latest in storage/backups/)}
                            {--list : List available backups and exit}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Restore the database from a backup in storage/backups/';

    public function handle(): int
    {
        if ($this->option('list')) {
            return $this->listBackups();
        }

        $file = $this->resolveFile();
        if ($file === null) {
            return self::FAILURE;
        }

        $connection = config('database.default');

        if (! $this->option('force')) {
            $this->warn("This will overwrite the current {$connection} database.");
            if (! $this->confirm("Restore from " . basename($file) . "?")) {
                $this->line('Aborted.');
                return self::SUCCESS;
            }
        }

        match ($connection) {
            'mysql'  => $this->restoreMysql($file),
            'sqlite' => $this->restoreSqlite($file),
            default  => throw new \RuntimeException("Unsupported DB connection for restore: {$connection}"),
        };

        return self::SUCCESS;
    }

    private function resolveFile(): ?string
    {
        $arg = $this->argument('file');

        if ($arg) {
            // Bare filename — look in backups dir first
            if (! str_contains($arg, '/')) {
                $candidate = storage_path('app/backups/' . $arg);
                if (file_exists($candidate)) {
                    return $candidate;
                }
            }
            if (! file_exists($arg)) {
                $this->error("File not found: {$arg}");
                return null;
            }
            return $arg;
        }

        // No argument — pick the latest backup
        $files = $this->backupFiles();
        if (empty($files)) {
            $this->error('No backups found in ' . storage_path('app/backups/'));
            $this->line('Run php artisan db:backup to create one.');
            return null;
        }

        $latest = end($files);
        $this->line('No file specified — using latest: <comment>' . basename($latest) . '</comment>');
        return $latest;
    }

    private function restoreMysql(string $file): void
    {
        $db   = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $cnfFile = tempnam(sys_get_temp_dir(), 'myrestore');
        file_put_contents($cnfFile, "[client]\npassword={$pass}\n");
        chmod($cnfFile, 0600);

        $isGzip = str_ends_with($file, '.gz');
        $cmd = $isGzip
            ? sprintf(
                'set -o pipefail; gunzip -c %s | mysql --defaults-extra-file=%s -h %s -P %s -u %s %s',
                escapeshellarg($file),
                escapeshellarg($cnfFile),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($db),
            )
            : sprintf(
                'mysql --defaults-extra-file=%s -h %s -P %s -u %s %s < %s',
                escapeshellarg($cnfFile),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($user),
                escapeshellarg($db),
                escapeshellarg($file),
            );

        $output = [];
        $exit   = 0;
        exec('/bin/bash -c ' . escapeshellarg($cmd) . ' 2>&1', $output, $exit);

        unlink($cnfFile);

        if ($exit !== 0) {
            throw new \RuntimeException('MySQL restore failed: ' . implode("\n", $output));
        }

        $this->info('MySQL database restored from ' . basename($file));
        $this->line('Run <comment>php artisan migrate</comment> if the backup pre-dates any pending migrations.');
    }

    private function restoreSqlite(string $file): void
    {
        $dest = config('database.connections.sqlite.database');

        if (! copy($file, $dest)) {
            throw new \RuntimeException("Failed to copy {$file} to {$dest}");
        }

        $this->info('SQLite database restored from ' . basename($file));
    }

    private function listBackups(): int
    {
        $files = $this->backupFiles();

        if (empty($files)) {
            $this->line('No backups found in ' . storage_path('app/backups/'));
            return self::SUCCESS;
        }

        $this->table(['File', 'Size', 'Date'], array_map(function (string $f) {
            return [
                basename($f),
                round(filesize($f) / 1024, 1) . ' KB',
                date('Y-m-d H:i:s', filemtime($f)),
            ];
        }, $files));

        return self::SUCCESS;
    }

    /** @return string[] Sorted oldest → newest */
    private function backupFiles(): array
    {
        $dir   = storage_path('app/backups');
        $files = glob($dir . '/*.{sql,sql.gz,sqlite}', GLOB_BRACE) ?: [];
        usort($files, fn ($a, $b) => filemtime($a) <=> filemtime($b));
        return $files;
    }
}
