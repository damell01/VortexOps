<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardNeedsAttentionWidget;
use App\Filament\Widgets\DashboardShowsKpiWidget;
use App\Filament\Widgets\FulfillmentInventoryWidget;
use App\Filament\Widgets\RecentShowsWidget;
use App\Filament\Widgets\StreamerInventoryWidget;
use App\Filament\Widgets\StreamerOverviewWidget;
use App\Filament\Widgets\StreamerProfitShareWidget;
use App\Filament\Widgets\StreamerShowsToReviewWidget;
use App\Filament\Widgets\UpcomingShowsWidget;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStock;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use Illuminate\Support\Facades\Schema;
use App\Support\ChannelContext;
use Filament\Pages\Dashboard;

class DashboardImproved extends Dashboard
{
    public function mount(): void
    {
        $user = auth()->user();

        if ($user?->isStreamer() && ! $user?->isAdmin() && ! $user?->isOwner()) {
            $this->redirect(StreamerShows::getUrl(), navigate: true);
        }
    }

    public function getView(): string
    {
        return 'filament.pages.dashboard-improved';
    }

    public function getSubheading(): ?string
    {
        $channel = ChannelContext::current();
        $name = $channel?->display_title ?: $channel?->name ?: 'Vortex Breaks';
        return "{$name} operations center";
    }

    public function getWidgets(): array
    {
        if ((bool) Setting::get('demo_mode', false)) return [];

        $user = auth()->user();

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
            return [
                StreamerOverviewWidget::class,
                StreamerInventoryWidget::class,
                StreamerShowsToReviewWidget::class,
                StreamerProfitShareWidget::class,
                RecentShowsWidget::class,
            ];
        }

        if (($user?->isFulfillment() || $user?->isFulfillmentAdmin()) && ! $user?->isAdmin() && ! $user?->isOwner()) {
            return [
                FulfillmentInventoryWidget::class,
                RecentShowsWidget::class,
            ];
        }

        if ($user?->isAdmin() || $user?->isOwner()) {
            return [
                DashboardShowsKpiWidget::class,
                UpcomingShowsWidget::class,
                DashboardNeedsAttentionWidget::class,
                RecentShowsWidget::class,
            ];
        }

        return [];
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $data = ['roleMode' => 'user'];

        if ($user?->isAdmin() || $user?->isOwner()) {
            $data['roleMode'] = 'admin';
            $data['inventoryHealth'] = $this->inventoryHealth();
            $data['recentInventoryActivity'] = $this->recentInventoryActivity();
        }

