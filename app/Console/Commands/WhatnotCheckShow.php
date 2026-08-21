<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\WhatnotChannel;
use App\Services\WhatnotScraper;
use Illuminate\Console\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;

class WhatnotCheckShow extends Command
{
    protected $signature = 'whatnot:check-show
                            {live-id : Whatnot livestream UUID}
                            {--channel= : WhatnotChannel name or ID}
                            {--debug : Show scraper progress/debug output}
                            {--no-orders : Skip order scraping}';

    protected $description = 'Import and verify one Whatnot show end-to-end: analytics, orders, and shipments';

    public function handle(WhatnotScraper $scraper): int
    {
        $liveId = trim((string) $this->argument('live-id'));
        $debug = (bool) $this->option('debug');
        $withOrders = ! (bool) $this->option('no-orders');

        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $liveId)) {
            $this->error('live-id must be a valid UUID.');
            return self::FAILURE;
        }

        $channel = $this->resolveChannel($this->option('channel'));
        if ($this->option('channel') && ! $channel) {
            $this->error('Channel not found: ' . $this->option('channel'));
            return self::FAILURE;
        }

        $this->info('Checking one Whatnot show end-to-end…');
        $this->line("Live ID: {$liveId}");
        if ($channel) {
            $this->line("Channel: {$channel->name} (@{$channel->whatnot_username})");
        }
        $this->newLine();

        try {
            $result = $scraper->importShows(
                channel: $channel,
                limit: 1,
                debug: $debug,
                withOrders: $withOrders,
                onProgress: fn (string $line) => $this->line('  <fg=gray>' . OutputFormatter::escape($line) . '</>'),
                seedLiveId: $liveId,
            );
        } catch (\Throwable $e) {
            $this->error('Analytics/import failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $show = Show::query()
            ->when($channel, fn ($q) => $q->where('whatnot_channel_id', $channel->id))
            ->where(function ($q) use ($liveId) {
                $q->where('whatnot_show_id', $liveId)
                    ->orWhere('detail_url', 'like', '%' . $liveId . '%');
            })
            ->latest('id')
            ->first();

        if (! $show) {
            $this->error('The analytics scrape completed, but no Show row matching this live-id was persisted.');
            $this->line('Import result: ' . json_encode($result, JSON_UNESCAPED_SLASHES));
            return self::FAILURE;
        }

        $show->loadMissing('channel');

        $shipmentResult = ['updated' => 0, 'skipped_shows' => 0];
        try {
            $shipmentResult = $scraper->refreshShipmentsForShows(
                collect([$show]),
                $show->channel?->whatnot_username,
                $debug,
            );
        } catch (\Throwable $e) {
            $this->warn('Shipment scrape failed: ' . $e->getMessage());
        }

        $show->refresh();
        $ordersCount = $show->orders()->count();
        $shipmentsCount = $show->shipments()->count();

        $this->newLine();
        $this->info('Show analytics');
        $this->table(
            ['Field', 'Value'],
            [
                ['DB Show ID', $show->id],
                ['Whatnot Live ID', $show->whatnot_show_id ?: $liveId],
                ['Title', $show->title ?: '—'],
                ['Show Date', $show->show_date?->format('Y-m-d') ?: '—'],
                ['Estimated Sales', $this->money($show->gross_revenue)],
                ['Total Estimated Earnings', $this->money($show->whatnot_net)],
                ['Completed Earnings', $this->money($show->completed_earnings)],
                ['Orders / Units Sold', (string) ($show->units_sold ?? 0)],
                ['AOV', $this->money($show->avg_order_value)],
                ['Giveaway Spend', $this->money($show->giveaway_spend)],
                ['Buyers', (string) ($show->buyers_count ?? 0)],
                ['Shares', (string) ($show->shares_count ?? 0)],
                ['Duration', $this->duration($show->show_duration)],
                ['Max Concurrent Viewers', (string) ($show->max_concurrent_viewers ?? 0)],
                ['Total Views', (string) ($show->total_views ?? 0)],
                ['Imported Order Rows', (string) $ordersCount],
                ['Shipment Rows', (string) $shipmentsCount],
            ],
        );

        $this->line(sprintf(
            'Import result: %d created, %d updated, %d skipped, %d order(s) created.',
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['skipped'] ?? 0,
            $result['ordersCreated'] ?? 0,
        ));
        $this->line(sprintf(
            'Shipment refresh: %d change(s), %d show(s) skipped.',
            $shipmentResult['updated'] ?? 0,
            $shipmentResult['skipped_shows'] ?? 0,
        ));

        $analyticsOk = collect([
            $show->gross_revenue,
            $show->whatnot_net,
            $show->completed_earnings,
            $show->avg_order_value,
            $show->buyers_count,
            $show->total_views,
        ])->contains(fn ($value) => $value !== null);

        $this->newLine();
        if ($analyticsOk) {
            $this->info('✓ Analytics fields were populated for this show.');
        } else {
            $this->warn('Analytics row exists, but the expected analytics fields are still empty. Check the scraper debug output.');
        }

        if ($withOrders) {
            $ordersCount > 0
                ? $this->info("✓ {$ordersCount} order row(s) are tied to this show.")
                : $this->warn('No order rows were tied to this show.');
        }

        $shipmentsCount > 0
            ? $this->info("✓ {$shipmentsCount} shipment row(s) are tied to this show.")
            : $this->warn('No shipment rows are currently tied to this show.');

        return $analyticsOk ? self::SUCCESS : self::FAILURE;
    }

    private function resolveChannel(mixed $value): ?WhatnotChannel
    {
        if (! $value) {
            return null;
        }

        return is_numeric($value)
            ? WhatnotChannel::find($value)
            : WhatnotChannel::where('name', $value)
                ->orWhere('whatnot_username', $value)
                ->first();
    }

    private function money(mixed $value): string
    {
        return $value === null ? '—' : '$' . number_format((float) $value, 2);
    }

    private function duration(mixed $seconds): string
    {
        if ($seconds === null) {
            return '—';
        }

        $seconds = (int) $seconds;
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        return $hours > 0
            ? sprintf('%dh %02dm %02ds', $hours, $minutes, $remaining)
            : sprintf('%dm %02ds', $minutes, $remaining);
    }
}
