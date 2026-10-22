<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class SyncWhatnotTipsBatch extends Command
{
    protected $signature = 'whatnot:sync-tips-batch
        {--mode=recent : recent or history}
        {--limit=2 : Maximum shows per run}
        {--channel= : Restrict to a Whatnot username}
        {--apply : Persist verified tips; without this, preview only}';

    protected $description = 'Safely sync a bounded number of verified livestream tip totals across Whatnot channels';

    public function handle(): int
    {
        $mode = (string) $this->option('mode');
        if (! in_array($mode, ['recent', 'history'], true)) {
            $this->error('Mode must be recent or history.');
            return self::FAILURE;
        }
        $limit = min(10, max(1, (int) $this->option('limit')));
        $lock = Cache::lock('whatnot:tips-batch', 14400);
        if (! $lock->get()) {
            $this->warn('A tips batch is already running; skipping.');
            return self::SUCCESS;
        }
        try {
            $channels = WhatnotChannel::query()
                ->whereIn('id', WhatnotChannel::importedIds())
                ->when($this->option('channel'), function ($q) {
                    $q->where('whatnot_username', ltrim((string) $this->option('channel'), '@'));
                })->get()->keyBy('id');
            if ($channels->isEmpty()) {
                $this->warn('No eligible channels.');
                return self::SUCCESS;
            }
            $today = Carbon::now('America/Chicago')->toDateString();
            $cutoff = Carbon::now('America/Chicago')->subDays(14)->toDateString();
            $freshness = now()->subHours($mode === 'recent' ? 24 : 24 * 365 * 10);
            $query = Show::query()
                ->whereIn('whatnot_channel_id', $channels->keys()->all())
                ->whereNotNull('whatnot_show_id')
                ->where('whatnot_show_id', '!=', '')
                ->whereDate('show_date', '<=', $today)
                ->where(function ($q) use ($freshness) {
                    $q->whereNull('tips_verified_at')->orWhere('tips_verified_at', '<', $freshness);
                });
            if ($mode === 'recent') {
                $query->whereDate('show_date', '>=', $cutoff)
                    ->orderByRaw('CASE WHEN tips_verified_at IS NULL THEN 0 ELSE 1 END')
                    ->orderByDesc('show_date')->orderBy('tips_verified_at');
            } else {
                $query->whereDate('show_date', '<', $cutoff)
                    ->whereNull('tips_verified_at')
                    ->orderByDesc('show_date');
            }
            // A bounded candidate pool avoids repeatedly choosing the same channel.
            $candidates = $query->limit(250)->get();
            $selected = [];
            $perChannel = [];
            foreach ($candidates as $show) {
                $channelId = (int) $show->whatnot_channel_id;
                if (($perChannel[$channelId] ?? 0) >= 1) {
                    continue;
                }
                $selected[] = $show;
                $perChannel[$channelId] = 1;
                if (count($selected) >= $limit) {
                    break;
                }
            }
            if (! $selected) {
                $this->info('No shows currently need tip verification.');
                return self::SUCCESS;
            }
            $failed = 0;
            foreach ($selected as $show) {
                $username = (string) $channels[$show->whatnot_channel_id]->whatnot_username;
                $this->line('Checking show '.$show->id.' on @'.$username);
                $options = ['--channel' => $username, '--show-id' => $show->id];
                if ($this->option('apply')) {
                    $options['--apply'] = true;
                }
                $exit = Artisan::call('whatnot:sync-show-tips', $options);
                $this->output->write(Artisan::output());
                if ($exit !== 0) {
                    $failed++;
                }
            }
            $this->info('Processed '.count($selected).' shows; failures: '.$failed);
            return $failed ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
