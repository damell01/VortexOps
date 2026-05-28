<?php

namespace App\Filament\Resources\ShowResource\Pages;

use App\Filament\Resources\DeductionRequestResource;
use App\Filament\Resources\ShowResource;
use App\Models\DeductionRequest;
use App\Models\DeductionRequestLine;
use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Jobs\MapShowInventory;
use App\Jobs\ParseShowTitle;
use App\Services\ShowSalesWorksheetService;
use App\Support\ShowSalesFormHelper;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewShow extends ViewRecord
{
    protected static string $resource = ShowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('enter_sold_items')
                ->label('Enter Sold Items')
                ->icon('heroicon-o-list-bullet')
                ->color('warning')
                ->visible(fn () => in_array($this->record->status, ['pending_review', 'pending_approval', 'mapping']))
                ->fillForm(fn (): array => $this->manualSalesFormDefaults())
                ->form([
                    Select::make('streamer_id')
                        ->label('Review Against Streamer')
                        ->helperText('Choose which streamer inventory or sales worksheet this review packet belongs to.')
                        ->options(fn (): array => $this->streamerOptions())
                        ->required()
                        ->native(false),

                    Repeater::make('lines')
                        ->label('Sold Items')
                        ->defaultItems(1)
                        ->reorderable(false)
                        ->addActionLabel('Add Sold Item')
                        ->schema([
                            Select::make('inventory_item_id')
                                ->label('Inventory Item')
                                ->options(fn (): array => ShowSalesFormHelper::inventoryItemOptions())
                                ->searchable()
                                ->preload()
                                ->required()
                                ->native(false)
                                ->live()
                                ->helperText(fn (Get $get): string => ShowSalesFormHelper::stockSummaryForItem(
                                    $get('inventory_item_id') ? (int) $get('inventory_item_id') : null
                                ))
                                ->afterStateUpdated(function (?string $state, Set $set): void {
                                    if (! $state) {
                                        return;
                                    }

                                    $item = InventoryItem::find($state);

                                    if (! $item) {
                                        return;
                                    }

                                    $unitCost = (float) ($item->average_unit_cost ?: $item->landed_unit_cost ?: $item->unit_cost);

                                    $set('unit_cost_snapshot', number_format($unitCost, 2, '.', ''));
                                    $set('raw_description', $item->name);
                                    $set('inventory_location_id', ShowSalesFormHelper::bestLocationIdForItem((int) $state));
                                }),

                            Select::make('inventory_location_id')
                                ->label('Location')
                                ->options(fn (Get $get): array => ShowSalesFormHelper::locationOptionsForItem(
                                    $get('inventory_item_id') ? (int) $get('inventory_item_id') : null
                                ))
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->required()
                                ->helperText(fn (Get $get): string => ShowSalesFormHelper::bestLocationHintForItem(
                                    $get('inventory_item_id') ? (int) $get('inventory_item_id') : null
                                )),

                            TextInput::make('quantity_approved')
                                ->label('Qty Sold')
                                ->numeric()
                                ->minValue(0.01)
                                ->step(0.01)
                                ->required(),

                            TextInput::make('unit_cost_snapshot')
                                ->label('Unit Cost')
                                ->numeric()
                                ->prefix('$')
                                ->minValue(0)
                                ->step(0.01)
                                ->required(),

                            TextInput::make('raw_description')
                                ->label('Show Recap Label')
                                ->placeholder('What the recap or ops note called this item')
                                ->maxLength(255),
                        ])
                        ->columns(5)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $request = $this->storeManualSoldItems($data);

                    Notification::make()
                        ->title('Sold items saved')
                        ->body('The show now has a pending approval packet with manually selected sold items.')
                        ->success()
                        ->send();

                    $this->redirect(DeductionRequestResource::getUrl('view', ['record' => $request->id]));
                }),

            Action::make('run_ai_title_parse')
                ->label('Re-parse Title')
                ->icon('heroicon-o-cpu-chip')
                ->color('gray')
                ->visible(fn () => $this->record->title !== null)
                ->action(function () {
                    ParseShowTitle::dispatch($this->record->id);
                    Notification::make()->title('Title parsing queued')->success()->send();
                }),

            Action::make('run_ai_mapping')
                ->label('Map Sales with AI')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->visible(fn () => $this->record->status === 'pending_review' && $this->record->streamers()->exists())
                ->requiresConfirmation()
                ->action(function () {
                    MapShowInventory::dispatch($this->record->id);
                    Notification::make()
                        ->title('AI Mapping queued')
                        ->body('We are mapping this show now. Ops will be notified when approval is ready.')
                        ->success()
                        ->send();
                }),

            Action::make('review_approval')
                ->label('Review Approval')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->visible(fn () => in_array($this->record->status, ['pending_approval', 'reconciled', 'closed']))
                ->url(fn () => DeductionRequestResource::getUrl('index', ['tableFilters[show_id][value]' => $this->record->id])),

            EditAction::make(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function manualSalesFormDefaults(): array
    {
        /** @var Show $show */
        $show = $this->record->loadMissing([
            'streamers:id,name',
            'latestDeductionRequest.lines',
        ]);

        $request = $show->latestDeductionRequest;
        $primaryStreamer = $show->primaryStreamer();

        return [
            'streamer_id' => $request?->streamer_id ?? $primaryStreamer?->id,
            'lines' => $request?->lines->map(function (DeductionRequestLine $line): array {
                return [
                    'inventory_item_id' => $line->inventory_item_id,
                    'inventory_location_id' => $line->inventory_location_id,
                    'quantity_approved' => (float) $line->quantity_approved,
                    'unit_cost_snapshot' => (float) $line->unit_cost_snapshot,
                    'raw_description' => $line->raw_description,
                ];
            })->values()->all() ?: [[
                'inventory_item_id' => null,
                'inventory_location_id' => null,
                'quantity_approved' => 1,
                'unit_cost_snapshot' => 0,
                'raw_description' => null,
            ]],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function streamerOptions(): array
    {
        /** @var Show $show */
        $show = $this->record->loadMissing('streamers:id,name');

        $streamers = $show->streamers->isNotEmpty()
            ? $show->streamers
            : Streamer::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']);

        return $streamers
            ->mapWithKeys(fn (Streamer $streamer): array => [$streamer->id => $streamer->name])
            ->all();
    }

    private function storeManualSoldItems(array $data): DeductionRequest
    {
        /** @var Show $show */
        $show = $this->record;

        return app(ShowSalesWorksheetService::class)->storeManualSoldItems(
            show: $show,
            streamerId: (int) $data['streamer_id'],
            lines: $data['lines'] ?? [],
        );
    }
}
