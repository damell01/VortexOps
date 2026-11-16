<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePallet extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = PalletResource::class;

    /** A new pallet is a few short steps; the manifest comes right after saving. */
    public function getSteps(): array
    {
        return \App\Filament\Support\FormFlow::steps(PalletResource::formParts());
    }

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
