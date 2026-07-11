<?php

namespace App\Filament\Resources\RoleResource\Pages;

use App\Filament\Resources\RoleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        // Protect the core roles from deletion.
        return [
            DeleteAction::make()
                ->visible(fn () => ! in_array($this->record->name, ['admin', 'super_admin', 'streamer'])),
        ];
    }
}
