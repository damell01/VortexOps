<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WhatnotSyncHealth extends Command
{
    protected $signature = 'whatnot:sync-health {--max-age=10 : Maximum minutes since successful fresh sync} {--fail-on-stale}';
    protected $description = 'Report per-channel Whatnot API freshness, failures, and order pagination backlog without changing locks';

    public function handle(): int
    {
        $maxAge = max(1, (int) $this->option('max-age'));
        $stale = 0;
        foreach ([1, 2, 3, 4] as $channel) {
            $key = 'whatnot:sync-health:'.$channel.':fresh';
            $success = Cache::get($key.':last_success');
            $result = Cache::get($key.':last_result', []);
            $age = $success ? now()->diffInMinutes(\Carbon\Carbon::parse($success)) : null;
            $isStale = $age === null || $age > $maxAge;
            if ($isStale) $stale++;
            $orders = $result['orders'] ?? [];
            $this->line(json_encode([
                'channel' => $channel,
                'last_success' => $success,
                'age_minutes' => $age === null ? null : round($age, 1),
                'stale' => $isStale,
                'last_run_ok' => $result['ok'] ?? null,
                'duration_seconds' => $result['duration_seconds'] ?? null,
                'orders_pages' => $orders['pages'] ?? null,
                'orders_incomplete' => $orders['incomplete'] ?? null,
                'errors' => $result['errors'] ?? [],
            ]));
        }
        if ($stale) $this->warn($stale.' channel(s) lack a recent successful fresh sync.');
        return $stale && $this->option('fail-on-stale') ? self::FAILURE : self::SUCCESS;
    }
}
