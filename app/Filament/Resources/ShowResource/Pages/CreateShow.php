<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\ShowResource;
use App\Jobs\NotifyShowReady;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryLocation;
use App\Models\Product;
use App\Models\Streamer;
use App\Models\WhatnotChannel;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
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
                    Grid::make(2)->schema([
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
                            ->nullable()
                            ->helperText('Leave blank to calculate from start/end times'),

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
                    $isStreamerOnly && $streamerRecord
                        ? Select::make('streamers')
                            ->label('Streamers')
                            ->multiple()
                            ->relationship('streamers', 'name')
                            ->default([$streamerRecord->id])
                            ->disabled()
                            ->dehydrated()
                            ->helperText("You ({$streamerRecord->name}) are automatically assigned.")
                        : Select::make('streamers')
                            ->label('Streamers')
                            ->multiple()
                            ->options(Streamer::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                            ->relationship('streamers', 'name')
                            ->preload()
                            ->searchable()
                            ->default($streamerRecord ? [$streamerRecord->id] : [])
                            ->helperText('Select one or more streamers for this show'),
                ]),

            Step::make('Items Sold')
                ->icon('heroicon-o-shopping-cart')
                ->description('Inventory and custom items sold during the show')
                ->schema([
                    Section::make('Inventory Items')
                        ->description('Products pulled from your inventory')
                        ->collapsible()
                        ->schema([
                            Repeater::make('inventory_items')
                                ->label(false)
                                ->schema([
                                    Grid::make(4)->schema([
                                        Select::make('product_id')
                                            ->label('Product')
                                            ->options(Product::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                                            ->searchable()
                                            ->required()
                                            ->columnSpan(2),

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
                                ->addActionLabel('Add inventory item')
                                ->defaultItems(0)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),

                    Section::make('Non-Inventory Items')
                        ->description('Custom or consignment items not tracked in inventory')
                        ->collapsible()
                        ->schema([
                            Repeater::make('custom_items')
                                ->label(false)
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('description')
                                            ->label('Description')
                                            ->required()
                                            ->placeholder('e.g. Consignment lot, Event passes')
                                            ->columnSpan(2),

                                        TextInput::make('amount')
                                            ->label('Amount ($)')
                                            ->numeric()
                                            ->prefix('$')
                                            ->nullable(),
                                    ]),
                                ])
                                ->addActionLabel('Add custom item')
                                ->defaultItems(0)
                                ->reorderable(false)
                                ->columnSpanFull(),
                        ]),

                    TextInput::make('units_sold')
                        ->label('Total Units Sold')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->helperText('Total number of orders/lots sold on Whatnot'),
                ]),

            Step::make('Financials')
                ->icon('heroicon-o-currency-dollar')
                ->description('Revenue and payout details')
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

                    Textarea::make('notes')
                        ->label('Notes')
                        ->rows(3)
                        ->columnSpanFull()
                        ->nullable(),
                ]),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['import_source'] = 'manual';
        $data['status']        = 'pending_review';
        $data['created_by']    = auth()->id();

        // Remove wizard-only fields that don't belong on the Show model
        unset($data['inventory_items'], $data['custom_items']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $show = $this->record;

        NotifyShowReady::dispatch($show->id);

        $formData      = $this->data;
        $inventoryRows = $formData['inventory_items'] ?? [];
        $customRows    = $formData['custom_items']    ?? [];

        // Create a DeductionRequest for inventory items
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
                    'deduction_request_id' => $request->id,
                    'inventory_item_id'    => $row['product_id'],
                    'inventory_location_id'=> $row['inventory_location_id'] ?? null,
                    'quantity_suggested'   => $row['quantity'] ?? 1,
                    'quantity_approved'    => $row['quantity'] ?? 1,
                    'unit_cost_snapshot'   => $row['unit_cost'] ?? 0,
                ]);
            }
        }

        // Append custom/non-inventory items to notes
        if (! empty($customRows)) {
            $lines = collect($customRows)
                ->filter(fn ($r) => ! empty($r['description']))
                ->map(fn ($r) => '- ' . $r['description'] . (isset($r['amount']) && $r['amount'] ? ' ($' . number_format((float)$r['amount'], 2) . ')' : ''))
                ->implode("\n");

            if ($lines) {
                $existing = $show->notes ? $show->notes . "\n\n" : '';
                $show->update(['notes' => $existing . "Non-inventory items sold:\n" . $lines]);
            }
        }
    }
}
