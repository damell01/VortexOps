<?php

namespace App\Filament\Resources\FulfillmentPackages\Pages;

use App\Filament\Resources\FulfillmentPackages\FulfillmentPackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentPackages extends ListRecords
{
    protected static string $resource = FulfillmentPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
