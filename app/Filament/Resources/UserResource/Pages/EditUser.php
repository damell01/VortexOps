<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int,string> privileged roles the target held before this edit */
    private array $privilegedBefore = [];

    protected function beforeSave(): void
    {
        $this->privilegedBefore = $this->record->getRoleNames()
            ->intersect(UserResource::PRIVILEGED_ROLES)
            ->values()
            ->all();
    }

    protected function afterSave(): void
    {
        // Only the owner may add or remove privileged roles. For anyone else,
        // restore the exact privileged-role set the target had before this edit —
        // so an admin can neither escalate an account nor demote a super admin,
        // even via a crafted request.
        if (auth()->user()?->isOwner()) {
            return;
        }

        $current = $this->record->getRoleNames()
            ->intersect(UserResource::PRIVILEGED_ROLES)
            ->values()
            ->all();

        foreach (array_diff($current, $this->privilegedBefore) as $illegitimatelyAdded) {
            $this->record->removeRole($illegitimatelyAdded);
        }
        foreach (array_diff($this->privilegedBefore, $current) as $illegitimatelyRemoved) {
            $this->record->assignRole($illegitimatelyRemoved);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn () => $this->record->isSuperAdmin())
                ->before(function (DeleteAction $action) {
                    if ($this->record->id === auth()->id()) {
                        \Filament\Notifications\Notification::make()
                            ->title('You cannot delete your own account.')
                            ->warning()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
