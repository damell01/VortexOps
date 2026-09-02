<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Widgets\FulfillmentCenterOverviewWidget;
use App\Filament\Widgets\WhatnotSyncStatusWidget;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentShows extends ListRecords
{
    protected static string $resource = FulfillmentResource::class;

    public function getTitle(): string
    {
        return 'Fulfillment Center';
    }

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return ($user?->isFulfillment() && ! $user->isAdmin())
            ? 'Your show-first work queue: pack orders, track open shipments, verify counts, and hand completed shows back into payroll.'
            : 'Manage assignment, packing, shipment progress, fulfillment verification, and show handoff from one workspace.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            FulfillmentCenterOverviewWidget::class,
            WhatnotSyncStatusWidget::class,
        ];
    }
}
