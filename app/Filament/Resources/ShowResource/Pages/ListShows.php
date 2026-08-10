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
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;

class ListShows extends ListRecords
{
    protected static string $resource = ShowResource::class;

    public function getView(): string
    {
        return 'filament.resources.show-resource.pages.list-shows';
    }

    public function getSubheading(): ?string
    {
        return 'Shows import automatically from Whatnot. Net Margin shows profit per show; use the tabs to focus on what needs review.';
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'past_7_days';
    }

    /** Saved-view chips above the table — quick one-tap filter presets. */
    public function getTabs(): array
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd   = now()->endOfWeek()->toDateString();

        $tabs = [
            // Default view: the working set. Day-to-day reviewing happens on
            // shows that streamed in the last 7 days — yesterday, a few hours
            // ago — not the full multi-year history. Upper bound carries a time
            // component (see OperationsOverviewWidget for the SQLite lexical-
            // comparison trap with bare date strings).
            'past_7_days' => Tab::make('Past 7 Days')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('show_date', [
                    now()->subDays(7)->toDateString(),
                    now()->endOfDay()->toDateTimeString(),
                ])),

            'all' => Tab::make('All'),

            'needs_review' => Tab::make('Needs Review')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending_review', 'pending_approval']))
                ->badge(Cache::remember('tab_badge:shows_needs_review', 30, fn () =>
                    Show::whereIn('status', ['pending_review', 'pending_approval'])->count()
                ))
                ->badgeColor('warning'),

            'this_week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('show_date', [$weekStart, $weekEnd])),

            'unreconciled' => Tab::make('Unreconciled')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['reconciled', 'closed', 'cancelled'])),
        ];

        // Channel-attribution review is admin-facing and only meaningful once a
        // flagged show exists.
        if (auth()->user()?->isAdmin()) {
            // Short TTL — these are tab badges, not the source of truth, and this
            // runs on every page mount (even before deferred table data loads).
            $flagged = Cache::remember('tab_badge:shows_channel_review', 30, fn () =>
                Show::where('channel_attribution_suspect', true)->count()
            );
            if ($flagged > 0) {
                $tabs['flagged'] = Tab::make('Channel Review')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('channel_attribution_suspect', true))
                    ->badge($flagged)
                    ->badgeColor('danger');
            }

            $revised = Cache::remember('tab_badge:shows_financials_revised', 30, fn () =>
                Show::where('financials_revised_after_lock', true)->count()
            );
            if ($revised > 0) {
                $tabs['revised'] = Tab::make('Financials Revised')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('financials_revised_after_lock', true))
                    ->badge($revised)
                    ->badgeColor('danger');
            }
        }

        return $tabs;
    }

    #[\Livewire\Attributes\On('open-show')]
    public function openShowLog(Show $show): void
    {
        $this->dispatch('open-show', $show);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Add Show Manually')
                ->visible(fn () => auth()->user()?->isAdmin() ?? false),

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

            Action::make('detect_streamers_all')
                ->label('Detect Streamers')
                ->icon('heroicon-o-user-circle')
                ->color('gray')
                ->visible(fn () => auth()->user()?->isAdmin())
                ->requiresConfirmation()
                ->modalHeading('Detect Streamers')
                ->modalDescription('Matches every show with no streamer attached against the active streamer roster and attaches any high-confidence match. This also runs automatically on every Whatnot import — use this button to catch any that were missed (e.g. a streamer added to the roster after their show was already imported).')
                ->modalSubmitActionLabel('Run Detection')
                ->action(function () {
                    $shows = Show::whereDoesntHave('streamers')->get();
                    $matched = 0;

                    foreach ($shows as $show) {
                        $suggestions = $show->detectStreamers();
                        if (collect($suggestions)->contains('confidence', 'high')) {
                            $matched++;
                        }
                    }

                    Notification::make()
                        ->title('Streamer detection complete')
                        ->body("{$matched} of {$shows->count()} unmapped show(s) matched.")
                        ->success()
                        ->send();
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
