<?php

namespace App\Console\Commands;

use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AuditWhatnotReporting extends Command
{
    protected $signature = 'whatnot:reporting-audit
        {--since=2026-07-01 : Reporting start date}
        {--channel= : Optional channel name, username, or ID}
        {--missing-only : Only print shows with missing reporting data}
        {--limit=200 : Maximum detail rows to print}';

    protected $description = 'Audit Whatnot reporting coverage, totals, source freshness, and shows missing analytics or shipment data';

    public function handle(): int
    {
        try {
            $since = Carbon::parse((string) $this->option('since'))->startOfDay();
        } catch (\Throwable) {
            $this->error('Invalid --since date. Use YYYY-MM-DD.');
            return self::FAILURE;
        }

        $channels = WhatnotChannel::query()
            ->where('include_in_import', true)
            ->where('status', 'active')
            ->when($this->option('channel'), function ($query, $value) {
                $value = ltrim(trim((string) $value), '@');
                $query->where(function ($q) use ($value) {
                    if (is_numeric($value)) $q->orWhere('id', (int) $value);
                    $q->orWhere('name', $value)->orWhere('whatnot_username', $value);
                });
            })
            ->orderBy('id')
            ->get();

        if ($channels->isEmpty()) {
            $this->error('No matching active Whatnot channels found.');
            return self::FAILURE;
        }

        $this->info('WHATNOT REPORTING AUDIT');
        $this->line('Reporting window: ' . $since->toDateString() . ' through ' . today()->toDateString());
        $this->line('Gross = Whatnot reported show sales. Whatnot Payout = Whatnot estimated net/settlement. Business Net = Whatnot payout + tips - product cost - show payouts/profit share.');
        $this->newLine();

        foreach ($channels as $channel) {
            $base = Show::query()
                ->where('whatnot_channel_id', $channel->id)
                ->whereDate('show_date', '>=', $since->toDateString())
                ->whereDate('show_date', '<=', today()->toDateString());

            $completed = (clone $base)->whereNotIn('status', ['cancelled']);
            $totalShows = (clone $base)->count();
            $cancelled = (clone $base)->where('status', 'cancelled')->count();
            $gross = (float) (clone $completed)->sum('gross_revenue');
            $whatnotNet = (float) (clone $completed)->sum('whatnot_net');
            $completedEarnings = (float) (clone $completed)->sum('completed_earnings');
            $tips = (float) (clone $completed)->sum('tips');
            $units = (int) (clone $completed)->sum('units_sold');
            $giveaways = (int) (clone $completed)->sum('giveaways_count');
            $buyers = (int) (clone $completed)->sum('buyers_count');
            $businessNet = (float) (clone $completed)->get()->sum(
                fn (Show $show) => (float) ($show->profitAndLoss()['margin'] ?? 0),
            );

            $missingAnalytics = (clone $completed)
                ->where(function ($q) {
                    $q->whereNull('gross_revenue')->orWhere('gross_revenue', '<=', 0)
                      ->orWhereNull('whatnot_net')->orWhere('whatnot_net', '<=', 0);
                })->count();

            $noShipments = (clone $completed)->doesntHave('shipments')->count();
            $noOrders = (clone $completed)->doesntHave('orders')->count();

            $this->info($channel->name . ' (@' . $channel->whatnot_username . ')');
            $this->table(
                ['Shows', 'Cancelled', 'Gross', 'Whatnot Payout', 'Business Net', 'Completed Earn.', 'Tips', 'Units', 'Giveaways', 'Buyers'],
                [[
                    $totalShows,
                    $cancelled,
                    '$' . number_format($gross, 2),
                    '$' . number_format($whatnotNet, 2),
                    '$' . number_format($businessNet, 2),
                    '$' . number_format($completedEarnings, 2),
                    '$' . number_format($tips, 2),
                    $units,
                    $giveaways,
                    $buyers,
                ]]
            );
            $this->line("Coverage gaps: {$missingAnalytics} missing analytics · {$noShipments} with no shipments · {$noOrders} with no orders");

            $sources = ShowIngestionLog::query()
                ->where('whatnot_channel_id', $channel->id)
                ->whereIn('source', ['whatnot_show_analytics', 'whatnot_orders', 'whatnot_shipments', 'whatnot_ledger'])
                ->where('created_at', '>=', $since)
                ->orderByDesc('created_at')
                ->get()
                ->groupBy('source');

            $sourceRows = [];
            foreach (['whatnot_show_analytics', 'whatnot_orders', 'whatnot_shipments', 'whatnot_ledger'] as $source) {
                $logs = $sources->get($source, collect());
                $last = $logs->first();
                $sourceRows[] = [
                    ShowIngestionLog::sourceLabels()[$source] ?? $source,
                    $last?->created_at?->format('Y-m-d H:i') ?? 'Never',
                    $logs->where('status', 'success')->count(),
                    $logs->where('status', 'partial')->count(),
                    $logs->where('status', 'failed')->count(),
                    $last?->summary() ?? 'No runs in window',
                ];
            }

            $this->table(['Source', 'Last Run', 'OK', 'Partial', 'Failed', 'Last Result'], $sourceRows);

            $details = (clone $base)
                ->withCount(['shipments', 'orders'])
                ->orderByDesc('show_date')
                ->orderByDesc('id')
                ->get()
                ->filter(function (Show $show) {
                    $missingAnalytics = ! in_array($show->status, ['cancelled'], true)
                        && ((float) ($show->gross_revenue ?? 0) <= 0 || (float) ($show->whatnot_net ?? 0) <= 0);
                    $missingShipments = ! in_array($show->status, ['cancelled'], true) && (int) $show->shipments_count === 0;
                    $missingOrders = ! in_array($show->status, ['cancelled'], true) && (int) $show->orders_count === 0;
                    return ! $this->option('missing-only') || $missingAnalytics || $missingShipments || $missingOrders;
                })
                ->take(max(1, min(1000, (int) $this->option('limit'))));

            $rows = $details->map(function (Show $show) {
                $issues = [];
                if (! in_array($show->status, ['cancelled'], true)) {
                    if ((float) ($show->gross_revenue ?? 0) <= 0 || (float) ($show->whatnot_net ?? 0) <= 0) $issues[] = 'analytics';
                    if ((int) $show->shipments_count === 0) $issues[] = 'shipments';
                    if ((int) $show->orders_count === 0) $issues[] = 'orders';
                }

                $pnl = $show->profitAndLoss();

                return [
                    $show->id,
                    $show->show_date?->format('Y-m-d') ?? '—',
                    str($show->title)->limit(38),
                    $show->status,
                    '$' . number_format((float) ($show->gross_revenue ?? 0), 2),
                    '$' . number_format((float) ($show->whatnot_net ?? 0), 2),
                    '$' . number_format((float) ($pnl['margin'] ?? 0), 2),
                    (int) $show->orders_count,
                    (int) $show->shipments_count,
                    $issues ? implode(', ', $issues) : 'complete',
                    $show->last_synced_at?->format('m-d H:i') ?? 'never',
                ];
            })->all();

            if ($rows !== []) {
                $this->table(['ID', 'Date', 'Show', 'Status', 'Gross', 'Whatnot Payout', 'Business Net', 'Orders', 'Ships', 'Missing', 'Last Sync'], $rows);
            }

            $this->newLine();
        }

        $this->line('Gross and Whatnot Payout come from imported Whatnot show analytics. Business Net is VortexOps-calculated after product cost and show payouts/profit share. Ledger refreshes remain the source for later refunds/cancellations/adjustments when Whatnot exposes them.');

        return self::SUCCESS;
    }
}
