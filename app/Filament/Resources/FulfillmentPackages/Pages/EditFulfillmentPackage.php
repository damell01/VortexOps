<?php

namespace App\Filament\Resources\FulfillmentPackages\Pages;

use App\Filament\Resources\FulfillmentPackages\FulfillmentPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFulfillmentPackage extends EditRecord
{
    protected static string $resource = FulfillmentPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
