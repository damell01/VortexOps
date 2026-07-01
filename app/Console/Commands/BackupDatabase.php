<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup
                            {--prune=14 : Delete backups older than this many days (0 = keep all)}';

    protected $description = 'Dump the database to storage/backups/ and prune old files';

    public function handle(): int
    {
        $connection = config('database.default');

        match ($connection) {
            'mysql'  => $this->dumpMysql(),
            'sqlite' => $this->copySqlite(),
            default  => throw new \RuntimeException("Unsupported DB connection for backup: {$connection}"),
        };

        $this->pruneOldBackups((int) $this->option('prune'));

        return self::SUCCESS;
    }

    private function dumpMysql(): void
    {
        $db   = config('database.connections.mysql.database');
        $host = config('database.connections.mysql.host', '127.0.0.1');
        $port = config('database.connections.mysql.port', '3306');
        $user = config('database.connections.mysql.username');
        $pass = config('database.connections.mysql.password');

        $filename = 'backups/' . date('Y-m-d_His') . '_' . $db . '.sql.gz';
        $fullPath = storage_path('app/' . $filename);

        $this->ensureDir(dirname($fullPath));

        // Write credentials to a temp file so the password is never exposed in ps output
        $cnfFile = tempnam(sys_get_temp_dir(), 'mybackup');
        file_put_contents($cnfFile, "[client]\npassword={$pass}\n");
        chmod($cnfFile, 0600);

        $cmd = sprintf(
            'mysqldump --defaults-extra-file=%s -h %s -P %s -u %s %s | gzip > %s',
            escapeshellarg($cnfFile),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($user),
            escapeshellarg($db),
            escapeshellarg($fullPath),
        );

        $output = [];
        $exit   = 0;
        exec($cmd . ' 2>&1', $output, $exit);

        unlink($cnfFile);

        if ($exit !== 0) {
            throw new \RuntimeException('mysqldump failed: ' . implode("\n", $output));
        }

        $size = round(filesize($fullPath) / 1024, 1);
        $this->info("MySQL backup saved → storage/app/{$filename} ({$size} KB)");
    }

    private function copySqlite(): void
    {
        $source   = config('database.connections.sqlite.database');
        $filename = 'backups/' . date('Y-m-d_His') . '_database.sqlite';
        $fullPath = storage_path('app/' . $filename);

        $this->ensureDir(dirname($fullPath));

        if (! copy($source, $fullPath)) {
            throw new \RuntimeException("Failed to copy SQLite database to {$fullPath}");
        }

        $size = round(filesize($fullPath) / 1024, 1);
        $this->info("SQLite backup saved → storage/app/{$filename} ({$size} KB)");
    }

    private function pruneOldBackups(int $days): void
    {
        if ($days <= 0) {
            return;
        }

        $dir       = storage_path('app/backups');
        $cutoff    = time() - ($days * 86400);
        $pruned    = 0;

        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                unlink($file);
                $pruned++;
            }
        }

        if ($pruned > 0) {
            $this->line("Pruned {$pruned} backup(s) older than {$days} days.");
        }
    }

    private function ensureDir(string $path): void
    {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
}
