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
        // Manual entry is the default path. From Manifest Lines the user can
        // switch to AI import or skip the manifest entirely and come back later.
        return $this->getResource()::getUrl('add-lines', ['record' => $this->getRecord()]);
    }
}
