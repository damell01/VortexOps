<?php

namespace App\Filament\Resources\ShowIngestionLogResource\Pages;

use App\Filament\Resources\ShowIngestionLogResource;
use Filament\Resources\Pages\ListRecords;

class ListShowIngestionLogs extends ListRecords
{
    protected static string $resource = ShowIngestionLogResource::class;

    public function getSubheading(): ?string
    {
        return 'Raw import log from the Whatnot scraper — check here first when a show didn\'t import right.';
    }
}
