<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Pages\Shows;
use App\Filament\Resources\ShowResource;
use App\Models\Show;
use App\Services\ChannelSuggester;
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

    private ?ChannelSuggester $suggester = null;

    /** @var array<int, array|null> */
    private array $suggestionMemo = [];

    protected function suggester(): ChannelSuggester
    {
        return $this->suggester ??= app(ChannelSuggester::class);
    }

    /** Every channel this show's imports ran under, assigned channel first. */
    protected function importedUnder(Show $show): array
    {
        return $this->suggester()->importedUnder($show);
    }

    protected function channelName(int $id): string
    {
        return $this->suggester()->channelName($id);
    }

    protected function suggestion(Show $show): ?array
    {
        return array_key_exists($show->id, $this->suggestionMemo)
            ? $this->suggestionMemo[$show->id]
            : $this->suggestionMemo[$show->id] = $this->suggester()->suggest($show);
    }

    protected static function confirm(Show $show, int $channelId): void
    {
        $show->update(['whatnot_channel_id' => $channelId, 'channel_attribution_suspect' => false]);
    }

    /**
     * Every flagged show whose suggestion is high confidence.
     *
     * @return \Illuminate\Support\Collection<int, array{show: Show, suggestion: array}>
     */
    protected function highConfidence(): \Illuminate\Support\Collection
    {
        $shows = Show::query()->where('channel_attribution_suspect', true)->with('streamers:id,name')->get();
        $this->suggester()->primeImportedUnder($shows);

        return $shows->map(fn (Show $show) => ['show' => $show, 'suggestion' => $this->suggester()->suggest($show)])
            ->filter(fn (array $row) => ($row['suggestion']['level'] ?? null) === ChannelSuggester::HIGH)->values();
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
                    TextColumn::make('suggested_channel')->label('Suggested channel')
                        ->state(function (Show $record) {
                            $s = $this->suggestion($record);
                            if (! $s) return null;
                            $keeps = $s['channel_id'] === (int) $record->whatnot_channel_id ? ' (keeps current)' : '';
                            return 'Suggested: '.$s['name'].$keeps.' · '.ucfirst($s['level']).' confidence';
                        })
                        ->badge()->icon('heroicon-o-light-bulb')
                        ->color(fn (Show $record) => match ($this->suggestion($record)['level'] ?? null) {
                            ChannelSuggester::HIGH => 'success', ChannelSuggester::MEDIUM => 'warning', default => 'gray',
                        })
                        ->placeholder('No suggestion — not enough history'),
                    TextColumn::make('suggestion_reasons')->label('Why')
                        ->state(fn (Show $record) => implode(' ', $this->suggestion($record)['reasons'] ?? []) ?: null)
                        ->size('xs')->color('gray')->wrap(),
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
                Action::make('accept_suggestion')
                    ->label(fn (Show $record) => 'Accept '.($this->suggestion($record)['name'] ?? ''))
                    ->icon('heroicon-o-check')->color('success')->button()
                    ->visible(fn (Show $record) => $this->suggestion($record) !== null)
                    ->action(function (Show $record): void {
                        abort_unless(static::canAccess(), 403);
                        $suggestion = $this->suggestion($record);
                        abort_unless($suggestion !== null, 422);
                        static::confirm($record, $suggestion['channel_id']);
                        Notification::make()->title('Channel confirmed')->body($record->title.' → '.$suggestion['name'])->success()->send();
                    }),
                Action::make('confirm_channel')
                    ->label('Choose channel')->icon('heroicon-o-check-badge')->color('gray')->button()
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
                            ->default(fn (Show $record) => $this->suggestion($record)['channel_id'] ?? $record->whatnot_channel_id)
                            ->searchable()->required()->exists('whatnot_channels', 'id'),
                    ])
                    ->action(function (Show $record, array $data): void {
                        abort_unless(static::canAccess(), 403);
                        static::confirm($record, (int) $data['whatnot_channel_id']);
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
        return [
            Action::make('confirm_high_confidence')
                ->label('Confirm high-confidence suggestions')->icon('heroicon-o-bolt')->color('success')
                ->requiresConfirmation()
                ->modalHeading('Confirm every high-confidence suggestion?')
                ->modalDescription(function () {
                    $rows = $this->highConfidence();
                    if ($rows->isEmpty()) return 'No flagged show has a high-confidence suggestion right now.';
                    $byChannel = $rows->countBy(fn (array $r) => $r['suggestion']['name'])->map(fn ($n, $name) => "{$name}: {$n}")->implode(', ');
                    return "{$rows->count()} shows will be confirmed ({$byChannel}). Medium and low confidence shows stay in the queue for you to check.";
                })
                ->modalSubmitActionLabel('Confirm them')
                ->action(function (): void {
                    abort_unless(static::canAccess(), 403);
                    $rows = $this->highConfidence();
                    \Illuminate\Support\Facades\DB::transaction(fn () => $rows->each(fn (array $r) => static::confirm($r['show'], $r['suggestion']['channel_id'])));
                    $this->suggestionMemo = [];
                    Notification::make()->title($rows->count().' shows confirmed')->body('Only high-confidence suggestions were applied.')->success()->send();
                }),
            Action::make('shows')->label('Shows Overview')->color('gray')->url(Shows::getUrl()),
        ];
    }
}
