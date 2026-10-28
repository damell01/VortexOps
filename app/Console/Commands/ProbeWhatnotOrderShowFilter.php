<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;

class ProbeWhatnotOrderShowFilter extends Command
{
    protected $signature = 'whatnot:probe-order-filter {--show-a= : First completed show ID} {--show-b= : Second completed show ID on the same channel}';
    protected $description = 'Read-only probe of Whatnot order source filtering; never imports or modifies orders';

    public function handle(WhatnotScraper $scraper): int
    {
        $ids = array_filter([(int) $this->option('show-a'), (int) $this->option('show-b')]);
        if (count($ids) !== 2 || count(array_unique($ids)) !== 2) {
            $this->error('Provide two distinct show IDs: --show-a=ID --show-b=ID');
            return self::FAILURE;
        }
        $shows = Show::with('channel')->whereIn('id', $ids)->get()->keyBy('id');
        if ($shows->count() !== 2) { $this->error('One or both shows were not found'); return self::FAILURE; }
        $a = $shows[$ids[0]]; $b = $shows[$ids[1]];
        if (! $a->whatnot_channel_id || $a->whatnot_channel_id !== $b->whatnot_channel_id) {
            $this->error('Shows must be on the same Whatnot channel'); return self::FAILURE;
        }
        $sources = [];
        foreach ([$a, $b] as $show) {
            $url = (string) $show->detail_url;
            if (! preg_match('/[a-f0-9]{8}-[a-f0-9-]{27,}/i', $url, $m)) {
                $this->error("Show {$show->id} lacks a recognizable Whatnot live ID in detail_url");
                return self::FAILURE;
            }
            $sources[] = ['show_key' => $show->id, 'live_id' => $m[0]];
        }
        try {
            $result = $scraper->fetchOrdersForShows($sources, $a->channel?->whatnot_username, false,
                fn (string $line) => $this->line($line));
        } catch (\Throwable $e) {
            $this->error('Order source probe failed: '.$e->getMessage());
            return self::FAILURE;
        }
        $owners = []; $counts = [];
        foreach ([$a, $b] as $show) {
            $rows = $result[$show->id] ?? [];
            $counts[$show->id] = count($rows);
            foreach ($rows as $row) {
                $id = trim((string) ($row['order_id'] ?? $row['whatnot_order_id'] ?? ''));
                if ($id !== '') $owners[$id][$show->id] = true;
            }
        }
        $overlaps = count(array_filter($owners, fn ($v) => count($v) > 1));
        $this->line(json_encode(['show_order_counts'=>$counts, 'overlapping_order_ids'=>$overlaps], JSON_PRETTY_PRINT));
        if ($overlaps > 0) {
            $this->error('SOURCE FILTER FAILED: orders appear under both shows. Do not backfill.');
            return self::FAILURE;
        }
        $this->warn('No overlap observed; this does NOT independently prove source filtering. No orders were imported or changed.');
        return self::SUCCESS;
    }
}
