<?php

namespace App\Filament\Resources\LedgerResource\Pages;

use App\Filament\Resources\LedgerResource;
use App\Models\LedgerEntry;
use Filament\Resources\Pages\ListRecords;

class ListLedgerEntries extends ListRecords
{
    protected static string $resource = LedgerResource::class;

    public function getSubheading(): ?string
    {
        return 'A running ledger of money in and out, by channel — credits and debits with a running net total.';
    }

    public function getFooterWidgetsColumns(): int | array
    {
        return 3;
    }

    protected function getFooterWidgets(): array
    {
        return [];
    }

    public function getLedgerSummaryProperty(): array
    {
        $entries = LedgerEntry::all();
        $credits = $entries->where('direction', 'credit')->sum('amount');
        $debits  = $entries->where('direction', 'debit')->sum('amount');

        return [
            'credits' => (float) $credits,
            'debits'  => (float) $debits,
            'net'     => (float) $credits - (float) $debits,
        ];
    }
}
