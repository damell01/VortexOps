<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
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
use Filament\Tables\Table;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class ShowIngestionLogResource extends Resource
{
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'streams';
    protected static ?string $model = ShowIngestionLog::class;

    protected static ?string $navigationParentItem = 'Import';

    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-arrow-down-tray'; }
    public static function getNavigationGroup(): string|\UnitEnum|null  { return AdminModules::navigationGroupFor('streams'); }
    public static function getNavigationSort(): ?int                     { return 4; }
    public static function getModelLabel(): string                       { return 'Ingestion Log'; }
    public static function getPluralModelLabel(): string                 { return 'Ingestion Logs'; }
    public static function getNavigationLabel(): string                  { return 'Ingestion Logs'; }

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('show');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ingestion Details')->columnSpanFull()->schema([
                \Filament\Forms\Components\Placeholder::make('source')
                    ->label('Source')
                    ->content(fn ($record) => $record?->source ?? '—'),

                \Filament\Forms\Components\Placeholder::make('status')
                    ->label('Status')
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
            ->columns([
                TextColumn::make('show.title')
                    ->label('Show')
                    ->placeholder('No show linked')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('source')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ShowIngestionLog::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => StatusColor::for($state)),

                TextColumn::make('error_message')
                    ->label('Error')
                    ->placeholder('—')
                    ->limit(60)
                    ->tooltip(fn ($record) => $record->error_message),

                TextColumn::make('created_at')
                    ->label('Ingested At')
                    ->dateTime('M j, Y g:i A')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([15, 25, 50])
            ->filters([
                SelectFilter::make('status')
                    ->options(ShowIngestionLog::statusLabels()),

                SelectFilter::make('source')
                    ->options(['whatnot' => 'Whatnot', 'manual' => 'Manual']),

                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                    ),
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
