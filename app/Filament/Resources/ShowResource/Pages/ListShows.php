<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\RunShowAiMappingJob;
use App\Models\AiTask;
use App\Models\Setting;
use App\Models\Show;
use App\Services\FeatureFlagService;
use App\Services\WhatnotScraper;
use App\Support\AdminModules;
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
                ->modifyQueryUsing(fn (Builder $q) => $q->whereIn('status', ['pending_review', 'pending_approval']))
                ->badge(Show::whereIn('status', ['pending_review', 'pending_approval'])->count())
                ->badgeColor('warning'),

            'this_week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereBetween('show_date', [$weekStart, $weekEnd])),

            'unreconciled' => Tab::make('Unreconciled')
                ->modifyQueryUsing(fn (Builder $q) => $q->whereNotIn('status', ['reconciled', 'closed', 'cancelled'])),
        ];

        // Channel-attribution review is admin-facing and only meaningful once a
        // flagged show exists.
        if (auth()->user()?->isAdmin()) {
            $flagged = Show::where('channel_attribution_suspect', true)->count();
            if ($flagged > 0) {
                $tabs['flagged'] = Tab::make('Channel Review')
                    ->modifyQueryUsing(fn (Builder $q) => $q->where('channel_attribution_suspect', true))
                    ->badge($flagged)
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
                        $importedAt = now();

                        $result = app(WhatnotScraper::class)->importAllEnabledChannels(
                            limit: (int) config('vortex.whatnot.limit', 50),
                        );

                        $channelNote = $result['channels'] > 1 ? " across {$result['channels']} channels" : '';

                        Notification::make()
                            ->title('Whatnot import complete')
                            ->body("{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped{$channelNote}.")
                            ->success()
                            ->send();

                        if ($result['created'] > 0
                            && Setting::get('ai_auto_queue_on_import', false)
                            && AdminModules::isEnabled('ai')
                        ) {
                            $newShows = Show::where('import_source', 'auto_whatnot')
                                ->where('status', 'draft')
                                ->where('created_at', '>=', $importedAt)
                                ->get();

                            $queued = 0;
                            foreach ($newShows as $show) {
                                $show->update(['status' => 'pending_review']);
                                $task = AiTask::create([
                                    'type'          => 'show_ai_mapping',
                                    'status'        => 'pending',
                                    'taskable_type' => Show::class,
                                    'taskable_id'   => $show->id,
                                    'triggered_by'  => auth()->id(),
                                    'input'         => ['show_id' => $show->id, 'show_title' => $show->title],
                                ]);
                                RunShowAiMappingJob::dispatch($show->id, $task->id)->onQueue('ai');
                                $queued++;
                            }

                            if ($queued > 0) {
                                Notification::make()
                                    ->title("Auto-queued {$queued} show" . ($queued === 1 ? '' : 's') . ' for AI mapping')
                                    ->body('You will receive a notification for each show when mapping completes.')
                                    ->info()
                                    ->send();
                            }
                        }
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

            Action::make('bulk_ai_mapping')
                ->label('Map All Pending Review')
                ->icon('heroicon-o-sparkles')
                ->color('violet')
                ->visible(fn () => auth()->user()?->isAdmin() && AdminModules::isEnabled('ai'))
                ->requiresConfirmation()
                ->modalHeading('Queue AI Mapping for All Pending Review Shows')
                ->modalDescription(function (): string {
                    $count = Show::where('status', 'pending_review')->count();
                    return $count === 0
                        ? 'There are no shows currently in Pending Review.'
                        : "This will queue AI mapping jobs for {$count} show" . ($count === 1 ? '' : 's') . " currently in Pending Review. You will receive a notification for each when complete.";
                })
                ->action(function (): void {
                    $shows = Show::where('status', 'pending_review')->get();

                    if ($shows->isEmpty()) {
                        Notification::make()
                            ->title('No shows to map')
                            ->body('No shows are currently in Pending Review.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $queued = 0;
                    foreach ($shows as $show) {
                        $task = AiTask::create([
                            'type'          => 'show_ai_mapping',
                            'status'        => 'pending',
                            'taskable_type' => \App\Models\Show::class,
                            'taskable_id'   => $show->id,
                            'triggered_by'  => auth()->id(),
                            'input'         => ['show_id' => $show->id, 'show_title' => $show->title],
                        ]);
                        RunShowAiMappingJob::dispatch($show->id, $task->id)->onQueue('ai');
                        $queued++;
                    }

                    Notification::make()
                        ->title("Queued {$queued} show" . ($queued === 1 ? '' : 's') . ' for AI mapping')
                        ->body('You will receive a notification for each show when mapping completes.')
                        ->info()
                        ->send();
                }),
        ];
    }
}
