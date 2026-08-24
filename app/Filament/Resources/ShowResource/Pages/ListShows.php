<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Services\FeatureFlagService;
use App\Services\WhatnotScraper;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MultiSelect;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ListShows extends ListRecords
{
    protected static string $resource = ShowResource::class;

    protected ?array $statsMemo = null;
    protected ?array $operationsMemo = null;

    public function getView(): string
    {
        return 'filament.resources.show-resource.pages.list-shows';
    }

    public function getSubheading(): ?string
    {
        return auth()->user()?->isStreamer()
            ? 'Your Whatnot schedule, recent shows, and show reports in one place.'
            : 'Shows Operations Center — schedule, streamer reports, fulfillment, shipments, and show health.';
    }

    /**
     * Operations-center data shared by the role-aware Blade layout.
     * ShowResource::getEloquentQuery() already scopes streamers to their own
     * shows and respects the active Whatnot channel, so this stays consistent
     * with the table below.
     */
    public function getOperations(): array
    {
        if ($this->operationsMemo !== null) return $this->operationsMemo;

        $base = fn () => ShowResource::getEloquentQuery()
            ->with([
                'streamerLogEntry.items',
                'fulfillmentUsers',
            ])
            ->withCount('shipments')
            ->withCount([
                'shipments as open_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) <> 'delivered'"),
                'shipments as delivered_shipments_count' => fn ($q) => $q->whereRaw("LOWER(COALESCE(status, '')) = 'delivered'"),
            ])
            ->whereNotIn('status', ['cancelled']);

        $nowTime = now()->format('H:i:s');

        $upcoming = $base()
            ->where(function ($q) use ($nowTime) {
                $q->whereDate('show_date', '>', today())
                    ->orWhere(function ($today) use ($nowTime) {
                        $today->whereDate('show_date', today())
                            ->where(function ($time) use ($nowTime) {
                                $time->whereNull('start_time')->orWhereTime('start_time', '>', $nowTime);
                            });
                    });
            })
            ->orderBy('show_date')
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        $recent = $base()
            ->where(function ($q) use ($nowTime) {
                $q->whereDate('show_date', '<', today())
                    ->orWhere(function ($today) use ($nowTime) {
                        $today->whereDate('show_date', today())
                            ->where(function ($time) use ($nowTime) {
                                $time->whereNull('start_time')->orWhereTime('start_time', '<=', $nowTime);
                            });
                    });
            })
            ->orderByDesc('show_date')
            ->orderByDesc('start_time')
            ->limit(16)
            ->get();

        $needsAttention = collect();
        if (! auth()->user()?->isStreamer()) {
            $needsAttention = $recent->filter(function (Show $show) {
                $log = $show->streamerLogEntry;
                $unmatched = $log ? $log->items->whereNull('inventory_item_id')->count() : 0;

                return $show->channel_attribution_suspect
                    || $show->financials_revised_after_lock
                    || in_array($show->status, ['pending_review', 'pending_approval'], true)
                    || ($log && in_array($log->status, ['streamer_reviewed', 'changes_requested'], true))
                    || $unmatched > 0
                    || ((int) $show->shipments_count > 0 && $show->fulfillmentUsers->isEmpty())
                    || (int) $show->open_shipments_count > 0;
            })->take(10)->values();
        }

        return $this->operationsMemo = [
            'upcoming' => $upcoming,
            'recent' => $recent,
            'needsAttention' => $needsAttention,
            'isStreamer' => auth()->user()?->isStreamer() && ! auth()->user()?->isAdmin(),
        ];
    }

    public function getStats(): array
    {
        if ($this->statsMemo !== null) return $this->statsMemo;

        $base = fn () => ShowResource::getEloquentQuery()->whereNotIn('status', ['cancelled']);

        $active    = $base()->whereIn('status', ['draft', 'mapping'])->count();
        $completed = $base()->whereIn('status', ['reconciled', 'closed'])->count();
        $pending   = $base()->whereIn('status', ['pending_review', 'pending_approval'])->count();
        $revenue   = (float) $base()->sum('gross_revenue');
        $counted   = $base()->count();
        $money = fn (float $v) => '$' . number_format($v, 2);

        return $this->statsMemo = [
            ['label' => 'Active Shows', 'value' => number_format($active), 'sub' => 'Draft or mapping', 'icon' => 'heroicon-o-signal', 'tone' => 'purple'],
            ['label' => 'Completed Shows', 'value' => number_format($completed), 'sub' => 'Reconciled or closed', 'icon' => 'heroicon-o-check-circle', 'tone' => 'green'],
            ['label' => 'Pending Submission', 'value' => number_format($pending), 'sub' => 'Awaiting review', 'icon' => 'heroicon-o-clock', 'tone' => 'amber'],
            ['label' => 'Total Revenue', 'value' => $money($revenue), 'sub' => 'All shows', 'icon' => 'heroicon-o-banknotes', 'tone' => 'blue'],
            ['label' => 'Avg Revenue', 'value' => $money($counted > 0 ? $revenue / $counted : 0), 'sub' => 'Per show', 'icon' => 'heroicon-o-chart-bar', 'tone' => 'orange'],
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'past_7_days';
    }

    public function getTabs(): array
    {
        $weekStart = now()->startOfWeek()->toDateString();
        $weekEnd   = now()->endOfWeek()->toDateString();

        $tabs = [
            'past_7_days' => Tab::make('Past 7 Days')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('show_date', [now()->subDays(7)->toDateString(), now()->endOfDay()->toDateTimeString()])),
            'all' => Tab::make('All'),
            'needs_review' => Tab::make('Needs Review')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['pending_review', 'pending_approval']))
                ->badge(Cache::remember('tab_badge:shows_needs_review', 30, fn () => Show::whereIn('status', ['pending_review', 'pending_approval'])->count()))
                ->badgeColor('warning'),
            'this_week' => Tab::make('This Week')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereBetween('show_date', [$weekStart, $weekEnd])),
            'unreconciled' => Tab::make('Unreconciled')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', ['reconciled', 'closed', 'cancelled'])),
        ];

        if (auth()->user()?->isAdmin()) {
            $flagged = Cache::remember('tab_badge:shows_channel_review', 30, fn () => Show::where('channel_attribution_suspect', true)->count());
            if ($flagged > 0) {
                $tabs['flagged'] = Tab::make('Channel Review')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('channel_attribution_suspect', true))
                    ->badge($flagged)->badgeColor('danger');
            }

            $revised = Cache::remember('tab_badge:shows_financials_revised', 30, fn () => Show::where('financials_revised_after_lock', true)->count());
            if ($revised > 0) {
                $tabs['revised'] = Tab::make('Financials Revised')
                    ->modifyQueryUsing(fn (Builder $query) => $query->where('financials_revised_after_lock', true))
                    ->badge($revised)->badgeColor('danger');
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
            CreateAction::make()->label('Add Show Manually')->visible(fn () => auth()->user()?->isAdmin() ?? false),

            Action::make('schedule_show')
                ->label('📅 Schedule Future Show')
                ->icon('heroicon-o-calendar-days')
                ->color('success')
                ->visible(fn () => auth()->user()?->isAdmin() ?? false)
                ->form([
                    Grid::make(2)->schema([
                        DatePicker::make('show_date')->label('Show Date')->required()->minDate(now()->startOfDay())->columnSpan(1),
                        TimePicker::make('start_time')->label('Start Time (Optional)')->columnSpan(1),
                        TextInput::make('title')->label('Show Title')->placeholder('e.g., Upcoming Break #50')->maxLength(255)->columnSpan(2),
                        Select::make('whatnot_channel_id')->label('Channel (Optional)')->options(WhatnotChannel::where('status', 'active')->pluck('name', 'id'))->searchable()->nullable()->columnSpan(1),
                        TimePicker::make('end_time')->label('End Time (Optional)')->columnSpan(1),
                        MultiSelect::make('streamers')->label('Streamers')->options(Streamer::where('status', 'active')->orderBy('name')->pluck('name', 'id'))->preload()->searchable()->columnSpan(2)->helperText('Who will be streaming this show?'),
                    ]),
                ])
                ->action(function (array $data): void {
                    $show = Show::create([
                        'show_date' => $data['show_date'],
                        'start_time' => $data['start_time'] ?? null,
                        'end_time' => $data['end_time'] ?? null,
                        // The field is optional, and a show saved without one
                        // lists as a blank row and reads as "'' is set for …"
                        // in the notification below.
                        'title' => filled($data['title'] ?? null)
                            ? $data['title']
                            : 'Show on ' . \Illuminate\Support\Carbon::parse($data['show_date'])->format('M d, Y'),
                        'whatnot_channel_id' => $data['whatnot_channel_id'] ?? null,
                        'status' => 'draft',
                        'import_source' => 'manual',
                        'created_by' => auth()->id(),
                    ]);
                    if (!empty($data['streamers'])) $show->streamers()->attach($data['streamers']);
                    Notification::make()->title('Show scheduled successfully!')->body("'{$show->title}' is set for {$show->show_date->format('M d, Y')}.")->success()->send();
                    $this->redirect(route('filament.admin.resources.shows.view', $show));
                }),

            Action::make('import_whatnot')
                ->label('Import from Whatnot')->icon('heroicon-o-arrow-down-tray')->color('info')
                ->visible(fn () => auth()->user()?->isAdmin() && FeatureFlagService::enabled('whatnot_import') && ! empty(config('vortex.whatnot.email')) && ! empty(config('vortex.whatnot.password')))
                ->requiresConfirmation()
                ->modalHeading('Import Shows from Whatnot')
                ->modalDescription('This runs the Whatnot scraper. Existing shows are matched by Whatnot show ID and updated, not duplicated.')
                ->modalSubmitActionLabel('Run Import')
                ->action(function () {
                    try {
                        $result = app(WhatnotScraper::class)->importAllEnabledChannels(limit: (int) config('vortex.whatnot.limit', 50));
                        $channelNote = $result['channels'] > 1 ? " across {$result['channels']} channels" : '';
                        Notification::make()->title('Whatnot import complete')->body("{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped{$channelNote}.")->success()->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title('Whatnot import failed')->body($e->getMessage())->danger()->send();
                    }
                }),

            Action::make('detect_streamers_all')
                ->label('Detect Streamers')->icon('heroicon-o-user-circle')->color('gray')->visible(fn () => auth()->user()?->isAdmin())
                ->requiresConfirmation()->modalHeading('Detect Streamers')
                ->modalDescription('Matches shows with no streamer against the active streamer roster. This also runs automatically on Whatnot import.')
                ->modalSubmitActionLabel('Run Detection')
                ->action(function () {
                    $shows = Show::whereDoesntHave('streamers')->get();
                    $matched = 0;
                    foreach ($shows as $show) {
                        $suggestions = $show->detectStreamers();
                        if (collect($suggestions)->contains('confidence', 'high')) $matched++;
                    }
                    Notification::make()->title('Streamer detection complete')->body("{$matched} of {$shows->count()} unmapped show(s) matched.")->success()->send();
                }),

            Action::make('export_excel')->label('Export Excel')->icon('heroicon-o-arrow-down-tray')->color('gray')->visible(fn () => auth()->user()?->isAdmin())->url(fn () => route('export.shows'))->openUrlInNewTab(),
        ];
    }
}
