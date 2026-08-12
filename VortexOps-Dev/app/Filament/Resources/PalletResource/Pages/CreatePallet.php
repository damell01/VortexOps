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
        $record = $this->getRecord();

        // When no lines were added manually, land on Import Manifest so the user
        // can upload a packing slip right away rather than viewing an empty pallet.
        if ($record->lines()->doesntExist()) {
            return $this->getResource()::getUrl('import-manifest', ['record' => $record]);
        }

        return $this->getResource()::getUrl('view', ['record' => $record]);
    }
}
