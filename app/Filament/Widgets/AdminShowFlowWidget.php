<?php

namespace App\Filament\Widgets;

use App\Models\Payout;
use App\Models\Shipment;
use App\Models\Show;
use App\Models\StreamerLogEntry;
use App\Models\StreamerLogItem;
use Filament\Widgets\Widget;

class AdminShowFlowWidget extends Widget
{
    protected static ?int $sort = -60;
    protected int|string|array $columnSpan = 'full';
    protected string $view = 'filament.widgets.admin-show-flow';

    public static function canView(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getFlowProperty(): array
    {
        $endedShows = Show::query()
            ->inChannelContext()
            ->whereDate('show_date', '<=', today())
            ->whereNotIn('status', ['closed', 'cancelled']);

        $needsStreamerLog = (clone $endedShows)
            ->whereDoesntHave('streamerLogEntry', fn ($q) => $q->whereNotNull('submitted_at'))
            ->count();

        $needsAdminReview = StreamerLogEntry::query()
            ->whereIn('status', ['pending', 'streamer_reviewed'])
            ->whereHas('show', fn ($q) => $q->inChannelContext())
            ->count();

        $mappingShows = Show::query()
            ->inChannelContext()
            ->where('status', 'mapping')
            ->count();

        $unmatchedLines = StreamerLogItem::query()
            ->whereNull('inventory_item_id')
            ->whereHas('logEntry.show', fn ($q) => $q->inChannelContext())
            ->count();

        $fulfillmentShows = Show::query()
            ->inChannelContext()
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->where(function ($q) {
                $q->whereHas('shipments', fn ($s) => $s->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"))
                    ->orWhereHas('orders', fn ($o) => $o->whereNotIn('shipping_status', ['shipped', 'delivered']));
            })
            ->count();

        $draftPayrollShows = Payout::query()
            ->inChannelContext()
            ->where('status', 'draft')
            ->whereNotNull('show_id')
            ->distinct('show_id')
            ->count('show_id');

        $paidThisWeek = Payout::query()
            ->inChannelContext()
            ->where('status', 'paid')
            ->where('updated_at', '>=', now()->startOfWeek())
            ->whereNotNull('show_id')
            ->distinct('show_id')
            ->count('show_id');

        return [
            [
                'key' => 'streamer',
                'label' => 'Streamer Log',
                'count' => $needsStreamerLog,
                'detail' => 'ended shows need a report',
                'icon' => 'heroicon-o-pencil-square',
                'url' => \App\Filament\Pages\Shows::getUrl(),
            ],
            [
                'key' => 'review',
                'label' => 'Admin Review',
                'count' => $needsAdminReview,
                'detail' => 'submitted reports to review',
                'icon' => 'heroicon-o-clipboard-document-check',
                'url' => \App\Filament\Pages\Shows::getUrl(),
            ],
            [
                'key' => 'mapping',
                'label' => 'Inventory Mapping',
                'count' => $mappingShows,
                'detail' => $unmatchedLines . ' unmatched item lines',
                'icon' => 'heroicon-o-qr-code',
                'url' => \App\Filament\Pages\Shows::getUrl(),
            ],
            [
                'key' => 'fulfillment',
                'label' => 'Fulfillment',
                'count' => $fulfillmentShows,
                'detail' => 'shows still shipping',
                'icon' => 'heroicon-o-truck',
                'url' => \App\Filament\Resources\FulfillmentResource::getUrl('index'),
            ],
            [
                'key' => 'payroll',
                'label' => 'Payroll',
                'count' => $draftPayrollShows,
                'detail' => 'shows in draft payouts',
                'icon' => 'heroicon-o-banknotes',
                'url' => \App\Filament\Pages\PayrollOverview::getUrl(),
            ],
            [
                'key' => 'done',
                'label' => 'Paid',
                'count' => $paidThisWeek,
                'detail' => 'shows paid this week',
                'icon' => 'heroicon-o-check-circle',
                'url' => \App\Filament\Pages\PayrollOverview::getUrl(),
            ],
        ];
    }

    public function getOpenShipmentsProperty(): int
    {
        return Shipment::query()
            ->whereHas('show', fn ($q) => $q->inChannelContext())
            ->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'")
            ->count();
    }
}
