<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\UserTeamProfileService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var array<int,string> privileged roles the target held before this edit */
    private array $privilegedBefore = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['streamer_type'] = $this->record->streamer?->streamer_type ?? 'in_house';
        $data['streamer_aliases'] = $this->record->streamer?->aliases()->orderBy('alias')->pluck('alias')->all() ?? [];
        return $data;
    }

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

        // Admin and Fulfillment Admin are managed from this single Users flow.
        app(UserTeamProfileService::class)->sync(
            $this->record,
            $this->data['streamer_type'] ?? $this->record->streamer?->streamer_type ?? 'in_house',
            $this->data['streamer_aliases'] ?? null,
        );
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
