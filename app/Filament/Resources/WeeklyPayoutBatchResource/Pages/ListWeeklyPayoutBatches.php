<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWeeklyPayoutBatches extends ListRecords
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Pay Run'),
        ];
    }
}
