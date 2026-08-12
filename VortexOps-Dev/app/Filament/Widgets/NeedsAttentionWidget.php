<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\ProductInsights;
use App\Filament\Pages\SystemHealth;
use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\ShowResource;
use App\Filament\Resources\StreamerLogResource;
use App\Filament\Resources\StreamerResource;
use App\Models\DeductionRequest;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Support\AdminModules;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One place that answers "what needs me today?" — a compact list of actionable
 * items with counts and deep links. Each row only appears when its count > 0,
 * so a healthy install shows a friendly all-clear.
 */
class NeedsAttentionWidget extends Widget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    // Deferred so the dashboard HTML returns instantly; the widget's queries
    // run in a follow-up request rather than blocking the initial page load.
    protected static bool $isLazy = true;
    protected static ?string $heading = 'Needs Attention';
    protected string $view = 'filament.widgets.needs-attention';

    public static function canView(): bool
    {
        return (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    }

    protected function getViewData(): array
    {
        return ['items' => $this->items()];
    }

    /** @return array<int,array<string,mixed>> */
    private function items(): array
    {
        $items = [];

        $add = function (bool $when, int $count, string $label, string $icon, string $color, string $url) use (&$items) {
            if ($when && $count > 0) {
                $items[] = compact('count', 'label', 'icon', 'color', 'url');
            }
        };

        try {
            $add(
                AdminModules::isEnabled('streamer_log') && Schema::hasTable('streamer_log_entries'),
                StreamerLogEntry::where('status', 'pending')->inChannelContext()->count(),
                'streamer logs awaiting review',
                'heroicon-o-clipboard-document-list',
                'warning',
                StreamerLogResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('streams') && Schema::hasColumn('shows', 'channel_attribution_suspect'),
                Show::where('channel_attribution_suspect', true)->inChannelContext()->count(),
                'shows need a channel confirmed',
                'heroicon-o-check-badge',
                'warning',
                ShowResource::getUrl('index', ['tableFilters[channel_attribution_suspect][value]' => '1']),
            );

            $add(
                AdminModules::isEnabled('streams') && Schema::hasColumn('shows', 'financials_revised_after_lock'),
                Show::where('financials_revised_after_lock', true)->inChannelContext()->count(),
                'shows had financials change after being locked in — review before payout',
                'heroicon-o-exclamation-triangle',
                'danger',
                ShowResource::getUrl('index', ['tableFilters[financials_revised_after_lock][value]' => '1']),
            );

            $add(
                Schema::hasTable('deduction_requests'),
                DeductionRequest::where('status', 'pending')->inChannelContext()->count(),
                'deduction requests pending approval',
                'heroicon-o-clipboard-document-check',
                'info',
                DeductionRequestResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('inventory_stock'),
                $this->lowStockCount(),
                'items at or below reorder level',
                'heroicon-o-cube',
                'danger',
                InventoryItemResource::getUrl(),
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('inventory_stock'),
                $this->deadStockCount(),
                'products dead on the shelf (capital stuck)',
                'heroicon-o-archive-box-x-mark',
                'warning',
                ProductInsights::getUrl(),
            );

            $add(
                AdminModules::isEnabled('inventory') && Schema::hasTable('whatnot_show_orders'),
                $this->zeroStockMappedSalesCount(),
                'sold items mapped to a product with no stock — likely mis-mapped',
                'heroicon-o-magnifying-glass',
                'danger',
                InventoryItemResource::getUrl(),
            );

            $staleHours = $this->importStaleHours();
            $add(
                $staleHours !== null,
                $staleHours ?? 0,
                'hours since Whatnot last imported — the scraper may be failing',
                'heroicon-o-cloud-arrow-down',
                'danger',
                SystemHealth::getUrl(),
            );

            $add(
                Schema::hasTable('failed_jobs'),
                (int) DB::table('failed_jobs')->count(),
                'failed background jobs',
                'heroicon-o-exclamation-triangle',
                'danger',
                SystemHealth::getUrl(),
            );

            $add(
                AdminModules::isEnabled('streams'),
                $this->trendingDownStreamerCount(),
                'streamers trending down vs. their recent average — worth a check-in',
                'heroicon-o-arrow-trending-down',
                'warning',
                StreamerResource::getUrl(),
            );
        } catch (\Throwable) {
            // On a partially-migrated install just show what we could gather.
        }

        return $items;
    }

    /**
     * Hours since the scheduled Whatnot import last succeeded, but only when
     * import is actually configured and the gap is meaningful (> 2h; imports
     * run every 15 min). Null means healthy or not-in-use — no alert.
     */
    private function importStaleHours(): ?int
    {
        try {
            if (! Schema::hasTable('whatnot_channels')
                || ! \App\Models\WhatnotChannel::where('include_in_import', true)->exists()) {
                return null;
            }

            $last = \App\Models\Setting::get('whatnot_last_import_success_at');
            if (! $last) {
                return null;
            }

            $hours = (int) \Illuminate\Support\Carbon::parse($last)->diffInHours(now());

            return $hours >= 2 ? $hours : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** Active products holding stock that hasn't sold within the dead-stock window. */
    private function deadStockCount(): int
    {
        try {
            $cutoff = now()->subDays(ProductInsights::DEAD_DAYS)->toDateString();

            return InventoryItem::query()
                ->where('is_active', true)
                // Has stock on hand.
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('inventory_stock')
                        ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                        ->when(\App\Support\ChannelContext::isScoped(), fn ($q) => $q
                            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
                            ->where('inventory_locations.whatnot_channel_id', \App\Support\ChannelContext::currentId()))
                        ->groupBy('inventory_stock.inventory_item_id')
                        ->havingRaw('SUM(inventory_stock.quantity) > 0');
                })
                // No sale inside the window (covers never-sold too).
                ->whereNotExists(function ($query) use ($cutoff) {
                    $query->selectRaw('1')
                        ->from('whatnot_show_orders')
                        ->whereColumn('whatnot_show_orders.inventory_item_id', 'products.id')
                        ->where('whatnot_show_orders.show_date', '>=', $cutoff)
                        ->when(\App\Support\ChannelContext::isScoped(), fn ($q) => $q
                            ->join('inventory_locations', 'inventory_locations.id', '=', 'whatnot_show_orders.inventory_location_id')
                            ->where('inventory_locations.whatnot_channel_id', \App\Support\ChannelContext::currentId()));
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Distinct products mapped to a recent sold-item line while showing zero
     * stock everywhere and no restock in 14+ days — the same "can't have sold
     * something with no stock" nudge shown inline when a streamer maps an
     * item, aggregated here so ops sees the system-wide picture too.
     */
    private function zeroStockMappedSalesCount(): int
    {
        try {
            $cutoff        = now()->subDays(60)->toDateString();
            $restockCutoff = now()->subDays(14);

            return InventoryItem::query()
                ->whereExists(function ($query) use ($cutoff) {
                    $query->selectRaw('1')
                        ->from('whatnot_show_orders')
                        ->whereColumn('whatnot_show_orders.inventory_item_id', 'products.id')
                        ->where('whatnot_show_orders.show_date', '>=', $cutoff)
                        ->when(\App\Support\ChannelContext::isScoped(), fn ($q) => $q
                            ->join('inventory_locations', 'inventory_locations.id', '=', 'whatnot_show_orders.inventory_location_id')
                            ->where('inventory_locations.whatnot_channel_id', \App\Support\ChannelContext::currentId()));
                })
                ->whereNotExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('inventory_stock')
                        ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                        ->groupBy('inventory_stock.inventory_item_id')
                        ->havingRaw('SUM(inventory_stock.quantity) > 0');
                })
                ->whereNotExists(function ($query) use ($restockCutoff) {
                    $query->selectRaw('1')
                        ->from('inventory_movements')
                        ->whereColumn('inventory_movements.inventory_item_id', 'products.id')
                        ->whereIn('inventory_movements.movement_type', ['opening', 'return'])
                        ->where('inventory_movements.created_at', '>=', $restockCutoff);
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** Active streamers whose most recently completed week is down 30%+ vs. their trailing 3-week average. */
    private function trendingDownStreamerCount(): int
    {
        try {
            return Streamer::where('status', 'active')
                ->inChannelContext()
                ->get()
                ->filter(fn (Streamer $s) => $s->isPerformanceTrendingDown())
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function lowStockCount(): int
    {
        try {
            return InventoryItem::query()
                ->where('is_active', true)
                ->whereNotNull('reorder_level')
                ->whereExists(function ($query) {
                    $query->selectRaw('1')
                        ->from('inventory_stock')
                        ->whereColumn('inventory_stock.inventory_item_id', 'products.id')
                        ->when(\App\Support\ChannelContext::isScoped(), fn ($q) => $q
                            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
                            ->where('inventory_locations.whatnot_channel_id', \App\Support\ChannelContext::currentId()))
                        ->groupBy('inventory_stock.inventory_item_id')
                        ->havingRaw('SUM(inventory_stock.quantity) <= products.reorder_level');
                })
                ->count();
        } catch (\Throwable) {
            return 0;
        }
    }
}
