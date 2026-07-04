<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Resources\ShowResource\Pages;
use App\Filament\Resources\ShowResource\RelationManagers\OrdersRelationManager;
use App\Models\DeductionRequest;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\QueryBuilder\Constraints\DateConstraint;
use Filament\QueryBuilder\Constraints\NumberConstraint;
use Filament\QueryBuilder\Constraints\SelectConstraint;
use Filament\QueryBuilder\Constraints\TextConstraint;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\QueryBuilder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class ShowResource extends Resource
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'streams';

    protected static ?string $model = Show::class;

    // Streamers can access shows; row-level scoping in getEloquentQuery() limits what they see
    protected static function passesModuleAccessCheck(): bool { return true; }

    public static function getEloquentQuery(): Builder
    {
        // latestDeductionRequest.lines and payouts.streamer are only needed on view/edit.
        return parent::getEloquentQuery()->with([
            'streamers',
            'channel',
            'latestDeductionRequest',
            'payouts',
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
        $count = Cache::remember('nav_badge:shows_pending_review', 60, fn () =>
            \App\Models\Show::where('status', 'pending_review')->count()
        );
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

            Section::make('Paper Sales')->columns(3)->schema([
                TextInput::make('paper_sales_gross')
                    ->label('Paper Sales Gross')
                    ->numeric()
                    ->prefix('$')
                    ->nullable()
                    ->helperText('Revenue from the streamer\'s own paper tracking (not Whatnot).'),

                TextInput::make('paper_sales_units')
                    ->label('Paper Sales Units')
                    ->numeric()
                    ->nullable(),

                Toggle::make('sales_reconciled')
                    ->label('Sales Reconciled')
                    ->helperText('Mark when Whatnot totals and paper sheet have been compared.')
                    ->columnSpanFull(),

                Textarea::make('paper_sales_notes')
                    ->label('Paper Sales Notes')
                    ->rows(2)
                    ->nullable()
                    ->columnSpanFull(),
            ]),

            Section::make('Notes')->schema([
                Textarea::make('notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]),

            Section::make('Whatnot Analytics')
                ->description('Populated automatically during import. Values can be corrected manually.')
                ->collapsible()
                ->collapsed()
                ->columns(3)
                ->schema([
                    TextInput::make('completed_earnings')
                        ->label('Completed Earnings')
                        ->prefix('$')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('avg_order_value')
                        ->label('Avg Order Value')
                        ->prefix('$')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('giveaway_spend')
                        ->label('Giveaway Spend')
                        ->prefix('$')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('giveaways_count')
                        ->label('Giveaways')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('buyers_count')
                        ->label('Buyers')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('first_time_buyers')
                        ->label('First-Time Buyers')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('returning_buyers')
                        ->label('Returning Buyers')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('shares_count')
                        ->label('Shares')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('max_concurrent_viewers')
                        ->label('Peak Viewers')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('total_views')
                        ->label('Total Views')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('avg_order_rating')
                        ->label('Avg Rating')
                        ->numeric()
                        ->nullable(),
                    TextInput::make('detail_url')
                        ->label('Whatnot Show URL')
                        ->url()
                        ->nullable()
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

            Section::make('P&L Summary')
                ->visible(fn (?Show $record) => $record !== null)
                ->columns(3)
                ->schema([
                    Placeholder::make('pl_gross')
                        ->label('Gross Revenue')
                        ->content(fn (?Show $record): string => '$' . number_format((float) ($record?->gross_revenue ?? 0), 2)),

                    Placeholder::make('pl_net')
                        ->label('Whatnot Net')
                        ->content(fn (?Show $record): string => '$' . number_format((float) ($record?->whatnot_net ?? 0), 2)),

                    Placeholder::make('pl_tips')
                        ->label('Tips')
                        ->content(fn (?Show $record): string => '$' . number_format((float) ($record?->tips ?? 0), 2)),

                    Placeholder::make('pl_cogs')
                        ->label('COGS (Deduction)')
                        ->content(function (?Show $record): string {
                            $cogs = $record?->latestDeductionRequest?->lines?->sum('line_total') ?? 0;
                            return '$' . number_format((float) $cogs, 2);
                        }),

                    Placeholder::make('pl_payouts')
                        ->label('Total Payouts')
                        ->content(fn (?Show $record): string => '$' . number_format((float) ($record?->payouts?->sum('calculated_payout') ?? 0), 2)),

                    Placeholder::make('pl_margin')
                        ->label('Net (after COGS + Payouts)')
                        ->content(function (?Show $record): string {
                            $net      = (float) ($record?->whatnot_net ?? 0);
                            $tips     = (float) ($record?->tips ?? 0);
                            $cogs     = (float) ($record?->latestDeductionRequest?->lines?->sum('line_total') ?? 0);
                            $payouts  = (float) ($record?->payouts?->sum('calculated_payout') ?? 0);
                            $margin   = ($net + $tips) - $cogs - $payouts;
                            $base     = $net + $tips;
                            $pct      = $base > 0 ? round(($margin / $base) * 100, 1) : 0;
                            $sign     = $margin >= 0 ? '+' : '';
                            return '$' . number_format($margin, 2) . " ({$sign}{$pct}%)";
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->default('—')
                    ->summarize(Sum::make()->money('USD')->label('Total Gross')),

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

                TextColumn::make('buyers_count')
                    ->label('Buyers')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('total_views')
                    ->label('Views')
                    ->numeric()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('avg_order_rating')
                    ->label('Rating')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('detail_url')
                    ->label('Source')
                    ->formatStateUsing(fn ($state) => $state ? 'View ↗' : null)
                    ->url(fn ($record) => $record->detail_url, shouldOpenInNewTab: true)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->striped()
            ->persistFiltersInSession()
            ->paginationPageOptions([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->deferLoading()
            ->defaultSort('show_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(Show::statusLabels())
                    ->multiple(),

                SelectFilter::make('whatnot_channel_id')
                    ->label('Channel')
                    ->relationship('channel', 'name')
                    ->multiple(),

                QueryBuilder::make()
                    ->label('Advanced Filters')
                    ->constraintPickerColumns(2)
                    ->constraints([
                        DateConstraint::make('show_date')->label('Show Date'),
                        NumberConstraint::make('gross_revenue')->label('Gross Revenue ($)'),
                        NumberConstraint::make('whatnot_net')->label('Whatnot Net ($)'),
                        NumberConstraint::make('units_sold')->label('Units Sold'),
                        SelectConstraint::make('status')
                            ->options(Show::statusLabels())
                            ->multiple(),
                        SelectConstraint::make('import_source')
                            ->label('Import Source')
                            ->options(Show::importSourceLabels()),
                        TextConstraint::make('title')->label('Show Title'),
                    ]),
            ])
            ->actions([
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
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    ExportBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'     => Pages\ListShows::route('/'),
            'create'    => Pages\CreateShow::route('/create'),
            'view'      => Pages\ViewShow::route('/{record}'),
            'edit'      => Pages\EditShow::route('/{record}/edit'),
            'inventory' => Pages\ShowInventoryBreakdown::route('/{record}/inventory'),
        ];
    }
}
