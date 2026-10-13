<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Pages\Shows;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Models\ShowChangeLog;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Services\ShowStreamerSuggester;
use App\Support\NavVisibility;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewShowStreamers extends ListRecords
{
    protected static string $resource = ShowResource::class;
    private ?ShowStreamerSuggester $suggester = null;
    private array $suggestionMemo = [];

    public static function canAccess(array $parameters = []): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && ShowResource::canAccess();
    }

    public static function canAssign(): bool
    {
        return static::canAccess() && ! NavVisibility::isReadOnlyForUser(ShowResource::class, auth()->user())
            && ! NavVisibility::isReadOnlyForUser(static::class, auth()->user());
    }

    public function getTitle(): string { return 'Stream Review'; }
    public function getSubheading(): ?string
    {
        return 'Every unassigned show across all dates and channels. Review the host, then assign one show or a selected group. The channel switcher does not restrict this queue.';
    }
    public function getView(): string { return 'filament.resources.show-resource.pages.stream-review'; }

    protected function getTableQuery(): Builder
    {
        return Show::query()->whereDoesntHave('streamers')->with('channel:id,name');
    }

    protected function makeTable(): Table
    {
        return $this->table($this->makeBaseTable()->query(fn () => $this->getTableQuery()));
    }

    protected function host(Show $show): array
    {
        $this->suggester ??= app(ShowStreamerSuggester::class);
        return $this->suggestionMemo[$show->id] ??= $this->suggester->suggest($show);
    }

    protected function streamerOptions(): array
    {
        return Streamer::query()->streamers()->orderBy('name')->get(['id','name','status'])
            ->mapWithKeys(fn (Streamer $s) => [$s->id=>$s->name.($s->status !== 'active' ? ' ('.$s->status.')' : '')])->all();
    }

    protected function assignmentField(): Select
    {
        return Select::make('streamer_ids')->label('Correct streamer(s)')->multiple()->searchable()
            ->options(fn () => $this->streamerOptions())->required()->minItems(1)
            ->helperText('Choose every host who actually streamed. The first selected profile is the primary streamer. Inactive profiles remain available for historical shows.');
    }

    /** Recheck after locking; concurrent assignments are skipped, never overwritten. */
    public function assignUnassigned(array $showIds, array $streamerIds): array
    {
        abort_unless(static::canAssign(), 403);
        $data = validator(['show_ids'=>$showIds,'streamer_ids'=>$streamerIds],[
            'show_ids'=>'required|array|min:1', 'show_ids.*'=>'required|integer|distinct',
            'streamer_ids'=>'required|array|min:1', 'streamer_ids.*'=>'required|integer|distinct',
        ])->validate();
        $ids = array_map('intval',$data['show_ids']);
        $hosts = array_map('intval',$data['streamer_ids']);
        $result = DB::transaction(function () use ($ids,$hosts) {
            $profiles=Streamer::query()->streamers()->whereKey($hosts)->orderBy('id')->lockForUpdate()->get();
            if ($profiles->count() !== count($hosts)) {
                throw ValidationException::withMessages(['streamer_ids'=>'Select valid streamer profiles. Fulfillment-only profiles cannot host a show.']);
            }
            $shows=Show::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get();
            $assigned=0;
            foreach ($shows as $show) {
                if ($show->streamers()->exists()) continue;
                $pivot=[];
                foreach ($hosts as $index=>$id) $pivot[$id]=['is_primary'=>$index===0];
                $show->streamers()->sync($pivot);
                ShowChangeLog::logChange($show,'streamers',[], $hosts,'stream-review');
                $assigned++;
            }
            return ['assigned'=>$assigned,'skipped'=>count($ids)-$assigned,'names'=>$profiles->pluck('name')->join(', ')];
        });
        $this->suggestionMemo=[];
        Cache::forget('tab_badge:shows_needs_matching');
        return $result;
    }

    protected function notifyAssignment(array $result): void
    {
        $notification=Notification::make()->title($result['assigned'].' show(s) assigned')
            ->body($result['names'].($result['skipped'] ? '. '.$result['skipped'].' already assigned or unavailable show(s) were skipped.' : '. Assigned shows leave this queue.'));
        ($result['assigned'] ? $notification->success() : $notification->warning())->send();
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Stack::make([
                TextColumn::make('show_date')->label('Date')->date('M j, Y')->sortable()->color('gray'),
                TextColumn::make('title')->label('Show')->searchable()->weight('semibold')->wrap(),
                TextColumn::make('channel.name')->label('Channel')->prefix('Channel: ')->placeholder('No channel assigned')->wrap(),
                TextColumn::make('status')->label('Show status')->badge()
                    ->formatStateUsing(fn (string $state) => Show::statusLabels()[$state] ?? ucfirst(str_replace('_',' ',$state))),
                TextColumn::make('detected_host')->label('Detected host')
                    ->state(fn (Show $record) => $this->host($record)['detected_name'])
                    ->prefix('Detected host: ')->placeholder('No clear host in the title')->wrap()->color('gray'),
                TextColumn::make('suggested_streamer')->label('Suggested streamer')
                    ->state(function (Show $record) {
                        $host=$this->host($record);
                        if ($s=$host['suggestion']) return 'Suggested: '.$s['name'];
                        return count($host['candidates']) > 1 ? 'Multiple possible hosts: '.collect($host['candidates'])->pluck('name')->join(', ') : null;
                    })->wrap()->badge()->color(fn (Show $record) => $this->host($record)['suggestion'] ? 'success' : 'warning')
                    ->placeholder('Choose a streamer manually'),
                TextColumn::make('matching_reason')->label('Why')
                    ->state(fn (Show $record) => $this->host($record)['suggestion']['reason']
                        ?? (count($this->host($record)['candidates']) > 1 ? 'Check every host. This show will not be assigned automatically.' : 'No unique profile or alias matched.'))
                    ->size('xs')->color('gray')->wrap(),
            ])->space(2),
        ])->contentGrid(['md'=>2,'xl'=>3])->defaultSort('show_date','desc')
            ->paginationPageOptions([12,24,48])->defaultPaginationPageOption(12)
            ->filters([
                SelectFilter::make('whatnot_channel_id')->label('Channel')->options(fn () => WhatnotChannel::orderBy('name')->pluck('name','id'))->searchable(),
                SelectFilter::make('status')->label('Show status')->options(Show::statusLabels()),
                Filter::make('dates')->schema([
                    DatePicker::make('from')->label('Show date from'),
                    DatePicker::make('until')->label('Show date through'),
                ])->query(fn ($query,array $data) => $query
                    ->when($data['from'] ?? null, fn ($q,$date)=>$q->whereDate('show_date','>=',$date))
                    ->when($data['until'] ?? null, fn ($q,$date)=>$q->whereDate('show_date','<=',$date))),
            ])
            ->recordActions([
                Action::make('accept_suggestion')->label('Use suggestion')
                    ->icon('heroicon-o-check')->color('success')->button()
                    ->visible(fn (Show $record) => static::canAssign() && $this->host($record)['suggestion'] !== null)
                    ->requiresConfirmation()->modalHeading('Assign the suggested streamer?')
                    ->modalDescription(fn (Show $record) => $record->title.' → '.($this->host($record)['suggestion']['name'] ?? ''))
                    ->modalSubmitActionLabel('Assign streamer')
                    ->action(function (Show $record) {
                        abort_unless(static::canAssign(),403);
                        $suggestion=app(ShowStreamerSuggester::class)->suggest($record)['suggestion'];
                        abort_unless($suggestion,422,'The suggestion changed. Choose a streamer manually.');
                        $this->notifyAssignment($this->assignUnassigned([$record->id],[$suggestion['id']]));
                    }),
                Action::make('assign_streamers')->label('Choose streamer')->icon('heroicon-o-user-plus')->color('gray')->button()
                    ->visible(fn () => static::canAssign())
                    ->modalHeading('Assign this show')->modalDescription(fn (Show $record) => $record->title)
                    ->modalSubmitActionLabel('Save assignment')
                    ->schema([$this->assignmentField()->default(fn (Show $record) => ($s=$this->host($record)['suggestion']) ? [$s['id']] : [])])
                    ->action(fn (Show $record,array $data) => $this->notifyAssignment($this->assignUnassigned([$record->id],$data['streamer_ids']))),
                Action::make('view_show')->label('Show details')->icon('heroicon-o-arrow-top-right-on-square')->iconButton()->tooltip('Show details')
                    ->url(fn (Show $record) => ShowResource::getUrl('view',['record'=>$record])),
            ])
            ->bulkActions([
                BulkAction::make('assign_streamers')->label('Assign selected shows')->icon('heroicon-o-user-plus')
                    ->visible(fn () => static::canAssign())->modalHeading('Assign selected shows')
                    ->modalDescription('Choose the correct host(s) for every selected show. Shows assigned by another person while you were reviewing will be skipped.')
                    ->modalSubmitActionLabel('Save assignments')->schema([$this->assignmentField()])
                    ->action(fn (Collection $records,array $data) => $this->notifyAssignment($this->assignUnassigned($records->modelKeys(),$data['streamer_ids'])))
                    ->deselectRecordsAfterCompletion(),
            ])
            ->emptyStateIcon('heroicon-o-check-badge')->emptyStateHeading('No streams need review')
            ->emptyStateDescription('No unassigned shows match this view. Clear filters to check the rest of the queue.');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('channel_review')->label('Channel Review')->color('gray')
                ->url(ShowResource::getUrl('channel-review'))->visible(fn()=>ReviewShowChannels::canAccess()),
            Action::make('shows')->label('Shows Overview')->color('gray')->url(Shows::getUrl()),
        ];
    }
}
