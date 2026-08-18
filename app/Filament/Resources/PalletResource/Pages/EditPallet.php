<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\PalletAttachment;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPallet extends EditRecord
{
    protected static string $resource = PalletResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function afterSave(): void
    {
        // Persisting lives in PalletAttachmentService so the pallet's own page
        // can take uploads without duplicating this.
        app(\App\Services\PalletAttachmentService::class)
            ->attach($this->record, $this->data['new_attachments'] ?? []);
    }
}