        if ($user?->isStreamer() && ! $user?->isAdmin() && ! $user?->isOwner()) {
            $streamerId = $user->streamer?->id ?? 0;
            $locationIds = $user->streamer?->inventoryLocations()->pluck('id') ?? collect();

            $assignedShows = Show::query()
                ->inChannelContext()
                ->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId));

            $nextShow = (clone $assignedShows)
                ->whereDate('show_date', '>=', today())
                ->orderBy('show_date')
                ->orderBy('start_time')
                ->first();

            $reportsDue = StreamerLogEntry::query()
                ->where('streamer_id', $streamerId)
                ->where('status', 'pending')
                ->count();

            $unreportedEndedShows = (clone $assignedShows)
                ->whereDate('show_date', '<=', today())
                ->whereNotIn('status', ['closed', 'cancelled'])
                ->whereDoesntHave('streamerLogEntry', fn ($q) => $q->whereNotNull('submitted_at'))
                ->count();

            $data += [
                'roleMode' => 'streamer',
                'nextShow' => $nextShow,
                'reportsDue' => $reportsDue + $unreportedEndedShows,
                'pendingPayouts' => Payout::where('streamer_id', $streamerId)->where('status', 'approved')->count(),
                'inventoryCount' => InventoryItem::whereHas('stock', fn ($q) => $q->whereIn('inventory_location_id', $locationIds)->where('quantity', '>', 0))->where('is_active', true)->count(),
                'inventoryUnits' => (float) \App\Models\InventoryStock::whereIn('inventory_location_id', $locationIds)->sum('quantity'),
                'giveawayUnits30' => (int) StreamerLogItem::where('disposition', 'giveaway')
                    ->whereHas('logEntry', fn ($q) => $q->where('streamer_id', $streamerId)->where('created_at', '>=', now()->subDays(30)))
                    ->sum('quantity'),
            ];
        } elseif (($user?->isFulfillment() || $user?->isFulfillmentAdmin()) && ! $user?->isAdmin() && ! $user?->isOwner()) {
            $showsQuery = Show::query()->inChannelContext()->whereNotIn('status', ['closed', 'cancelled']);
            if (! $user?->isFulfillmentAdmin()) {
                $showsQuery->whereHas('fulfillmentUsers', fn ($q) => $q->where('users.id', $user->id));
            }

            $showIds = (clone $showsQuery)->pluck('shows.id');
            $shipmentQuery = Shipment::whereIn('show_id', $showIds);

            $data += [
                'roleMode' => 'fulfillment',
                'showsToFulfill' => (clone $showsQuery)->where(function ($q) {
                    $q->whereHas('shipments', fn ($s) => $s->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"))
                        ->orWhereHas('orders', fn ($o) => $o->whereNotIn('shipping_status', ['shipped', 'delivered']));
                })->count(),
                'openShipments' => (clone $shipmentQuery)->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")->count(),
                'deliveredToday' => (clone $shipmentQuery)->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'")->whereDate('updated_at', today())->count(),
                'unassignedShows' => $user?->isFulfillmentAdmin()
                    ? Show::query()->inChannelContext()->whereHas('shipments')->whereDoesntHave('fulfillmentUsers')->whereNotIn('status', ['closed', 'cancelled'])->count()
                    : 0,
            ];
        }

        return $data;
    }

    private function inventoryHealth(): array
    {
        try {
            return \Illuminate\Support\Facades\Cache::remember(
                'dashboard:inventory_health:' . (ChannelContext::currentId() ?? 'all'),
                60,
                function (): array {
                    $stockTotals = \Illuminate\Support\Facades\DB::table('inventory_stock')
                        ->selectRaw('inventory_item_id, SUM(quantity) as on_hand')
                        ->when(ChannelContext::isScoped(), fn ($q) => $q
                            ->join('inventory_locations', 'inventory_locations.id', '=', 'inventory_stock.inventory_location_id')
                            ->where('inventory_locations.whatnot_channel_id', ChannelContext::currentId()))
                        ->groupBy('inventory_item_id');

                    $row = \Illuminate\Support\Facades\DB::table('products')
                        ->leftJoinSub($stockTotals, 'stock_totals', 'stock_totals.inventory_item_id', '=', 'products.id')
                        ->where('products.is_active', true)
                        ->selectRaw('SUM(CASE WHEN COALESCE(stock_totals.on_hand, 0) > 0 AND (products.reorder_level IS NULL OR stock_totals.on_hand > products.reorder_level) THEN 1 ELSE 0 END) as in_stock')
                        ->selectRaw('SUM(CASE WHEN COALESCE(stock_totals.on_hand, 0) > 0 AND products.reorder_level IS NOT NULL AND stock_totals.on_hand <= products.reorder_level THEN 1 ELSE 0 END) as low_stock')
                        ->selectRaw('SUM(CASE WHEN COALESCE(stock_totals.on_hand, 0) <= 0 THEN 1 ELSE 0 END) as out_stock')
                        ->first();

                    return [
                        'in' => (int) ($row->in_stock ?? 0),
                        'low' => (int) ($row->low_stock ?? 0),
                        'out' => (int) ($row->out_stock ?? 0),
                    ];
                }
            );
        } catch (\Throwable) {
            return ['in' => 0, 'low' => 0, 'out' => 0];
        }
    }

    private function recentInventoryActivity(): array
    {
        try {
            return InventoryMovement::query()->with('item')->latest()->limit(5)->get()->map(function ($m) {
                $qty = (float) ($m->quantity ?? 0);
                return [
                    'name' => $m->item?->name ?: 'Inventory item',
                    'qty' => $qty,
                    'type' => ucfirst(str_replace('_', ' ', (string) $m->movement_type)),
                    'time' => $m->created_at?->diffForHumans(),
                ];
            })->all();
        } catch (\Throwable) { return []; }
    }
}

