<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatnotSyncHealth extends Command
{
    protected $signature = 'whatnot:sync-health {--max-age=10 : Maximum minutes since successful fresh sync} {--fail-on-stale} {--alert : Log actionable stale/backlog warnings, deduplicated for one hour}';
    protected $description = 'Report per-channel Whatnot API freshness, failures, and order pagination backlog without changing locks';

    public function handle(): int
    {
        $maxAge = max(1, (int) $this->option('max-age'));
        $stale = 0;
        $channels = DB::table('whatnot_api_connections')->where('enabled', true)->where('sync_enabled', true)->pluck('whatnot_channel_id');
        foreach ($channels as $channel) {
            $key = 'whatnot:sync-health:'.$channel.':fresh';
            $row = DB::table('whatnot_sync_health')->where('whatnot_channel_id', $channel)->where('mode', 'fresh')->first();
            $catchup = DB::table('whatnot_sync_health')->where('whatnot_channel_id', $channel)->where('mode', 'catchup')->first();
            $success = $row?->last_success_at ?? Cache::get($key.':last_success');
            $result = $row ? [
                'ok' => $row->last_run_ok === null ? null : (bool) $row->last_run_ok,
                'duration_seconds' => $row->duration_seconds,
                'orders' => ['pages' => $row->orders_pages, 'incomplete' => $row->orders_incomplete === null ? null : (bool) $row->orders_incomplete],
                'errors' => json_decode($row->errors ?? '[]', true) ?: [],
            ] : Cache::get($key.':last_result', []);
            $age = $success ? max(0, \Carbon\Carbon::parse($success)->diffInMinutes(now(), false)) : null;
            $isStale = $age === null || $age > $maxAge;
            if ($isStale) $stale++;
            $orders = $result['orders'] ?? [];
            if ($this->option('alert') && ($isStale || ($catchup && ! $catchup->last_complete_at && $catchup->last_run_at && \Carbon\Carbon::parse($catchup->last_run_at)->lt(now()->subMinutes(30))) || ($result['ok'] ?? true) === false)) {
                $reason = $isStale ? 'stale' : (($result['ok'] ?? true) === false ? 'failed' : 'catchup_not_completed');
                $alertKey = 'whatnot:sync-alert:'.$channel.':'.$reason;
                if (Cache::add($alertKey, true, now()->addHour())) {
                    Log::warning('Whatnot API sync requires attention', ['channel' => $channel, 'reason' => $reason, 'age_minutes' => $age, 'consecutive_incomplete' => $row?->consecutive_incomplete ?? 0, 'errors' => $result['errors'] ?? []]);
                }
            }
            $this->line(json_encode([
                'channel' => $channel,
                'last_success' => $success,
                'age_minutes' => $age === null ? null : round($age, 1),
                'stale' => $isStale,
                'last_run_ok' => $result['ok'] ?? null,
                'duration_seconds' => $result['duration_seconds'] ?? null,
                'orders_pages' => $orders['pages'] ?? null,
                'orders_incomplete' => $orders['incomplete'] ?? null,
                'consecutive_incomplete' => $row?->consecutive_incomplete ?? null,
                'catchup_last_complete' => $catchup?->last_complete_at,
                'catchup_last_run' => $catchup?->last_run_at,
                'catchup_incomplete' => $catchup?->orders_incomplete === null ? null : (bool) $catchup->orders_incomplete,
                'errors' => $result['errors'] ?? [],
            ]));
        }
        if ($stale) $this->warn($stale.' channel(s) lack a recent successful fresh sync.');
        return $stale && $this->option('fail-on-stale') ? self::FAILURE : self::SUCCESS;
    }
}
