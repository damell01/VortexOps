<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Read-only: how long the main pages take to build on this server, as a given
 * person, and where the time goes (query count, database time, slowest query).
 * Each page is built twice and the second (warm) run is reported, which is
 * what someone clicking around feels. Nothing is changed.
 */
class PerfPages extends Command
{
    protected $signature = 'perf:pages {--user= : Email of the person to load pages as (default: the owner)} {--path=* : Extra paths to time, e.g. --path=/admin/payouts} {--only=* : Time only these paths} {--explain : Also print each page\'s slowest query in full, with the database\'s EXPLAIN plan}';

    protected $description = 'Time the main pages as a user: total ms, queries, database ms and the slowest query (read-only)';

    private const PATHS = [
        '/admin', '/admin/shows-overview', '/admin/fulfillment', '/admin/inventory-overview', '/admin/inventory-items',
        '/admin/inventory-scanner', '/admin/pallets', '/admin/stock-status', '/admin/payouts', '/admin/weekly-payout-batches',
        '/admin/whatnot-ledgers', '/admin/reports', '/admin/show-performance', '/admin/streamers',
    ];

    private int $queries = 0;
    private float $dbMs = 0.0;
    private array $slowest = ['ms' => 0, 'sql' => ''];

    public function handle(): int
    {
        DB::listen(function (QueryExecuted $q) {
            $this->queries++;
            $this->dbMs += $q->time;
            if ($q->time > $this->slowest['ms']) $this->slowest = ['ms' => $q->time, 'sql' => $q->sql, 'bindings' => $q->bindings];
        });

        $user = $this->option('user')
            ? User::query()->where('email', $this->option('user'))->first()
            : User::query()->where('email', config('app.owner_email') ?: 'dbellcreations@gmail.com')->first() ?? User::query()->orderBy('id')->first();
        if (! $user) {
            $this->error('No such user.');

            return self::FAILURE;
        }

        $paths = $this->option('only') ?: array_merge(self::PATHS, $this->option('path'));
        $this->info('Timing ' . count($paths) . ' pages as user #' . $user->id . ' (second, warm load of each)…');

        $rows = [];
        $plans = [];
        foreach ($paths as $path) {
            $this->measure($path, $user); // warm-up: autoload, opcache, caches
            $rows[] = $this->measure($path, $user);
            if ($this->option('explain') && $this->slowest['sql'] !== '') $plans[$path] = $this->slowest;
        }

        usort($rows, fn ($a, $b) => $b[2] <=> $a[2]);
        $this->table(['Page', 'Status', 'Total ms', 'Queries', 'DB ms', 'Slowest query (ms)'], array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[3], $r[4], $r[5]], $rows));
        foreach ($plans as $path => $q) {
            $this->line('');
            $this->info($path . ' — slowest query, ' . round($q['ms']) . ' ms');
            $this->line(\Illuminate\Support\Str::replaceArray('?', array_map(fn ($b) => is_numeric($b) ? (string) $b : "'" . str_replace("'", "''", (string) $b) . "'", $q['bindings'] ?? []), $q['sql']));
            try {
                $plan = DB::select('EXPLAIN ' . $q['sql'], $q['bindings'] ?? []);
                $this->table(array_keys((array) ($plan[0] ?? [])), array_map(fn ($r) => array_map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v), (array) $r), $plan));
            } catch (\Throwable $e) {
                $this->line('EXPLAIN failed: ' . $e->getMessage());
            }
        }
        $this->line('Under ~300 ms feels instant with SPA navigation; over ~1000 ms is what shows the loading bar.');

        return self::SUCCESS;
    }

    /** @return array{0:string,1:int|string,2:int,3:int,4:int,5:string} */
    private function measure(string $path, User $user): array
    {
        $this->queries = 0;
        $this->dbMs = 0.0;
        $this->slowest = ['ms' => 0, 'sql' => ''];

        Auth::guard('web')->setUser($user);
        $kernel = app(Kernel::class);
        $request = Request::create(url($path), 'GET');
        $start = microtime(true);
        try {
            $response = $kernel->handle($request);
            $status = $response->getStatusCode();
            $kernel->terminate($request, $response);
        } catch (\Throwable $e) {
            $status = 'error: ' . mb_substr($e->getMessage(), 0, 60);
        }
        $ms = (int) round((microtime(true) - $start) * 1000);

        return [$path, $status, $ms, $this->queries, (int) round($this->dbMs), $this->slowest['sql'] === '' ? '—' : round($this->slowest['ms']) . ' ms · ' . mb_substr(preg_replace('/\s+/', ' ', $this->slowest['sql']), 0, 90)];
    }
}
