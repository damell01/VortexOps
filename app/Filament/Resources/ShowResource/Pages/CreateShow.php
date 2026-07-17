<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\NotifyShowReady;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\HasWizard;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Wizard\Step;

class CreateShow extends CreateRecord
{
    use HasWizard;

    protected static string $resource = ShowResource::class;

    public function getSteps(): array
    {
        $user           = auth()->user();
        $isStreamerOnly = $user?->isStreamer() && ! $user?->isAdmin() && ! $user?->isOwner();
        $streamerRecord = $user?->streamer;

        return [
            Step::make('Show Details')
                ->icon('heroicon-o-video-camera')
                ->description('Basic information about the stream')
                ->schema([
                    Grid::make(3)->schema([
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

                        TextInput::make('show_duration')
                            ->label('Duration (minutes)')
                            ->numeric()
                            ->nullable(),

                        TimePicker::make('start_time')
                            ->label('Start Time')
                            ->nullable(),

                        TimePicker::make('end_time')
                            ->label('End Time')
                            ->nullable(),
                    ]),
                ]),

            Step::make('Streamers')
                ->icon('heroicon-o-users')
                ->description('Who streamed this show')
                ->schema([
                    Select::make('streamers')
                        ->label('Streamers')
                        ->multiple()
                        ->options(Streamer::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                        ->relationship('streamers', 'name')
                        ->preload()
                        ->searchable()
                        ->default($streamerRecord ? [$streamerRecord->id] : [])
                        ->disabled($isStreamerOnly && (bool) $streamerRecord)
                        ->dehydrated()
                        ->helperText(
                            $isStreamerOnly && $streamerRecord
                                ? "You ({$streamerRecord->name}) are automatically assigned."
                                : 'Select one or more streamers for this show'
                        ),
                ]),

            Step::make('Items Sold')
                ->icon('heroicon-o-shopping-cart')
                ->description('Inventory items sold during the show')
                ->schema([
                    Repeater::make('inventory_items')
                        ->label('Inventory Items')
                        ->schema([
                            Grid::make(4)->schema([
                                Select::make('product_id')
                                    ->label('Product')
                                    ->options(Product::where('is_active', true)->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(2)
                                    ->live()
                                    ->afterStateUpdated(function (?int $state, \Filament\Forms\Set $set) {
                                        if (! $state) {
                                            return;
                                        }
                                        $product = Product::find($state);
                                        if ($product) {
                                            $cost = $product->effectiveCost();
                                            if ($cost > 0) {
                                                $set('unit_cost', number_format($cost, 2, '.', ''));
                                            }
                                        }
                                    }),

                                Select::make('inventory_location_id')
                                    ->label('Location')
                                    ->options(InventoryLocation::activeOptions())
                                    ->searchable()
                                    ->nullable(),

                                TextInput::make('quantity')
                                    ->label('Qty')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(1)
                                    ->required(),

                                TextInput::make('unit_cost')
                                    ->label('Unit Cost')
                                    ->numeric()
                                    ->prefix('$')
                                    ->nullable(),
                            ]),
                        ])
                        ->addActionLabel('Add item')
                        ->defaultItems(0)
                        ->reorderable(false)
                        ->columnSpanFull(),

                    TextInput::make('units_sold')
                        ->label('Total Units Sold')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Total number of orders/lots sold on Whatnot'),
                ]),

            Step::make('Financials')
                ->icon('heroicon-o-currency-dollar')
                ->description('Revenue and paper sales details')
                ->schema([
                    Grid::make(3)->schema([
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

                    Grid::make(3)->schema([
                        TextInput::make('paper_sales_gross')
                            ->label('Paper Sales Gross')
                            ->numeric()
                            ->prefix('$')
                            ->nullable()
                            ->helperText('Streamer\'s own paper tracking (not Whatnot).'),

                        TextInput::make('paper_sales_units')
                            ->label('Paper Sales Units')
                            ->numeric()
                            ->nullable(),

                        Toggle::make('sales_reconciled')
                            ->label('Sales Reconciled')
                            ->helperText('Whatnot totals and paper sheet have been compared.')
                            ->inline(false),
                    ]),

                    Textarea::make('paper_sales_notes')
                        ->label('Paper Sales Notes')
                        ->rows(2)
                        ->nullable()
                        ->columnSpanFull(),

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->nullable(),

                    Select::make('status')
                        ->label('Initial Status')
                        ->options(Show::statusLabels())
                        ->default('pending_review')
                        ->visible(fn () => auth()->user()?->isAdmin())
                        ->helperText('Choose Draft to save without triggering notifications yet.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['import_source'] = 'manual';
        $data['status']        = $data['status'] ?? 'pending_review';
        $data['created_by']    = auth()->id();

        unset($data['inventory_items']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $show = $this->record;

        if ($show->status !== 'draft') {
            NotifyShowReady::dispatch($show->id);
        }

        $inventoryRows = $this->data['inventory_items'] ?? [];

        if (! empty($inventoryRows)) {
            $request = DeductionRequest::create([
                'show_id' => $show->id,
                'status'  => 'pending',
            ]);

            foreach ($inventoryRows as $row) {
                if (empty($row['product_id'])) {
                    continue;
                }
                DeductionRequestLine::create([
                    'deduction_request_id'  => $request->id,
                    'inventory_item_id'     => $row['product_id'],
                    'inventory_location_id' => $row['inventory_location_id'] ?? null,
                    'quantity_suggested'    => $row['quantity'] ?? 1,
                    'quantity_approved'     => $row['quantity'] ?? 1,
                    'unit_cost_snapshot'    => $row['unit_cost'] ?? 0,
                ]);
            }
        }
    }
}
