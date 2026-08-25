<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShowIngestionLogResource\Pages;
use App\Models\ShowIngestionLog;
use App\Support\AdminModules;
use App\Support\StatusColor;
use Filament\Actions\ViewAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class ShowIngestionLogResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug  = 'streams';
    protected static ?string $model = ShowIngestionLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-arrow-down-tray'; }
    public static function getNavigationGroup(): string|\UnitEnum|null  { return AdminModules::navigationGroupFor('streams'); }
    public static function getNavigationSort(): ?int                     { return 4; }
    public static function getModelLabel(): string                       { return 'Ingestion Record'; }
    public static function getPluralModelLabel(): string                 { return 'Ingestion Records'; }
    public static function getNavigationLabel(): string                  { return 'Ingestion'; }

    public static function getNavigationBadge(): ?string
    {
        $count = ShowIngestionLog::where('status', 'failed')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'danger'; }

    public static function canCreate(): bool         { return false; }
    public static function canEdit($r): bool         { return false; }
    public static function canDelete($r): bool       { return auth()->user()?->isOwner() ?? false; }
    public static function canDeleteAny(): bool      { return auth()->user()?->isOwner() ?? false; }

    /**
     * Admin-only. These are scraper run records — every channel's imports,
     * with raw error messages attached — and the query is deliberately not
     * scoped to anyone. HasModuleAccess admits any signed-in user by default,
     * which put them in front of streamers.
     */
    protected static function passesModuleAccessCheck(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['show', 'channel']);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ingestion Details')->columnSpanFull()->schema([
                \Filament\Forms\Components\Placeholder::make('summary')
                    ->label('What happened')
                    ->content(fn ($record) => $record?->summary() ?? '—'),

                \Filament\Forms\Components\Placeholder::make('source')
                    ->label('What ran')
                    ->content(fn ($record) => $record?->sourceLabel() ?? '—'),

                \Filament\Forms\Components\Placeholder::make('channel')
                    ->label('Channel')
                    ->content(fn ($record) => $record?->channel?->name ?? 'Unknown'),

                \Filament\Forms\Components\Placeholder::make('status')
                    ->label('Outcome')
                    ->content(fn ($record) => ShowIngestionLog::statusLabels()[$record?->status ?? ''] ?? ($record?->status ?? '—')),

                \Filament\Forms\Components\Placeholder::make('show_title')
                    ->label('Show')
                    ->content(fn ($record) => $record?->show?->title ?? 'No show linked'),

                \Filament\Forms\Components\Placeholder::make('error_message')
                    ->label('Error Message')
                    ->content(fn ($record) => $record?->error_message ?? '—'),

                \Filament\Forms\Components\Placeholder::make('raw_payload')
                    ->label('Raw Payload (JSON)')
                    ->content(fn ($record) => $record?->raw_payload
                        ? json_encode($record->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                        : '—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->persistFiltersInSession()
            ->deferLoading()
            ->emptyStateHeading('No ingestion logs')
            ->emptyStateDescription('Whatnot import runs are logged here with their results.')
            ->emptyStateIcon('heroicon-o-arrow-down-tray')
            ->columns([
                // Relative first, exact underneath: "12 minutes ago" answers
                // the question this page exists for, and the timestamp is
                // still there for anyone correlating against a log file.
                TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->description(fn ($record) => $record->created_at?->format('M j, Y g:i A'))
                    ->tooltip(fn ($record) => $record->created_at?->toDayDateTimeString())
                    ->sortable(),

                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->badge()
                    ->color('gray')
                    // Before this column existed the answer lived in a JSON
                    // key, and failures — which carry no show to join through
                    // — had no answer at all.
                    ->placeholder('Unknown')
                    ->sortable(),

                // What happened, in words. A row reading
                // "whatnot_spa_enrichment / success" says nothing about what
                // changed; the payload knows, so the model says it.
                TextColumn::make('summary')
                    ->label('What happened')
                    ->getStateUsing(fn (ShowIngestionLog $record) => $record->summary())
                    // The error goes here rather than in a column of its own.
                    // A separate column is a dash on every healthy row, and on
                    // a phone — where the table stacks into cards — that is a
                    // labelled empty line under every single record.
                    ->description(fn (ShowIngestionLog $record) => filled($record->error_message)
                        ? \Illuminate\Support\Str::limit($record->error_message, 120)
                        : $record->sourceLabel())
                    ->color(fn (ShowIngestionLog $record) => $record->status === 'failed' ? 'danger' : null)
                    ->tooltip(fn (ShowIngestionLog $record) => $record->error_message)
                    ->wrap(),

                TextColumn::make('show.title')
                    ->label('Show')
                    ->placeholder('—')
                    ->searchable()
                    ->limit(40)
                    ->description(fn ($record) => $record->show?->show_date?->format('M j, Y'))
                    ->url(fn ($record) => $record->show
                        ? ShowResource::getUrl('view', ['record' => $record->show_id])
                        : null),

                TextColumn::make('status')
                    ->label('Outcome')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ShowIngestionLog::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),

            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([15, 25, 50])
            ->groups([
                Group::make('channel.name')
                    ->label('Channel')
                    ->getTitleFromRecordUsing(fn ($record) => $record->channel?->name ?? 'Unknown channel'),
                Group::make('created_at')
                    ->label('Day')
                    ->date(),
                Group::make('source')
                    ->label('What ran')
                    ->getTitleFromRecordUsing(fn (ShowIngestionLog $record) => $record->sourceLabel()),
            ])
            ->filters([
                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name')
                    ->multiple()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Outcome')
                    ->options(ShowIngestionLog::statusLabels()),

                // Every source the importers actually write. The old list
                // offered "Whatnot" and "Manual" — three of the four real
                // values were missing, and nothing has ever written "manual".
                SelectFilter::make('source')
                    ->label('What ran')
                    ->options(ShowIngestionLog::sourceLabels())
                    ->multiple(),

                Filter::make('problems_only')
                    ->label('Problems only')
                    ->query(fn (Builder $query) => $query->where('status', '!=', 'success')),

                Filter::make('created_at')
                    ->label('Date range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    // $query, not $q: Filament injects the table's builder by
                    // parameter *name*. Named anything else it falls through to
                    // resolving Builder from the container, so the constraints
                    // land on a throwaway query and the filter does nothing —
                    // which is exactly what this one did.
                    ->query(fn (Builder $query, array $data) => $query
                        // ?? null: the rendered form always supplies both keys,
                        // but a filter restored from the session or a URL need
                        // not, and an undefined index here is a 500 on the page.
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                    )
                    ->indicateUsing(function (array $data): ?string {
                        $from  = $data['from'] ?? null;
                        $until = $data['until'] ?? null;

                        return match (true) {
                            $from && $until => "From {$from} to {$until}",
                            (bool) $from    => "From {$from}",
                            (bool) $until   => "Until {$until}",
                            default         => null,
                        };
                    }),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
                \Filament\Actions\DeleteAction::make()->iconButton()
                    ->visible(fn (ShowIngestionLog $record) => static::canDelete($record)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShowIngestionLogs::route('/'),
            'view'  => Pages\ViewShowIngestionLog::route('/{record}'),
        ];
    }
}
