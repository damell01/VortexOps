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
        // super_admin is frozen from this UI entirely — nobody, owner included,
        // may grant OR revoke it here. Granting is blocked outright; restoring
        // on removal matters because super_admin is filtered out of the select
        // options, so an unrelated edit to an existing super admin would
        // otherwise silently drop the role from the submitted state.
        foreach (UserResource::UNGRANTABLE_ROLES as $role) {
            $had = in_array($role, $this->privilegedBefore, true);
            $has = $this->record->hasRole($role);
            if ($has && ! $had) {
                $this->record->removeRole($role);
            } elseif ($had && ! $has) {
                $this->record->assignRole($role);
            }
        }

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
                ->visible(fn () => UserResource::canDelete($this->record))
                ->before(function (DeleteAction $action) {
                    // Defense in depth: canDelete() already hides the button for
                    // these cases, but guard the actual delete call too in case
                    // it's ever reached via a crafted request.
                    if ($this->record->id === auth()->id()) {
                        \Filament\Notifications\Notification::make()
                            ->title('You cannot delete your own account.')
                            ->warning()
                            ->send();
                        $action->cancel();
                    } elseif (! UserResource::canDelete($this->record)) {
                        \Filament\Notifications\Notification::make()
                            ->title('You do not have permission to delete this account.')
                            ->danger()
                            ->send();
                        $action->cancel();
                    }
                }),
        ];
    }
}
