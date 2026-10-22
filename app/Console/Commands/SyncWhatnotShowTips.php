<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

class SyncWhatnotShowTips extends Command
{
    protected $signature = 'whatnot:sync-show-tips {--channel= : Whatnot channel username} {--show-id= : Local show ID} {--livestream-id= : Exact Whatnot livestream UUID} {--limit=1 : Number of recent shows} {--apply : Persist verified totals}';
    protected $description = 'Preview or sync exact per-livestream Whatnot tips via Scrapling';

    public function handle(): int
    {
        $username = ltrim(trim((string) $this->option('channel')), '@');
        $channel = WhatnotChannel::query()->where('whatnot_username', $username)->first();
        if (! $channel) {
            $this->error('Specify an existing Whatnot username with --channel.');
            return self::FAILURE;
        }
        $query = Show::query()->where('whatnot_channel_id', $channel->id)
            ->whereNotNull('whatnot_show_id')->where('whatnot_show_id', '!=', '');
        if ($this->option('livestream-id')) {
            $query->where('whatnot_show_id', strtolower(trim((string) $this->option('livestream-id'))));
        }
        if ($this->option('show-id')) {
            $query->whereKey((int) $this->option('show-id'));
        } else {
            $query->orderByDesc('show_date')->orderByDesc('id')
                ->limit(min(100, max(1, (int) $this->option('limit'))));
        }
        $shows = $query->get();
        if ($shows->isEmpty()) {
            $this->error('No matching show with a livestream ID on this channel.');
            return self::FAILURE;
        }
        foreach ($shows as $show) {
            $liveId = strtolower(trim((string) $show->whatnot_show_id));
            if (! preg_match('/^[a-f0-9]{8}-(?:[a-f0-9]{4}-){3}[a-f0-9]{12}$/', $liveId)) {
                $this->warn('Skipping show '.$show->id.': invalid livestream UUID');
                continue;
            }
            $process = new Process(
                ['node', base_path('scripts/whatnot-runner.cjs')],
                base_path(),
                [
                    'WHATNOT_MODE' => 'tips-sync',
                    'WHATNOT_HEADLESS' => 'true',
                    'WHATNOT_CHANNEL_NAME' => $username,
                    'WHATNOT_TIPS_LIVESTREAM_ID' => $liveId,
                ],
                null,
                1300
            );
            $this->line('Fetching show '.$show->id.': '.$liveId);
            $process->run();
            if (! $process->isSuccessful()) {
                $this->error('Scrapling failed: '.substr($process->getErrorOutput(), -1200));
                return self::FAILURE;
            }
            $data = json_decode(trim($process->getOutput()), true);
            if (! is_array($data) || ($data['livestream_id'] ?? null) !== $liveId ||
                ! is_array($data['tips'] ?? null) ||
                count($data['tips']) !== ($data['reported_count'] ?? -1) ||
                count($data['tips']) !== ($data['unique_tips'] ?? -1)) {
                $this->error('Incomplete tip response for show '.$show->id);
                return self::FAILURE;
            }
            $rows = [];
            $total = 0;
            foreach ($data['tips'] as $tip) {
                if (! is_array($tip) || ! is_string($tip['id'] ?? null) ||
                    ($tip['id'] ?? '') === '' || ! is_int($tip['amount_cents'] ?? null) ||
                    ($tip['amount_cents'] ?? 0) < 0 || ($tip['currency'] ?? null) !== 'USD') {
                    $this->error('Invalid tip for show '.$show->id);
                    return self::FAILURE;
                }
                $total += $tip['amount_cents'];
                $rows[] = [
                    'whatnot_tip_id' => $tip['id'],
                    'whatnot_livestream_id' => $liveId,
                    'whatnot_channel_id' => $channel->id,
                    'show_id' => $show->id,
                    'amount_cents' => $tip['amount_cents'],
                    'currency' => 'USD',
                    'tipped_at' => $tip['created_at'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if ($total !== ($data['total_cents'] ?? null)) {
                $this->error('Tip total mismatch for show '.$show->id);
                return self::FAILURE;
            }
            $amount = number_format($total / 100, 2, '.', '');
            $this->info('Show '.$show->id.': '.count($rows).' tips, $'.$amount);
            if (! $this->option('apply')) {
                $this->line('Preview only. Use --apply to save.');
                continue;
            }
            DB::transaction(function () use ($rows, $show, $amount, $liveId, $channel) {
                foreach ($rows as $row) {
                    $existing = DB::table('whatnot_show_tips')
                        ->where('whatnot_tip_id', $row['whatnot_tip_id'])->first();
                    if ($existing && ($existing->show_id != $show->id ||
                        $existing->whatnot_channel_id != $channel->id ||
                        $existing->whatnot_livestream_id !== $liveId)) {
                        throw new \RuntimeException('Tip already belongs to another show/channel');
                    }
                    DB::table('whatnot_show_tips')->updateOrInsert(
                        ['whatnot_tip_id' => $row['whatnot_tip_id']], $row
                    );
                }
                $stale = DB::table('whatnot_show_tips')->where('show_id', $show->id);
                if ($rows) {
                    $stale->whereNotIn('whatnot_tip_id', array_column($rows, 'whatnot_tip_id'));
                }
                $stale->delete();
                $show->tips = $amount;
                $show->tips_verified_at = now();
                $show->save();
            });
            $this->info('Saved show '.$show->id);
        }
        return self::SUCCESS;
    }
}
