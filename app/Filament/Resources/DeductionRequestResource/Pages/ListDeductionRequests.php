<?php

namespace App\Filament\Resources\DeductionRequestResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListDeductionRequests extends ListRecords
{
    use \App\Filament\Concerns\DownloadsTableCsv;

    protected static string $resource = DeductionRequestResource::class;

    public function getSubheading(): ?string
    {
        return 'Items streamers logged as sold during a show, waiting on ops to confirm cost and approve the stock deduction.';
    }
}
