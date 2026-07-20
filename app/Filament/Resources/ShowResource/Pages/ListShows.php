<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Services\FeatureFlagService;
use App\Services\WhatnotScraper;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListShows extends ListRecords
{
    protected static string $resource = ShowResource::class;

    public function getSubheading(): ?string
    {
        return 'Shows import automatically from Whatnot. Net Margin shows profit per show; use the tabs to focus on what needs review.';
    }

    /** Saved-view chips above the table — quick one-tap filter presets. */
    public function getTabs(): array
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd   = now()->endOfWeek()->toDateString();

        $tabs = [
            'all' => Tab::make('All'),

            'needs_review' => Tab::make('Needs Review')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending_review', 'pending_approval']))
                ->badge(Show::whereIn('status', ['pending_review', 'pending_approval'])->count())
                ->badgeColor('warning'),

            'this_week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('show_date', [$weekStart, $weekEnd])),

            'unreconciled' => Tab::make('Unreconciled')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['reconciled', 'closed', 'cancelled'])),
        ];

        // Channel-attribution review is admin-facing and only meaningful once a
        // flagged show exists.
        if (auth()->user()?->isAdmin()) {
            $flagged = Show::where('channel_attribution_suspect', true)->count();
            if ($flagged > 0) {
                $tabs['flagged'] = Tab::make('Channel Review')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('channel_attribution_suspect', true))
                    ->badge($flagged)
                    ->badgeColor('danger');
            }

            $revised = Show::where('financials_revised_after_lock', true)->count();
            if ($revised > 0) {
                $tabs['revised'] = Tab::make('Financials Revised')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('financials_revised_after_lock', true))
                    ->badge($revised)
                    ->badgeColor('danger');
            }
        }

        return $tabs;
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Show Manually'),

            Action::make('import_whatnot')
                ->label('Import from Whatnot')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('info')
                ->visible(fn () => auth()->user()?->isAdmin()
                    && FeatureFlagService::enabled('whatnot_import')
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
