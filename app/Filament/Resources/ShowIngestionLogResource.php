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

    public static function canCreate(): bool    { return false; }
    public static function canEdit($r): bool    { return false; }
    public static function canDelete($r): bool  { return auth()->user()?->isOwner() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isOwner() ?? false; }

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

                \Filament\Forms\Components\Placeholder::make('failure_type')
                    ->label('Failure type')
                    ->content(fn ($record) => $record?->failureTypeLabel() ?? '—'),

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
                    ->label('Full error message')
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
                    ->placeholder('Unknown')
                    ->sortable(),

                TextColumn::make('summary')
                    ->label('What happened')
                    ->getStateUsing(fn (ShowIngestionLog $record) => $record->summary())
                    ->description(function (ShowIngestionLog $record): string {
                        if ($record->status !== 'failed') {
                            return $record->sourceLabel();
                        }

                        $error = trim((string) $record->error_message);

                        return $error !== ''
                            ? \Illuminate\Support\Str::limit($error, 90)
                            : 'Open the record for failure details';
                    })
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

                SelectFilter::make('source')
                    ->label('What ran')
                    ->options(ShowIngestionLog::sourceLabels())
                    ->multiple(),

                Filter::make('problems_only')
                    ->label('Problems only')
                    ->query(fn (Builder $query) => $query->where('status', '!=', 'success')),

                Filter::make('hide_old_failures')
                    ->label('Hide failures older than 24h')
                    ->query(fn (Builder $query) => $query->where(function (Builder $query): void {
                        $query->where('status', '!=', 'failed')
                            ->orWhere('created_at', '>=', now()->subDay());
                    })),

                Filter::make('created_at')
                    ->label('Date range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
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
