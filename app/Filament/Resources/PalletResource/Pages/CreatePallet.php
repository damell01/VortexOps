<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePallet extends CreateRecord
{
    protected static string $resource = PalletResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        // Always the pallet itself. An empty pallet used to divert to the
        // packing-slip reader, which is the one screen you cannot stage from —
        // the list is built here, by name, with Add Expected Item.
        return $this->getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
