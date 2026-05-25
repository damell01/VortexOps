<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\MapShowInventory;
use App\Jobs\ParseShowTitle;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('run_ai_title_parse')
                ->label('Re-parse Title')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->visible(fn () => $this->record->title !== null)
                ->action(function () {
                    ParseShowTitle::dispatch($this->record->id);
                    Notification::make()->title('Title parsing queued')->success()->send();
                }),

            Action::make('run_ai_mapping')
                ->label('Run AI Mapping')
                ->icon('heroicon-o-sparkles')
                ->color('warning')
                ->visible(fn () => $this->record->status === 'pending_review' && $this->record->streamers()->exists())
                ->requiresConfirmation()
                ->action(function () {
                    MapShowInventory::dispatch($this->record->id);
                    Notification::make()->title('AI Mapping queued')->success()->send();
                }),

            EditAction::make(),
        ];
    }
}
