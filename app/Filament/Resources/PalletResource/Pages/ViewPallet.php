<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletAttachment;
use App\Models\PalletLine;
use App\Services\ReceivingService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewPallet extends ViewRecord
{
    protected static string $resource = PalletResource::class;

    public ?array $newAttachments = null;

    public function getRecord(): \App\Models\Pallet
    {
        // loadCount as well as load: the view gates its attachments block on
        // attachments_count, which load() never sets — so the block stayed
        // hidden even when the pallet had files.
        return parent::getRecord()
            ->load(['vendor', 'lines.inventoryItem', 'lines.location', 'lines.cases', 'attachments'])
            ->loadCount('attachments');
    }

    public function getView(): string
    {
        return 'filament.pages.view-pallet';
    }

    protected function getHeaderActions(): array
    {
        return [
            // Scanning confirms one case at a time against what was staged, so
            // a part-delivered pallet is describable rather than all-or-nothing.
            Action::make('scan_item')
                ->label('Scan Item')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->modalHeading('Confirm an item off this pallet')
                ->modalDescription('Scan or type the barcode, UPC or SKU. Each scan confirms one case.')
                ->modalSubmitActionLabel('Confirm')
                ->schema([
                    \Filament\Forms\Components\TextInput::make('code')
                        ->label('Barcode / UPC / SKU')
                        ->required()
                        ->autofocus()
                        ->placeholder('Scan or type…'),
                ])
                ->action(function (array $data) {
                    try {
                        $result = app(ReceivingService::class)
                            ->receiveOneCaseByItemCode($this->record, $data['code']);

                        Notification::make()
                            ->title($result['item'] . ' — ' . $result['received'] . ' of ' . $result['expected'])
                            ->body($result['complete'] ? 'That line is complete.' : 'Scan again for the next case.')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        // A code that is not on the pallet is the ordinary way
                        // an unexpected item shows up, so say what to do next
                        // rather than only refusing.
                        Notification::make()
                            ->title($e->getMessage())
                            ->body('If it really is on this pallet, add it with "Add Expected Item" first.')
                            ->danger()
                            ->send();
                    }

                    $this->record->refresh();
                })
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving', 'staged'])),
            // The other half: build the list before the pallet lands, by name
            // rather than by barcode, since a staged item has not arrived to be
            // scanned yet.
            Action::make('add_expected_item')
                ->label('Add Expected Item')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->modalHeading('Add an item to this pallet')
                ->modalSubmitActionLabel('Add')
                ->schema([
                    Select::make('inventory_item_id')
                        ->label('Item')
                        ->required()
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => InventoryItem::where('is_active', true)
                            ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%")
                                ->orWhere('barcode', $search))
                            ->orderBy('name')
                            ->limit(30)
                            ->pluck('name', 'id')
                            ->toArray())
                        ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name)
                        ->helperText('Type a name, SKU or barcode.'),
                    Select::make('inventory_location_id')
                        ->label('Receive Into')
                        ->required()
                        ->searchable()
                        ->options(fn () => InventoryLocation::activeOptions()),
                    \Filament\Forms\Components\TextInput::make('case_count')
                        ->label('Cases Expected')
                        ->numeric()->minValue(1)->default(1)->required(),
                    \Filament\Forms\Components\TextInput::make('quantity_per_case')
                        ->label('Units per Case')
                        ->numeric()->minValue(1)->default(1)->required(),
                    \Filament\Forms\Components\TextInput::make('unit_cost')
                        ->label('Unit Cost')
                        ->numeric()->minValue(0)->prefix('$')->default(0),
                ])
                ->action(function (array $data) {
                    $item = InventoryItem::findOrFail($data['inventory_item_id']);

                    $line = PalletLine::create([
                        'pallet_id'             => $this->record->id,
                        'line_number'           => ((int) $this->record->lines()->max('line_number')) + 1,
                        'description'           => $item->name,
                        'inventory_item_id'     => $item->id,
                        'inventory_location_id' => $data['inventory_location_id'],
                        'case_count'            => $data['case_count'],
                        'quantity_per_case'     => $data['quantity_per_case'],
                        'unit_cost'             => $data['unit_cost'] ?? 0,
                        'line_status'           => 'pending',
                    ]);

                    // Stubs up front so the line can be scanned against
                    // immediately rather than only at bulk receive.
                    app(ReceivingService::class)->generateExpectedCases($line);

                    Notification::make()->title($item->name . ' added to this pallet')->success()->send();

                    $this->record->refresh();
                })
                ->visible(fn () => ! in_array($this->getRecord()->status, ['received', 'processed'])),
            // Photos and paperwork are captured while standing at the pallet,
            // so they are taken here rather than through the edit form. That
            // round trip — leave the pallet, open Edit, upload, save, navigate
            // back — is not something anyone does holding a box.
            Action::make('add_attachments')
                ->label('Add Photos / Documents')
                ->icon('heroicon-o-camera')
                ->color('gray')
                ->modalHeading('Add to this pallet')
                ->modalSubmitActionLabel('Upload')
                ->schema([
                    \Filament\Forms\Components\FileUpload::make('files')
                        ->label('Photos or documents')
                        ->multiple()
                        ->directory('pallets')
                        ->visibility('public')
                        ->maxSize(5120)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'application/pdf'])
                        ->helperText('Up to 5MB each. Use Take Photo below to capture one now.'),
                    \Filament\Schemas\Components\View::make('filament.components.photo-capture-button'),
                    \Filament\Forms\Components\TextInput::make('description')
                        ->label('Note (optional)')
                        ->maxLength(255)
                        ->placeholder('e.g. crushed corner on case 3'),
                ])
                ->action(function (array $data) {
                    $count = app(\App\Services\PalletAttachmentService::class)
                        ->attach($this->record, $data['files'] ?? [], $data['description'] ?? null);

                    if ($count === 0) {
                        Notification::make()->title('Nothing was uploaded')->warning()->send();

                        return;
                    }

                    Notification::make()
                        ->title($count . ' ' . \Illuminate\Support\Str::plural('file', $count) . ' added')
                        ->success()
                        ->send();

                    $this->record->refresh();
                }),

            // Nine buttons did not fit the header — the scan action, the one
            // most used while receiving, was cut off the right edge. The three
            // above are what a pallet is worked with; the rest are occasional.
            \Filament\Actions\ActionGroup::make([
                Action::make('receive')
                    ->label('Go to Receiving')
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->color('success')
                    ->url(fn () => PalletResource::getUrl('receive', ['record' => $this->getRecord()]))
                    ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving'])),
                Action::make('receive_all')
                    ->label('Bulk Receive All')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This will receive all mapped lines at once. Any unmapped lines will cause this to fail.')
                    ->action(function () {
                        try {
                            $result = app(ReceivingService::class)->receivePallet($this->getRecord());
                            Notification::make()
                                ->title("Pallet received — {$result['cases_received']} cases across {$result['lines_processed']} lines")
                                ->success()
                                ->send();
                            $this->record->refresh();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    })
                    ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving'])),
                Action::make('upload_manifest')
                    ->label('Upload Manifest')
                    ->icon('heroicon-o-document-arrow-up')
                    ->color('violet')
                    ->url(fn () => PalletResource::getUrl('import-manifest', ['record' => $this->getRecord()]))
                    ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'shipped', 'receiving'])),
                Action::make('map_line')
                    ->label('Map Line to Item')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->form([
                        Select::make('pallet_line_id')
                            ->label('Manifest Line')
                            ->options(fn () => $this->getRecord()->lines
                                ->mapWithKeys(fn ($l) => [$l->id => "Line {$l->line_number}: {$l->description}"])
                                ->toArray())
                            ->required()
                            ->searchable()
                            ->live(),
                        Select::make('inventory_item_id')
                            ->label('Inventory Item')
                            ->options(fn ($get) => InventoryItem::suggestForDescription(
                                \App\Models\PalletLine::find($get('pallet_line_id'))?->description ?? ''
                            ))
                            ->getSearchResultsUsing(fn (string $search) => InventoryItem::where('is_active', true)
                                ->where(fn ($q) => $q->where('name', 'like', "%{$search}%")
                                    ->orWhere('sku', 'like', "%{$search}%")
                                    ->orWhere('barcode', $search))
                                ->orderBy('name')
                                ->limit(30)
                                ->pluck('name', 'id')
                                ->toArray())
                            ->getOptionLabelUsing(fn ($value) => InventoryItem::find($value)?->name)
                            ->required()
                            ->searchable()
                            ->helperText('Suggestions based on previous show history. Type a name, SKU, or barcode to search all items.'),
                        Select::make('inventory_location_id')
                            ->label('Receive Into Location')
                            ->options(fn () => InventoryLocation::activeOptions())
                            ->required()
                            ->searchable(),
                    ])
                    ->action(function (array $data) {
                        $line     = PalletLine::where('id', $data['pallet_line_id'])
                            ->where('pallet_id', $this->record->id)
                            ->firstOrFail();
                        $item     = InventoryItem::findOrFail($data['inventory_item_id']);
                        $location = InventoryLocation::findOrFail($data['inventory_location_id']);
                        app(ReceivingService::class)->mapLine($line, $item, $location);
                        Notification::make()->title('Line mapped successfully')->success()->send();
                        $this->record->refresh();
                    }),
                // Shipping and payment fees decide what the stock ends up costing,
                // and they are usually known at the pallet rather than at the desk
                // the record was created from.
                Action::make('edit_costs')
                    ->label('Costs')
                    ->icon('heroicon-o-banknotes')
                    ->color('gray')
                    ->modalHeading('Costs for this pallet')
                    ->modalDescription('Shipping and fees are spread across the items by quantity when the pallet is received.')
                    ->fillForm(fn () => [
                        'shipping_cost' => $this->record->shipping_cost,
                        'payment_fees'  => $this->record->payment_fees,
                    ])
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('shipping_cost')
                            ->label('Shipping Cost')
                            ->numeric()->prefix('$')->minValue(0)->default(0),
                        \Filament\Forms\Components\TextInput::make('payment_fees')
                            ->label('Payment Fees')
                            ->numeric()->prefix('$')->minValue(0)->default(0)
                            ->helperText('Card, PayPal or wire charges.'),
                    ])
                    ->action(function (array $data) {
                        $this->record->update([
                            'shipping_cost' => $data['shipping_cost'] ?? 0,
                            'payment_fees'  => $data['payment_fees'] ?? 0,
                        ]);

                        Notification::make()
                            ->title('Costs updated')
                            ->body($this->record->status === 'received'
                                ? 'This pallet is already received, so the change does not re-cost the stock that came off it.'
                                : 'Spread across the items when this pallet is received.')
                            ->success()
                            ->send();

                        $this->record->refresh();
                    })
                    ->visible(fn () => auth()->user()?->isAdmin() || auth()->user()?->isOwner()),
                EditAction::make(),
            ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-vertical')
                ->button(),

        ];
    }
}
