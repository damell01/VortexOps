<?php

namespace App\Filament\Resources\WhatnotLedgerResource\Pages;

use App\Filament\Resources\WhatnotLedgerResource;
use Filament\Resources\Pages\ListRecords;

class ListWhatnotLedgerEntries extends ListRecords
{
    protected static string $resource = WhatnotLedgerResource::class;

    public function getSubheading(): ?string
    {
        return 'Line-by-line financial ledger pulled directly from Whatnot, per show — the source data behind each show\'s numbers.';
    }
}
