<?php

namespace App\Filament\Resources\FulfillmentResource\Pages;

use App\Filament\Resources\FulfillmentResource;
use App\Filament\Widgets\WhatnotSyncStatusWidget;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentShows extends ListRecords
{
    protected static string $resource = FulfillmentResource::class;

    public function getSubheading(): ?string
    {
        $user = auth()->user();

        return ($user?->isFulfillment() && ! $user->isAdmin())
            ? 'Shows you\'re assigned to fulfill. Open one to update shipping status and tracking.'
            : 'Every show with sold items, across all fulfillment assignments.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function getHeaderWidgets(): array
    {
        return [WhatnotSyncStatusWidget::class];
    }
}
