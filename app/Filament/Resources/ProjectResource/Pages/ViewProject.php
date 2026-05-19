<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ProjectResource;
use App\Modules\ProjectHub\Support\ProjectHubRoadmap;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('applyRoadmapTemplate')
                ->label('Load Roadmap Template')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('gray')
                ->action(function (): void {
                    ProjectHubRoadmap::apply($this->record);

                    Notification::make()
                        ->title('Roadmap template loaded')
                        ->body('Milestones, approvals, and progress updates were synced into this project hub.')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }
}
