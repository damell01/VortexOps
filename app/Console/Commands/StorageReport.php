<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: where the disk goes. The free space on the disk, the biggest
 * folders under storage/ (backups, logs, the Whatnot browser profile), and
 * the biggest database tables with their row counts. Changes nothing.
 */
class StorageReport extends Command
{
    protected $signature = 'storage:report {--tables=20 : How many tables to list}';

    protected $description = 'Show what uses the disk: storage folders and the largest database tables (read-only)';

    public function handle(): int
    {
        $root = base_path();
        $total = @disk_total_space($root);
        $free = @disk_free_space($root);
        if ($total) {
            $this->info(sprintf('Disk: %s used of %s (%d%%), %s free', $this->size($total - $free), $this->size($total), round(($total - $free) / $total * 100), $this->size($free)));
        }

        $this->line('');
        $this->info('Folders');
        $folders = [];
        foreach (['storage/app/backups', 'storage/app', 'storage/logs', 'storage/framework', 'storage/app/public', 'storage/whatnot-profile', 'storage/app/whatnot', 'public/build', 'vendor', 'node_modules'] as $dir) {
            $path = base_path($dir);
            if (is_dir($path)) $folders[] = [$dir, $this->size($this->dirSize($path))];
        }
        foreach (glob(storage_path('*'), GLOB_ONLYDIR) ?: [] as $path) {
            $dir = 'storage/' . basename($path);
            if (! in_array($dir, array_column($folders, 0), true)) $folders[] = [$dir, $this->size($this->dirSize($path))];
        }
        $this->table(['Folder', 'Size'], $folders);

        $backups = glob(storage_path('app/backups/*')) ?: [];
        if ($backups) {
            $this->line(count($backups) . ' backup files; newest ' . basename((string) collect($backups)->sortByDesc(fn ($f) => filemtime($f))->first()));
        }

        $this->line('');
        $this->info('Largest database tables');
        $rows = $this->tables((int) $this->option('tables'));
        $this->table(['Table', 'Rows (approx.)', 'Data', 'Indexes', 'Total'], $rows);

        return self::SUCCESS;
    }

    /** @return list<array{0:string,1:string,2:string,3:string,4:string}> */
    private function tables(int $limit): array
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            return collect(DB::select('SELECT table_name AS name, table_rows AS row_count, data_length AS data, index_length AS idx FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY data_length + index_length DESC LIMIT ?', [$limit]))
                ->map(fn ($t) => [$t->name, number_format((int) $t->row_count), $this->size((int) $t->data), $this->size((int) $t->idx), $this->size((int) $t->data + (int) $t->idx)])
                ->all();
        }

        // SQLite and others: row counts only.
        return collect(DB::connection()->getSchemaBuilder()->getTableListing())
            ->map(fn ($name) => [$name, DB::table($name)->count()])
            ->sortByDesc(1)->take($limit)
            ->map(fn ($t) => [$t[0], number_format($t[1]), '—', '—', '—'])->values()->all();
    }

    private function dirSize(string $path): int
    {
        $size = 0;
        try {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile() && ! $file->isLink()) $size += $file->getSize();
            }
        } catch (\Throwable) {
        }

        return $size;
    }

    private function size(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) { $bytes /= 1024; $i++; }

        return round($bytes, $i ? 1 : 0) . ' ' . $units[$i];
    }
}
