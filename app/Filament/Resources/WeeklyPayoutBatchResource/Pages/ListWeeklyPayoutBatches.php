<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyPayoutBatches extends ListRecords
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    public function getSubheading(): ?string
    {
        return 'Payroll history by week — review totals and status, then open a pay run for payout-level detail.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Pay Run'),
        ];
    }
}
