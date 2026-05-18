<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (DeleteAction $action) {
                    // Prevent self-deletion
                    if ($this->record->id === auth()->id()) {
                        $this->notify('warning', 'You cannot delete your own account.');
                        $action->cancel();
                    }
                }),
        ];
    }
}
