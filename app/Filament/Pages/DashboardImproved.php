<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FulfillmentInventoryWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\OperationsOverviewWidget;
use App\Filament\Widgets\RecentShowsWidget;
use App\Filament\Widgets\ShowWorkflowControlWidget;
use App\Filament\Widgets\ShowsKpiWidget;
use App\Filament\Widgets\StreamerInventoryWidget;
use App\Filament\Widgets\StreamerOverviewWidget;
use App\Filament\Widgets\StreamerProfitShareWidget;
use App\Filament\Widgets\StreamerShowsToReviewWidget;
use App\Models\InventoryItem;
use App\Models\Payout;
use App\Models\Setting;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use App\Support\ChannelContext;
use Filament\Pages\Dashboard;

class DashboardImproved extends Dashboard
{
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
                ShowWorkflowControlWidget::class,
                NeedsAttentionWidget::class,
                OperationsOverviewWidget::class,
                RecentShowsWidget::class,
                ShowsKpiWidget::class,
            ];
        }

        return [];
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $data = ['roleMode' => 'user'];

        if ($user?->isStreamer() && ! $user->isAdmin() && ! $user->isOwner()) {
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
        } elseif ($user?->isAdmin() || $user?->isOwner()) {
            $data += [
                'roleMode' => 'admin',
                'reportsToReview' => StreamerLogEntry::query()
                    ->where('status', 'streamer_reviewed')
                    ->whereHas('show', fn ($q) => $q->inChannelContext())
                    ->count(),
                'unmatchedItems' => StreamerLogItem::query()
                    ->whereNull('inventory_item_id')
                    ->whereHas('logEntry.show', fn ($q) => $q->inChannelContext())
                    ->count(),
                'openShipments' => Shipment::query()
                    ->whereHas('show', fn ($q) => $q->inChannelContext())
                    ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")
                    ->count(),
                'unassignedFulfillment' => Show::query()
                    ->inChannelContext()
                    ->whereHas('shipments')
                    ->whereDoesntHave('fulfillmentUsers')
                    ->whereNotIn('status', ['closed', 'cancelled'])
                    ->count(),
                'draftPayouts' => Payout::where('status', 'draft')->inChannelContext()->count(),
            ];
        }

        return $data;
    }
}
