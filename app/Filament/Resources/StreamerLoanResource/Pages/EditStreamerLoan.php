<?php

namespace App\Filament\Resources\StreamerLoanResource\Pages;

use App\Filament\Resources\StreamerLoanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStreamerLoan extends EditRecord
{
    protected static string $resource = StreamerLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
