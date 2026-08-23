<?php

namespace App\Filament\Widgets;

use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use Filament\Widgets\Widget;

/**
 * What is waiting, in four numbers.
 *
 * This was the Post-show automation panel: two sets of radio buttons choosing
 * when inventory posts and which reports need review, with these counts along
 * the top. The radios were settings — chosen once and then left for months —
 * sitting at the top of a screen read many times a day, mostly by people
 * without the permission to change them. They are in Settings now.
 *
 * The counts stayed, because nothing else on the dashboard reports them and
 * they are the ones that mean someone has work to do.
 */
class ShowQueueCountsWidget extends Widget
{
    protected static ?int $sort = -50;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.show-queue-counts';

    public static function canView(): bool
    {
        $user = auth()->user();

        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getCountsProperty(): array
    {
        $channelScope = fn ($q) => $q->whereHas('show', fn ($show) => $show->inChannelContext());

        $pendingReports = StreamerLogEntry::query()
            ->whereIn('status', ['pending', 'streamer_reviewed'])
            ->where($channelScope)
            ->count();

        $unmatchedLines = StreamerLogItem::query()
            ->whereNull('inventory_item_id')
            ->whereHas('logEntry.show', fn ($show) => $show->inChannelContext())
            ->count();

        $unassignedFulfillment = Show::query()
            ->inChannelContext()
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->whereHas('shipments')
            ->whereDoesntHave('fulfillmentUsers')
            ->count();

        $openShipments = \App\Models\Shipment::query()
            ->whereHas('show', fn ($show) => $show->inChannelContext())
            ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")
            ->count();

        return [
            'pending_reports' => $pendingReports,
            'unmatched_lines' => $unmatchedLines,
            'unassigned_fulfillment' => $unassignedFulfillment,
            'open_shipments' => $openShipments,
        ];
    }
}
