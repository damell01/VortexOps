<?php

namespace App\Filament\Resources\MissingItemReportResource\Pages;

use App\Filament\Resources\MissingItemReportResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMissingItemReports extends ListRecords
{
    protected static string $resource = MissingItemReportResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
