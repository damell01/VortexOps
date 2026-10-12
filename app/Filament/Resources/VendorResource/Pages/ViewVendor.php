<?php

namespace App\Filament\Resources\VendorResource\Pages;

use App\Filament\Resources\VendorResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendor extends ViewRecord
{
    protected static string $resource = VendorResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make(),
            \Filament\Actions\Action::make('receiving_history')->label('Receiving history')->icon('heroicon-o-clock')
                ->url(fn () => \App\Filament\Pages\PalletReceivingHistory::getUrl(parameters: ['vendorId'=>$this->record->id]))
                ->visible(fn () => \App\Filament\Pages\PalletReceivingHistory::canAccess()),
        ];
    }
}

