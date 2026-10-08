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
            $data['adminSummary'] = $this->adminSummary();
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
                ->tap(fn ($q) => \App\Support\ShowReportingGoLive::scope($q))
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

    private function adminSummary(): array
    {
        try {
            $start = now()->subDays(29)->startOfDay();
            $shows = Show::query()->inChannelContext()
                ->whereBetween('show_date', [$start->toDateString(), now()->endOfDay()->toDateTimeString()])
                ->whereNotIn('status', ['cancelled'])->get();

            $inventoryValue = InventoryStock::query()
                ->with('item:id,average_cost,unit_cost')
                ->get()
                ->sum(fn ($stock) => (float) $stock->quantity * (float) ($stock->item?->average_cost ?: $stock->item?->unit_cost ?: 0));

            return [
                'shows' => $shows->count(),
                'gross' => (float) $shows->sum('gross_revenue'),
                'net' => (float) $shows->sum('whatnot_net'),
                'inventory_value' => $inventoryValue,
                'units' => (float) InventoryStock::query()->sum('quantity'),
                'unassigned' => Show::query()->inChannelContext()->whereDate('show_date', '>=', today())->whereDoesntHave('streamers')->whereNotIn('status', ['cancelled','closed'])->count(),
                'upcoming' => Show::query()->inChannelContext()->whereDate('show_date', '>=', today())->whereNotIn('status', ['cancelled','closed'])->orderBy('show_date')->limit(5)->get(['id','title','show_date','start_time']),
            ];
        } catch (\Throwable) {
            return ['shows'=>0,'gross'=>0,'net'=>0,'inventory_value'=>0,'units'=>0,'unassigned'=>0,'upcoming'=>collect()];
        }
    }

    private function inventoryHealth(): array
    {
        try {
            $products = InventoryItem::query()->where('is_active', true)->with('stock')->get();
            $in = $products->filter(fn ($p) => (float) $p->stock->sum('quantity') > 0)->count();
            $out = $products->count() - $in;
            $low = $products->filter(function ($p) {
                $qty = (float) $p->stock->sum('quantity');
                return $qty > 0 && $p->reorder_level !== null && $qty <= (float) $p->reorder_level;
            })->count();
            return compact('in', 'low', 'out');
        } catch (\Throwable) { return ['in' => 0, 'low' => 0, 'out' => 0]; }
    }

    private function recentInventoryActivity(): array
    {
        try {
            $movements = InventoryMovement::query()
                ->with('item')
                ->latest()
                ->limit(100)
                ->get();

            return $movements
                ->groupBy(function (InventoryMovement $m): string {
                    // Rows written by the same stock operation belong together
                    // in activity UI. Keep the raw movement rows untouched for
                    // audit/history, but show the user the business quantity.
                    return implode('|', [
                        $m->inventory_item_id,
                        $m->movement_type,
                        $m->from_location_id,
                        $m->to_location_id,
                        trim((string) $m->reason),
                        $m->created_by,
                        $m->created_at?->format('Y-m-d H:i') ?? 'unknown',
                    ]);
                })
                ->map(function ($group) {
                    /** @var InventoryMovement $m */
                    $m = $group->first();
                    return [
                        'name' => $m->item?->name ?: 'Inventory item',
                        'qty' => (float) $group->sum(fn (InventoryMovement $row) => (float) ($row->quantity ?? 0)),
                        'type' => ucfirst(str_replace('_', ' ', (string) $m->movement_type)),
                        'time' => $m->created_at?->diffForHumans(),
                        '_at' => $m->created_at,
                    ];
                })
                ->sortByDesc('_at')
                ->take(5)
                ->map(fn (array $row) => collect($row)->except('_at')->all())
                ->values()
                ->all();
        } catch (\Throwable) { return []; }
    }
}
