<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Resources\VendorResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendors extends ListRecords
{
    protected static string $resource = VendorResource::class;

    public function getSubheading(): ?string
    {
        return 'Suppliers you buy from. A vendor\'s lead time drives the reorder-quantity suggestions on Product Insights.';
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
