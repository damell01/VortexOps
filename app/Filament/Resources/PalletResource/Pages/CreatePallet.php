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
        // Create the pallet record first, then let the user decide whether to
        // launch the background AI manifest job or return to manual lines.
        // The AI page returns immediately after dispatch and notifies the user
        // when review is ready.
        return $this->getResource()::getUrl('import-manifest', ['record' => $this->getRecord()]);
    }
}
