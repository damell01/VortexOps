<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Summarise the "Slow or failed web request" lines PerformanceHeaders writes
 * to storage/logs: which pages and buttons are slow, how often, how slow
 * (median / 95th percentile), and how much of it is database time.
 */
final class SlowRequestReport extends Command
{
    protected $signature = 'perf:slow-report {--days=7} {--top=25}';

    protected $description = 'List the slowest pages and buttons from the slow-request log.';

    public function handle(): int
    {
        $since = now()->subDays(max(1, (int) $this->option('days')));
        $groups = [];
        foreach (glob(storage_path('logs/laravel*.log')) ?: [] as $file) {
            if (filemtime($file) < $since->getTimestamp()) continue;
            $handle = fopen($file, 'r');
            while (($line = fgets($handle)) !== false) {
                if (! str_contains($line, 'Slow or failed web request')) continue;
                if (! preg_match('/^\[([^\]]+)\]/', $line, $when) || Carbon::parse($when[1])->lt($since)) continue;
                $json = json_decode(substr($line, (int) strpos($line, '{')), true);
                if (! is_array($json)) continue;
                $key = ($json['calls'] ?? null) ? ($json['page'] ?? '?').' → '.$json['calls'] : ($json['path'] ?? $json['route'] ?? '?');
                $g = &$groups[$key];
                $g['durations'][] = (float) ($json['duration_ms'] ?? 0);
                $g['db'][] = (float) ($json['database_ms'] ?? 0);
                $g['queries'][] = (int) ($json['queries'] ?? 0);
                $g['errors'] = ($g['errors'] ?? 0) + ((int) ($json['status'] ?? 200) >= 500 ? 1 : 0);
                unset($g);
            }
            fclose($handle);
        }
        if ($groups === []) {
            $this->info('No slow requests logged in the last '.$this->option('days').' days.');
            return self::SUCCESS;
        }

        $pct = function (array $values, float $p): float {
            sort($values);
            return $values[(int) floor(($p / 100) * (count($values) - 1))];
        };
        $rows = [];
        foreach ($groups as $key => $g) {
            $rows[] = [
                'where' => mb_strimwidth($key, 0, 90, '…'),
                'count' => count($g['durations']),
                'median_ms' => (int) $pct($g['durations'], 50),
                'p95_ms' => (int) $pct($g['durations'], 95),
                'db_share' => array_sum($g['durations']) > 0 ? (int) round(100 * array_sum($g['db']) / array_sum($g['durations'])).'%' : '-',
                'avg_queries' => (int) round(array_sum($g['queries']) / count($g['queries'])),
                'errors' => $g['errors'] ?? 0,
                'total_s' => round(array_sum($g['durations']) / 1000, 1),
            ];
        }
        usort($rows, fn ($a, $b) => $b['total_s'] <=> $a['total_s']);
        $this->table(['Page → button', 'Times', 'Median ms', 'p95 ms', 'DB share', 'Avg queries', '5xx', 'Total s'],
            array_map('array_values', array_slice($rows, 0, (int) $this->option('top'))));

        return self::SUCCESS;
    }
}
