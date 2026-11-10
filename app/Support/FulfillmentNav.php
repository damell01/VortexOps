<?php

namespace App\Support;

/** The Fulfillment menu: the three main pages and the TV whiteboard, then the supporting tools. */
final class FulfillmentNav
{
    /** @return list<array{0:string,1:string,2?:string,3?:string}> */
    public static function links(): array
    {
        $user = auth()->user();

        return array_values(array_filter([
            ['Fulfillment Home', \App\Filament\Pages\FulfillmentHome::getUrl(panel: 'admin'), 'Fulfillment', 'heroicon-o-home'],
            ['Station Board', \App\Filament\Pages\FulfillmentStationBoard::getUrl(panel: 'admin'), 'Fulfillment', 'heroicon-o-qr-code'],
            ['Schedule & Activity', \App\Filament\Pages\FulfillmentActivity::getUrl(panel: 'admin'), 'Fulfillment', 'heroicon-o-calendar-days'],
            ['Reports', \App\Filament\Pages\FulfillmentReports::getUrl(panel: 'admin'), 'Fulfillment', 'heroicon-o-chart-bar'],
            ['TV Whiteboard', route('fulfillment.whiteboard'), 'Fulfillment', 'heroicon-o-tv'],
            (! $user?->isFulfillmentAdmin() && \App\Filament\Resources\ShowResource\Pages\ReviewShowStreamers::canAccess()) ? ['Stream Review', \App\Filament\Resources\ShowResource::getUrl('stream-review'), 'Management', 'heroicon-o-user-group'] : null,
            \App\Filament\Pages\ShipmentOverview::canAccess() ? ['Shipment Tracking', \App\Filament\Pages\ShipmentOverview::getUrl(panel: 'admin'), 'Management', 'heroicon-o-truck'] : null,
        ]));
    }
}
