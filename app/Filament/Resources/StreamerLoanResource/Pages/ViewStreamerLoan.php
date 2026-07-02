<?php

namespace App\Filament\Resources\StreamerLoanResource\Pages;

use App\Filament\Resources\StreamerLoanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewStreamerLoan extends ViewRecord
{
    protected static string $resource = StreamerLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
