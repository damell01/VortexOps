<?php

namespace App\Filament\Resources\ProductIdentityResource\Pages;

use App\Filament\Resources\ProductIdentityResource;
use Filament\Resources\Pages\ListRecords;

class ListProductIdentities extends ListRecords
{
    protected static string $resource = ProductIdentityResource::class;

    public function getSubheading(): ?string
    {
        return 'Extra barcodes/UPCs mapped to a product, for items that come in more than one vendor package.';
    }
}
