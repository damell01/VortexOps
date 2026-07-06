<?php

namespace App\Filament\Resources\WeeklyPayoutBatchResource\Pages;

use App\Filament\Resources\WeeklyPayoutBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWeeklyPayoutBatch extends EditRecord
{
    protected static string $resource = WeeklyPayoutBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn () => $this->record->status === 'draft'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return WeeklyPayoutBatchResource::getUrl('view', ['record' => $this->record]);
    }
}
