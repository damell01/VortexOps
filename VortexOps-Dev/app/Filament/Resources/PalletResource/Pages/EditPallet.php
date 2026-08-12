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
        $newAttachments = $this->data['new_attachments'] ?? [];

        if (empty($newAttachments)) {
            return;
        }

        $acceptedTypes = [
            'image/jpeg'      => PalletAttachment::TYPE_PHOTO,
            'image/png'       => PalletAttachment::TYPE_PHOTO,
            'image/webp'      => PalletAttachment::TYPE_PHOTO,
            'image/gif'       => PalletAttachment::TYPE_PHOTO,
            'application/pdf' => PalletAttachment::TYPE_DOCUMENT,
        ];

        foreach ($newAttachments as $filePath) {
            if (! $filePath) {
                continue;
            }

            $file = storage_path('app/public/' . $filePath);
            if (! file_exists($file)) {
                continue;
            }

            $mimeType = mime_content_type($file);
            $type     = $acceptedTypes[$mimeType] ?? PalletAttachment::TYPE_OTHER;

            PalletAttachment::create([
                'pallet_id'   => $this->record->id,
                'type'        => $type,
                'file_path'   => $filePath,
                'file_name'   => basename($filePath),
                'file_size'   => filesize($file),
                'mime_type'   => $mimeType,
                'uploaded_by' => auth()->id(),
                'uploaded_at' => now(),
            ]);
        }

        // Update the denormalized count
        $this->record->update([
            'attachments_count' => $this->record->attachments()->count(),
        ]);
    }
}
