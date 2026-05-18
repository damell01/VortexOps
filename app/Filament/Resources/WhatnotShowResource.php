<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WhatnotShowResource\Pages;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use App\Models\WhatnotShow;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Services\OllamaService;
use App\Services\PayoutService;
use App\Services\ReconciliationService;
use App\Services\ShowService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Actions\ViewAction;

class WhatnotShowResource extends Resource
{
    protected static ?string $model = WhatnotShow::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-video-camera';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Stream Tracking';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
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
            'Date'   => $record->show_date?->format('M j, Y'),
            'Status' => \App\Models\WhatnotShow::statusLabels()[$record->status] ?? $record->status,
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

                Select::make('source')
                    ->options(WhatnotShow::sourceLabels())
                    ->default('manual')
                    ->required(),

                DateTimePicker::make('started_at')
                    ->label('Stream Start')
                    ->nullable(),

                DateTimePicker::make('ended_at')
                    ->label('Stream End')
                    ->nullable(),

                Select::make('streamers')
                    ->label('Streamers')
                    ->multiple()
                    ->options(Streamer::where('status', 'active')->pluck('name', 'id'))
                    ->relationship('streamers', 'name')
                    ->preload()
                    ->columnSpanFull(),

                Textarea::make('notes')
                    ->rows(2)
                    ->columnSpanFull(),
            ]),

            Section::make('Sales — Line Items')
                ->description('Enter each item sold during the show. You can run AI matching after saving to link items to inventory.')
                ->schema([
                    Repeater::make('sales')
                        ->relationship('sales')
                        ->schema([
                            Grid::make(4)->schema([
                                TextInput::make('item_name')
                                    ->required()
                                    ->placeholder('e.g. 2024 Topps Chrome Hobby')
                                    ->columnSpan(2),

                                TextInput::make('sku')
                                    ->label('SKU')
                                    ->placeholder('Optional')
                                    ->columnSpan(1),

                                Select::make('sale_type')
                                    ->options(\App\Models\ShowSale::saleTypeLabels())
                                    ->default('break_slot')
                                    ->required()
                                    ->columnSpan(1),
                            ]),

                            Grid::make(4)->schema([
                                TextInput::make('quantity')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0.01)
                                    ->required(),

                                TextInput::make('sale_price')
                                    ->numeric()
                                    ->prefix('$')
                                    ->required()
                                    ->minValue(0),

                                TextInput::make('buyer_username')
                                    ->label('Buyer (Whatnot username)')
                                    ->placeholder('Optional'),

                                TextInput::make('order_id')
                                    ->label('Order ID')
                                    ->placeholder('Optional'),
                            ]),

                            Select::make('inventory_item_id')
                                ->label('Matched Inventory Item')
                                ->options(InventoryItem::where('is_active', true)->pluck('name', 'id'))
                                ->searchable()
                                ->nullable()
                                ->helperText('Link to inventory for deduction tracking. Use AI Match on the view page to auto-suggest.'),
                        ])
                        ->addActionLabel('Add Sale')
                        ->collapsible()
                        ->cloneable()
                        ->columns(1),
                ]),

            Section::make('Financials')->columns(3)->schema([
                TextInput::make('financial.gross_sales')
                    ->label('Gross Sales')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                TextInput::make('financial.platform_fee_pct')
                    ->label('Whatnot Platform Fee %')
                    ->numeric()
                    ->suffix('%')
                    ->default(8),

                TextInput::make('financial.shipping_collected')
                    ->label('Shipping Collected')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                TextInput::make('financial.tips_collected')
                    ->label('Tips Collected')
                    ->numeric()
                    ->prefix('$')
                    ->default(0),

                TextInput::make('financial.owner_platform_fee_pct')
                    ->label('Owner Fee % (from streamers)')
                    ->numeric()
                    ->suffix('%')
                    ->default(0)
                    ->helperText('% of net revenue the owner retains before streamer payout'),

                TextInput::make('financial.cogs')
                    ->label('Cost of Goods (COGS)')
                    ->numeric()
                    ->prefix('$')
                    ->default(0)
                    ->helperText('Auto-filled when deductions are executed'),
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

                TextColumn::make('streamers.name')
                    ->label('Streamers')
                    ->badge()
                    ->separator(', '),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => WhatnotShow::statusLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'draft'                  => 'gray',
                        'pending_reconciliation' => 'warning',
                        'reconciling'            => 'info',
                        'reconciled'             => 'success',
                        'paid'                   => 'success',
                        default                  => 'gray',
                    }),

                TextColumn::make('source')
                    ->badge()
                    ->formatStateUsing(fn ($state) => WhatnotShow::sourceLabels()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'manual'     => 'gray',
                        'csv_import' => 'info',
                        'scraper'    => 'success',
                        default      => 'gray',
                    }),

                TextColumn::make('financial.gross_sales')
                    ->label('Gross Sales')
                    ->money('USD')
                    ->default('—'),

                TextColumn::make('sales_count')
                    ->counts('sales')
                    ->label('Sales'),

                TextColumn::make('deduction_requests_count')
                    ->counts('deductionRequests')
                    ->label('Deductions'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('show_date', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(WhatnotShow::statusLabels()),
                SelectFilter::make('source')
                    ->options(WhatnotShow::sourceLabels()),
            ])
            ->actions([
                ViewAction::make()->iconButton(),
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListWhatnotShows::route('/'),
            'create' => Pages\CreateWhatnotShow::route('/create'),
            'view'   => Pages\ViewWhatnotShow::route('/{record}'),
            'edit'   => Pages\EditWhatnotShow::route('/{record}/edit'),
        ];
    }
}
