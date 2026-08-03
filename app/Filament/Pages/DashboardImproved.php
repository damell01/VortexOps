<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FulfillmentNeedsAttentionWidget;
use App\Filament\Widgets\NeedsAttentionWidget;
use App\Filament\Widgets\OperationsOverviewWidget;
use App\Filament\Widgets\RecentShowsWidget;
use App\Filament\Widgets\ShowsKpiWidget;
use App\Filament\Widgets\StreamerOverviewWidget;
use App\Filament\Widgets\StreamerShowsToReviewWidget;
use App\Models\DeductionRequest;
use App\Models\Payout;
use App\Models\Shipment;
use App\Models\StreamerLogEntry;
use App\Models\Show;
use App\Models\InventoryItem;
use App\Models\Setting;
use Filament\Pages\Dashboard;

class DashboardImproved extends Dashboard
{
    public function getView(): string
    {
        return 'filament.pages.dashboard-improved';
    }

    public function getWidgets(): array
    {
        if ((bool) Setting::get('demo_mode', false)) {
            return [];
        }

        return [
            StreamerOverviewWidget::class,
            StreamerShowsToReviewWidget::class,
            FulfillmentNeedsAttentionWidget::class,
            NeedsAttentionWidget::class,
            OperationsOverviewWidget::class,
            RecentShowsWidget::class,
            ShowsKpiWidget::class,
        ];
    }

    public function getViewData(): array
    {
        $data = [];
        $user = auth()->user();

        if ($user?->isStreamer() && ! $user->isAdmin()) {
            // Streamer-specific metrics
            $data['pendingShows'] = StreamerLogEntry::where('streamer_id', $user->streamer?->id ?? 0)
                ->where('status', 'pending')
                ->count();

            $data['pendingPayouts'] = Payout::where('streamer_id', $user->streamer?->id ?? 0)
                ->where('status', 'approved')
                ->count();

            $data['inventoryCount'] = InventoryItem::whereHas('stock', fn ($q) =>
                $q->whereIn('inventory_location_id', $user->streamer?->inventoryLocations()->pluck('id') ?? [])
            )->where('is_active', true)->count();
        } elseif ($user?->isFulfillment() && ! $user->isAdmin()) {
            // Fulfillment-specific metrics
            $data['showsToFulfill'] = Show::whereHas('fulfillmentUsers', fn ($q) =>
                $q->where('users.id', $user->id)
            )->whereHas('orders', fn ($q) =>
                $q->where('shipping_status', 'ready_to_ship')
            )->count();

            $data['readyToShip'] = Shipment::where('status', 'Ready to Ship')->count();
            $data['inTransit'] = Shipment::where('status', 'Shipped')->count();
        } elseif ($user?->isAdmin()) {
            // Admin-specific metrics
            $data['pendingReview'] = Show::where('status', 'pending_review')
                ->inChannelContext()
                ->count();

            $data['draftPayouts'] = Payout::where('status', 'draft')
                ->inChannelContext()
                ->count();

            $data['lowStock'] = InventoryItem::whereHas('stock', fn ($q) =>
                $q->whereRaw('quantity <= COALESCE(reorder_level, 0)')
            )->where('is_active', true)->count();
        }

        return $data;
    }
}
