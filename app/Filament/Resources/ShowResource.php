<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShowResource\Pages;
use App\Jobs\MapShowInventory;
use App\Models\DeductionRequest;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Filament\Actions\Action as TableAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ShowResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'streams';

    protected static ?string $model = Show::class;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with([
            'streamers',
            'channel',
            'latestDeductionRequest.lines.inventoryItem',
            'latestDeductionRequest.lines.location',
            'payouts.streamer',
        ]);
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-video-camera';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('streams');
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Cache::remember('nav-badge:shows:pending_review', 30, fn (): int => Show::where('status', 'pending_review')->count());
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getModelLabel(): string
    {
        return 'Show';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Shows';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->title ?? 'Show #' . $record->id;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return array_filter([
            'Date' => $record->show_date?->format('M j, Y'),
            'Status' => Show::statusLabels()[$record->status] ?? $record->status,
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Show Details')->columns(2)->schema([
                Select::make('whatnot_channel_id')
                    ->label('Channel')
                    ->options(WhatnotChannel::where('status', 'active')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                TextInput::make('title')
                    ->label('Show Title')
                    ->placeholder('e.g. Mojo Break #47')
                    ->maxLength(255),

                DatePicker::make('show_date')
                    ->label('Show Date')
                    ->required()
                    ->default(now()),

                Select::make('import_source')
                    ->label('Import Source')
                    ->options(Show::importSourceLabels())
                    ->default('manual')
                    ->required(),

                TimePicker::make('start_time')
                    ->label('Start Time')
                    ->nullable(),

                TimePicker::make('end_time')
                    ->label('End Time')
                    ->nullable(),

                TextInput::make('units_sold')
                    ->label('Units Sold')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                TextInput::make('show_duration')
                    ->label('Duration (minutes)')
                    ->numeric()
                    ->nullable(),

                Select::make('streamers')
                    ->label('Streamers')
                    ->multiple()
                    ->options(Streamer::where('status', 'active')->pluck('name', 'id'))
                    ->relationship('streamers', 'name')
                    ->preload()
                    ->columnSpanFull(),
            ]),

            Section::make('Financials')->columns(3)->schema([
                TextInput::make('gross_revenue')
                    ->label('Gross Revenue')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                TextInput::make('whatnot_net')
                    ->label('Whatnot Net')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                TextInput::make('tips')
                    ->label('Tips')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),
            ]),

            Section::make('Notes')->schema([
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Section::make('Approval Summary')
                ->visible(fn (?Show $record) => (bool) $record?->latestDeductionRequest)
                ->schema([
                    Placeholder::make('approval_status')
                        ->label('Approval Status')
                        ->content(function (?Show $record): string {
                            $request = $record?->latestDeductionRequest;

                            return $request
                                ? (DeductionRequest::statusLabels()[$request->status] ?? $request->status)
                                : 'No approval request yet';
                        }),
                    Placeholder::make('next_step')
                        ->label('Next Step')
                        ->content(function (?Show $record): string {
                            return match ($record?->status) {
                                'draft' => 'Finish entering show details, then assign streamers and revenue.',
                                'pending_review' => 'Run AI mapping to build the approval packet.',
                                'mapping' => 'AI mapping is in progress. Ops will be notified when review is ready.',
                                'pending_approval' => 'Review the mapped lines and approve the deduction request.',
                                'reconciled' => 'Inventory is reconciled. Review payouts and close the show when ready.',
                                'closed' => 'This show is fully complete.',
                                'cancelled' => 'This show has been cancelled.',
                                default => 'Review the show details and continue the next operational step.',
                            };
                        }),
                    Placeholder::make('mapped_items')
                        ->label('Mapped Items')
                        ->content(function (?Show $record): string {
                            $request = $record?->latestDeductionRequest;

                            if (! $request || $request->lines->isEmpty()) {
                                return 'No mapped items yet.';
                            }

                            return $request->lines->map(function ($line) {
                                $item = $line->inventoryItem?->name ?? 'Unknown item';
                                $location = $line->location?->name ?? 'Unknown location';

                                return "{$item} x {$line->quantity_approved} from {$location}";
                            })->implode("\n");
                        }),
                    Placeholder::make('mapped_line_count')
                        ->label('Mapped Lines')
                        ->content(fn (?Show $record): string => (string) ($record?->latestDeductionRequest?->lines?->count() ?? 0)),
                    Placeholder::make('mapped_total')
                        ->label('Mapped COGS')
                        ->content(function (?Show $record): string {
                            $request = $record?->latestDeductionRequest;

                            return $request
                                ? '$' . number_format((float) $request->lines->sum('line_total'), 2)
                                : '$0.00';
                        }),
                ]),

            Section::make('Show Recap')
                ->visible(fn (?Show $record) => (bool) $record?->payouts?->count())
                ->schema([
                    Placeholder::make('payouts_summary')
                        ->label('Payout Summary')
                        ->content(function (?Show $record): string {
                            if (! $record || $record->payouts->isEmpty()) {
                                return 'No payouts have been calculated yet.';
                            }

                            return $record->payouts
                                ->map(function ($payout) {
                                    $streamer = $payout->streamer?->name ?? 'Unknown streamer';
                                    $type = Streamer::payoutTypeLabels()[$payout->payout_type] ?? $payout->payout_type;

                                    return "{$streamer}: $" . number_format((float) $payout->calculated_payout, 2) . " ({$type})";
                                })
                                ->implode("\n");
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutEagerLoads()
                ->with([
                    'streamers:id,name',
                    'channel:id,name',
                    'latestDeductionRequest:id,show_id,status',
                ]))
            ->columns([
                TextColumn::make('show_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),

                TextColumn::make('title')
                    ->label('Show Title')
                    ->default('—')
                    ->searchable(),

                TextColumn::make('channel.name')
                    ->label('Channel')
                    ->placeholder('—'),

                TextColumn::make('streamers.name')
                    ->label('Streamers')
                    ->badge()
                    ->separator(', '),

                TextColumn::make('gross_revenue')
                    ->label('Gross Revenue')
                    ->money('USD')
                    ->default('—'),

                TextColumn::make('units_sold')
                    ->label('Units')
                    ->numeric(),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Show::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft' => 'gray',
                        'pending_review' => 'warning',
                        'mapping' => 'info',
                        'pending_approval' => 'warning',
                        'reconciled' => 'success',
                        'closed' => 'success',
                        'cancelled' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('latestDeductionRequest.status')
                    ->label('Approval')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? (DeductionRequest::statusLabels()[$state] ?? $state) : 'Not started')
                    ->color(fn ($state) => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'info',
                        'processed' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                TextColumn::make('import_source')
                    ->badge()
                    ->formatStateUsing(fn ($state) => Show::importSourceLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'manual' => 'gray',
                        'auto_whatnot' => 'info',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->stackedOnMobile()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('show_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(Show::statusLabels()),

                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name'),

                SelectFilter::make('import_source')
                    ->label('Import Source')
                    ->options(Show::importSourceLabels()),

                Filter::make('show_date')
                    ->form([
                        DatePicker::make('from')->label('From Date'),
                        DatePicker::make('until')->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('show_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('show_date', '<=', $date));
                    }),
            ])
            ->actions([
                TableAction::make('run_ai_mapping')
                    ->label('Map Sales with AI')
                    ->icon('heroicon-o-sparkles')
                    ->color('primary')
                    ->visible(fn (Show $record) => $record->status === 'pending_review' && $record->streamers()->exists())
                    ->requiresConfirmation()
                    ->action(function (Show $record) {
                        MapShowInventory::dispatch($record->id);
                        Notification::make()
                            ->title('AI Mapping queued')
                            ->body('We are mapping this show now. Ops will be notified when approval is ready.')
                            ->success()
                            ->send();
                    }),

                TableAction::make('view_deduction')
                    ->label(fn (Show $record) => $record->status === 'pending_approval' ? 'Review Approval' : 'View Approval')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('info')
                    ->visible(fn (Show $record) => in_array($record->status, ['pending_approval', 'reconciled', 'closed']))
                    ->url(fn (Show $record) => DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $record->id])),

                TableAction::make('cancel_show')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn () => auth()->user()?->isAdmin())
                    ->requiresConfirmation()
                    ->modalHeading('Cancel Show')
                    ->modalDescription('Are you sure you want to cancel this show? This cannot be undone.')
                    ->action(fn (Show $record) => $record->update(['status' => 'cancelled'])),

                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListShows::route('/'),
            'create' => Pages\CreateShow::route('/create'),
            'view' => Pages\ViewShow::route('/{record}'),
            'edit' => Pages\EditShow::route('/{record}/edit'),
        ];
    }
}
