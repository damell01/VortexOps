<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Services\WhatnotScraper;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListShows extends ListRecords
{
    protected static string $resource = ShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Show Manually'),

            Action::make('import_whatnot')
                ->label('Import from Whatnot')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn () => auth()->user()?->isAdmin()
                    && ! empty(config('vortex.whatnot.email'))
                    && ! empty(config('vortex.whatnot.password')))
                ->requiresConfirmation()
                ->modalHeading('Import Shows from Whatnot')
                ->modalDescription('This will run the Whatnot scraper and pull in your most recent shows. Existing shows are matched by Whatnot show ID and updated, not duplicated.')
                ->modalSubmitActionLabel('Run Import')
                ->action(function () {
                    try {
                        $result = app(WhatnotScraper::class)->importAllEnabledChannels(
                            limit: (int) config('vortex.whatnot.limit', 50),
                        );

                        $channelNote = $result['channels'] > 1 ? " across {$result['channels']} channels" : '';

                        Notification::make()
                            ->title('Whatnot import complete')
                            ->body("{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped{$channelNote}.")
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()
                            ->title('Whatnot import failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('export_excel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->url(fn () => route('export.shows'))
                ->openUrlInNewTab(),
        ];
    }
}
