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
        // Straight into the manifest table. A pallet exists to hold lines, so
        // the next thing anyone does after creating one is type them — and the
        // create form no longer carries a line editor of its own, because two
        // editors for one thing is how they drift apart.
        //
        // It used to divert to the packing-slip reader, which is the one screen
        // you cannot stage from.
        return $this->getResource()::getUrl('add-lines', ['record' => $this->getRecord()]);
    }
}
