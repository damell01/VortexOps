<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\HasModuleAccess;
use App\Filament\Concerns\HasAdminNavVisibility;
use App\Filament\Resources\ShowResource\Pages;
use App\Filament\Resources\ShowResource\RelationManagers\OrdersRelationManager;
use App\Models\DeductionRequest;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Support\AdminModules;
use Filament\Actions\Action as TableAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
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
    use HasModuleAccess, HasAdminNavVisibility;

    protected static string $moduleSlug  = 'streams';
    protected static string $featureSlug = 'shows';

    protected static ?string $model = Show::class;

    // Streamers can access shows; row-level scoping in getEloquentQuery() limits what they see
    protected static function passesModuleAccessCheck(): bool { return true; }

    public static function canCreate(): bool    { return auth()->user()?->isAdmin() ?? false; }
    public static function canEdit($r): bool    { return auth()->user()?->isAdmin() ?? false; }
    public static function canDelete($r): bool  { return auth()->user()?->isAdmin() ?? false; }
    public static function canDeleteAny(): bool { return auth()->user()?->isAdmin() ?? false; }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with([
            'streamers',
            'channel',
            'latestDeductionRequest',
            'payouts',
        ]);

        $user = auth()->user();
        if ($user && $user->isStreamer() && ! $user->isAdmin()) {
            $streamerId = $user->streamer?->id ?? 0;
            $query->whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamerId));
        }

        return $query;
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
                DatePicker::make('show_date')
                    ->label('Show Date')
                    ->required()
                    ->default(now()),

                Select::make('whatnot_channel_id')
                    ->label('Channel')
                    ->options(WhatnotChannel::where('status', 'active')->pluck('name', 'id'))
                    ->searchable()
                    ->nullable(),

                TextInput::make('title')
                    ->label('Show Title')
                    ->placeholder('e.g. Mojo Break #47')
                    ->maxLength(255),

                Select::make('status')
                    ->label('Status')
                    ->options(Show::statusLabels())
                    ->required()
                    ->default('pending_review')
                    ->visible(fn () => auth()->user()?->isAdmin()),

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

                Select::make('import_source')
                    ->label('Import Source')
                    ->options(Show::importSourceLabels())
                    ->default('manual')
                    ->required()
                    ->visible(fn () => auth()->user()?->isOwner())
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
                        ->columnSpanFull()
                        ->content(function (?Show $record): \Illuminate\Support\HtmlString {
                            $request = $record?->latestDeductionRequest;
                            if (! $request || $request->lines->isEmpty()) {
                                return new \Illuminate\Support\HtmlString('<span style="color:#9ca3af;font-size:13px">No mapped items yet.</span>');
                            }
                            $lines = $request->lines->load('inventoryItem', 'location');
                            $stageBadge = function (?string $stage): string {
                                $map = [
                                    'alias'     => ['Alias',     '#d1fae5','#065f46','#6ee7b7'],
                                    'fuzzy'     => ['Fuzzy',     '#dbeafe','#1e40af','#93c5fd'],
                                    'embedding' => ['Embedding', '#ede9fe','#5b21b6','#c4b5fd'],
                                    'llm'       => ['LLM',       '#fef3c7','#92400e','#fcd34d'],
                                ];
                                if (! $stage || ! isset($map[$stage])) {
                                    return '<span style="color:#9ca3af;font-size:10px">—</span>';
                                }
                                [$label, $bg, $color, $border] = $map[$stage];
                                return "<span style=\"display:inline-flex;padding:1px 7px;border-radius:9999px;background:{$bg};color:{$color};border:1px solid {$border};font-size:10px;font-weight:700\">{$label}</span>";
                            };
                            $rows = $lines->map(fn ($line) =>
                                '<tr style="border-bottom:1px solid #f3f4f6">' .
                                '<td style="padding:5px 10px;font-size:12px;color:#374151">' . e($line->inventoryItem?->name ?? '—') . '</td>' .
                                '<td style="padding:5px 10px">' . $stageBadge($line->match_stage ?? null) . '</td>' .
                                '<td style="padding:5px 10px;font-size:12px;text-align:right;color:#374151">' . number_format((float)$line->quantity_approved, 0) . '</td>' .
                                '<td style="padding:5px 10px;font-size:12px;color:#6b7280">' . e($line->location?->name ?? '—') . '</td>' .
                                '<td style="padding:5px 10px;font-size:12px;text-align:right;font-weight:600;color:#111827">$' . number_format((float)$line->line_total, 2) . '</td>' .
                                '</tr>'
                            )->join('');
                            return new \Illuminate\Support\HtmlString("
                                <div style=\"overflow-x:auto;border:1px solid #e5e7eb;border-radius:8px\">
                                <table style=\"width:100%;border-collapse:collapse\">
                                    <thead><tr style=\"background:#f9fafb\">
                                        <th style=\"padding:5px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase\">Item</th>
                                        <th style=\"padding:5px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase\">Stage</th>
                                        <th style=\"padding:5px 10px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase\">Qty</th>
                                        <th style=\"padding:5px 10px;text-align:left;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase\">Location</th>
                                        <th style=\"padding:5px 10px;text-align:right;font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase\">COGS</th>
                                    </tr></thead>
                                    <tbody>{$rows}</tbody>
                                </table>
                                </div>
                            ");
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

                            $user = auth()->user();
                            $payouts = $record->payouts;

                            if ($user && $user->isStreamer() && ! $user->isAdmin()) {
                                $myStreamerId = $user->streamer?->id;
                                $payouts = $payouts->filter(
                                    fn ($p) => $p->streamer_id === $myStreamerId
                                );
                            }

                            if ($payouts->isEmpty()) {
                                return 'No payouts have been calculated yet.';
                            }

                            return $payouts
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
                ->schema([
                    Placeholder::make('pl_card')
                        ->label('')
                        ->columnSpanFull()
                        ->content(function (?Show $record): \Illuminate\Support\HtmlString {
                            $gross   = (float) ($record?->gross_revenue ?? 0);
                            $net     = (float) ($record?->whatnot_net ?? 0);
                            $tips    = (float) ($record?->tips ?? 0);
                            $cogs    = (float) ($record?->latestDeductionRequest?->lines?->sum('line_total') ?? 0);
                            $payouts = (float) ($record?->payouts?->sum('calculated_payout') ?? 0);
                            $margin  = ($net + $tips) - $cogs - $payouts;
                            $base    = $net + $tips;
                            $pct     = $base > 0 ? round(($margin / $base) * 100, 1) : 0;
                            $sign    = $margin >= 0 ? '+' : '';

                            $f  = fn (float $v): string => '$' . number_format($v, 2);
                            $marginColor  = $margin >= 0 ? '#059669' : '#dc2626';
                            $marginBg     = $margin >= 0 ? '#f0fdf4' : '#fef2f2';
                            $marginBorder = $margin >= 0 ? '#bbf7d0' : '#fecaca';

                            $cell = fn (string $label, string $value, string $color = '#111827', string $subColor = '#6b7280'): string =>
                                "<div style=\"padding:14px 18px\">
                                    <div style=\"font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px\">{$label}</div>
                                    <div style=\"font-size:18px;font-weight:700;color:{$color}\">{$value}</div>
                                </div>";

                            return new \Illuminate\Support\HtmlString("
                                <div style=\"border:1px solid #e5e7eb;border-radius:10px;overflow:hidden\">
                                    <div style=\"display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid #e5e7eb\">
                                        {$cell('Gross Revenue', $f($gross))}
                                        <div style=\"border-left:1px solid #e5e7eb\">{$cell('Whatnot Net', $f($net))}</div>
                                        <div style=\"border-left:1px solid #e5e7eb\">{$cell('Tips', $f($tips), '#059669')}</div>
                                    </div>
                                    <div style=\"display:grid;grid-template-columns:repeat(3,1fr);border-bottom:1px solid #e5e7eb;background:#fafafa\">
                                        {$cell('COGS', $f($cogs), '#dc2626')}
                                        <div style=\"border-left:1px solid #e5e7eb\">{$cell('Payouts', $f($payouts), '#d97706')}</div>
                                        <div style=\"border-left:1px solid #e5e7eb;padding:14px 18px\">
                                            <div style=\"font-size:11px;font-weight:600;color:#6b7280;text-transform:uppercase;letter-spacing:0.05em;margin-bottom:4px\">Net Margin</div>
                                            <div style=\"font-size:20px;font-weight:800;color:{$marginColor}\">{$f($margin)}</div>
                                            <div style=\"font-size:12px;font-weight:600;color:{$marginColor};margin-top:2px\">{$sign}{$pct}%</div>
                                        </div>
                                    </div>
                                </div>
                            ");
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
                    DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->isAdmin()),
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
