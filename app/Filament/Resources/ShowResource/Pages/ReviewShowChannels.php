<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Pages\Shows;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Models\ShowIngestionLog;
use App\Models\WhatnotChannel;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReviewShowChannels extends ListRecords
{
    protected static string $resource = ShowResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        return (auth()->user()?->isAdmin() ?? false) && ShowResource::canAccess();
    }

    /** @var array<int, list<int>> */
    private array $importedUnderMemo = [];

    /** @var array<int, string>|null */
    private ?array $channelNamesMemo = null;

    /**
     * Every channel this show's imports ran under, assigned channel first.
     *
     * Read from the ingestion logs, which record the channel each import ran
     * for — the flag itself only says that a second channel was seen.
     *
     * @return list<int>
     */
    protected function importedUnder(Show $show): array
    {
        return $this->importedUnderMemo[$show->id] ??= ShowIngestionLog::query()
            ->where('show_id', $show->id)->whereNotNull('whatnot_channel_id')
            ->distinct()->pluck('whatnot_channel_id')
            ->map(fn ($id) => (int) $id)
            ->prepend((int) $show->whatnot_channel_id)
            ->filter()->unique()->values()->all();
    }

    protected function channelName(int $id): string
    {
        $this->channelNamesMemo ??= WhatnotChannel::pluck('name', 'id')->all();

        return $this->channelNamesMemo[$id] ?? "Channel #{$id}";
    }

    protected function otherChannels(Show $show): array
    {
        return array_values(array_filter($this->importedUnder($show), fn (int $id) => $id !== (int) $show->whatnot_channel_id));
    }

    public function getTitle(): string
    {
        return 'Channel Review';
    }

    public function getSubheading(): ?string
    {
        return 'Flagged shows across all channels, including historical imports. Choose the channel where each show actually ran.';
    }

    protected function getTableQuery(): Builder
    {
        return Show::query()->where('channel_attribution_suspect', true)->with(['channel:id,name', 'streamers:id,name']);
    }

    // A separate table avoids saved filters/column choices hiding the review queue.
    protected function makeTable(): Table
    {
        return $this->table($this->makeBaseTable()->query(fn () => $this->getTableQuery()));
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Stack::make([
                    TextColumn::make('show_date')->label('Date')->date('M j, Y')->sortable()->color('gray'),
                    TextColumn::make('title')->label('Show')->searchable()->weight('semibold')->wrap(),
                    TextColumn::make('channel.name')->label('Assigned channel')->prefix('Assigned channel: ')->placeholder('No channel assigned')->color('warning')->wrap(),
                    TextColumn::make('streamers.name')->label('Streamers')->badge()->separator(', '),
                    TextColumn::make('imported_under')->label('Also imported under')
                        ->state(fn (Show $record) => array_map(fn (int $id) => $this->channelName($id), $this->otherChannels($record)))
                        ->prefix('Also imported under: ')->badge()->color('info')->separator(', ')
                        ->placeholder('Other channel not recorded for this import'),
                    TextColumn::make('review_reason')
                        ->state(fn (Show $record) => count($this->importedUnder($record)) > 1
                            ? 'Imported under '.count($this->importedUnder($record)).' channels. Confirm which one it actually ran on.'
                            : 'Imported under more than one channel. Confirm the correct assignment.')
                        ->wrap()->color('gray'),
                ])->space(2),
            ])
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->defaultSort('show_date', 'desc')
            ->paginationPageOptions([12, 24, 48])
            ->defaultPaginationPageOption(12)
            ->filters([
                SelectFilter::make('whatnot_channel_id')->label('Assigned channel')->options(fn () => WhatnotChannel::orderBy('name')->pluck('name', 'id')),
            ])
            ->recordActions([
                Action::make('confirm_channel')
                    ->label('Confirm Channel')->icon('heroicon-o-check-badge')->color('warning')->button()
                    ->modalHeading('Confirm the show channel')
                    ->modalDescription(fn (Show $record) => $record->title)
                    ->modalSubmitActionLabel('Save channel & clear review')
                    ->schema([
                        Select::make('whatnot_channel_id')->label('Correct channel')
                            // Channels this show was imported under are marked, so the
                            // likely answers stand out from the full list.
                            ->options(fn (Show $record) => WhatnotChannel::orderBy('name')->pluck('name', 'id')
                                ->map(fn (string $name, int $id) => in_array($id, $this->importedUnder($record), true)
                                    ? $name.($id === (int) $record->whatnot_channel_id ? ' — assigned now' : ' — also imported under')
                                    : $name))
                            ->helperText(fn (Show $record) => 'Imported under: '.implode(', ', array_map(fn (int $id) => $this->channelName($id), $this->importedUnder($record))).'.')
                            ->default(fn (Show $record) => $record->whatnot_channel_id)
                            ->searchable()->required()->exists('whatnot_channels', 'id'),
                    ])
                    ->action(function (Show $record, array $data): void {
                        abort_unless(static::canAccess(), 403);
                        $record->update(['whatnot_channel_id' => $data['whatnot_channel_id'], 'channel_attribution_suspect' => false]);
                        Notification::make()->title('Channel confirmed')->body('The show has been removed from this review queue.')->success()->send();
                    }),
                Action::make('view_show')->label('Show details')->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Show $record) => ShowResource::getUrl('view', ['record' => $record])),
            ])
            ->emptyStateIcon('heroicon-o-check-badge')
            ->emptyStateHeading('No channels need review')
            ->emptyStateDescription('Every show in this view has a confirmed channel. New attribution conflicts will appear here.');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('shows')->label('Shows Overview')->color('gray')->url(Shows::getUrl())];
    }
}
