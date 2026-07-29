<?php

namespace App\Filament\Resources\ShippingSurchargeResource\Pages;

use App\Filament\Resources\ShippingSurchargeResource;
use Filament\Resources\Pages\ListRecords;

class ListShippingSurcharges extends ListRecords
{
    protected static string $resource = ShippingSurchargeResource::class;

    public function getSubheading(): ?string
    {
        return 'Extra shipping costs charged against a streamer\'s payout for a show — deducted automatically at pay run time.';
    }
}
