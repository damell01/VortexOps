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
            // Staging's own checkpoint. What is being confirmed here is that
            // the list matches the paperwork — before anything arrives and
            // before any of it touches stock — so the same review is shown
            // without the receive buttons.
            Action::make('review_staging')
                ->label('Review Manifest')
                ->icon('heroicon-o-clipboard-document-list')
                ->color('info')
                ->modalHeading('Does this match the paperwork?')
                ->modalDescription('Nothing here has arrived yet. Confirming marks the pallet ready to receive.')
                ->modalWidth('4xl')
                ->modalContent(fn () => view('filament.modals.pallet-review', [
                    'review'  => app(ReceivingService::class)->reviewPallet($this->getRecord()),
                    'staging' => true,
                ]))
                ->modalSubmitAction(fn ($action) => app(ReceivingService::class)
                    ->reviewPallet($this->getRecord())['can_finish']
                        ? $action->label('Mark ready to receive')
                        : false)
                ->action(function () {
                    $this->record->update([
                        'status'     => 'shipped',
                        'staged_at'  => $this->record->staged_at ?? now(),
                    ]);

                    Notification::make()
                        ->title('Manifest confirmed')
                        ->body('Scan items in as they arrive, then use Review & Receive.')
                        ->success()
                        ->send();

                    $this->record->refresh();
                })
                // Only while staging: once anything has been scanned the
                // receiving review is the one that matters.
                ->visible(fn () => in_array($this->getRecord()->status, ['pending', 'staged'])),

            // Receiving is where quantities and money become permanent, so it
            // gets a look first: what turned up against what was expected, what
            // is short, and what each item will be valued at afterwards.
            Action::make('review_and_receive')
                ->label('Review & Receive')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->modalHeading('Review this pallet')
                ->modalDescription('Nothing is committed until you confirm.')
                ->modalWidth('4xl')
                ->modalContent(fn () => view('filament.modals.pallet-review', [
                    'review' => app(ReceivingService::class)->reviewPallet($this->getRecord()),
                ]))
                ->modalSubmitAction(function ($action) {
                    $review = app(ReceivingService::class)->reviewPallet($this->getRecord());

                    // An action that cannot work is removed rather than offered
                    // and then refused.
                    return $review['can_finish']
                        ? $action->label('Receive all ' . number_format($review['totals']['expected_units']) . ' units')
                        : false;
                })
                // Only when something is missing: with a complete delivery the
                // two buttons would do the same thing and the choice is noise.
                ->extraModalFooterActions(fn () => app(ReceivingService::class)
                    ->reviewPallet($this->getRecord())['totals']['short_units'] > 0
                        ? [
                            Action::make('close_short')
                                ->label('Close short')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->modalHeading('Close this pallet short?')
                                ->modalDescription('Only what was scanned is kept. The rest stays outstanding and is not credited to stock.')
                                ->action(function () {
                                    $result = app(ReceivingService::class)->closePalletShort($this->getRecord());

                                    Notification::make()
                                        ->title("Closed with {$result['received_cases']} cases received")
                                        ->body("{$result['outstanding_cases']} cases were never scanned and have not been credited.")
                                        ->warning()
                                        ->send();

                                    $this->record->refresh();
                                })
                                ->cancelParentActions(),
                        ]
                        : [])
                ->action(function () {
                    try {
                        // Takes in every expected case, scanned or not — the
                        // "delivery was complete, scanning was a spot check"
                        // path. Close short is the other one.
                        $result = app(ReceivingService::class)->receivePallet($this->getRecord());

                        Notification::make()
                            ->title("Received {$result['cases_received']} cases across {$result['lines_processed']} lines")
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                })
                ->visible(fn () => ! in_array($this->getRecord()->status, ['received', 'processed'])),

            // The other end of staging by name: the box is here, its barcode
            // settles what it is. Picking the line first means an unrecognised
            // code is not an error — it is a new product, named from what was
            // staged, created and mapped in one step.
            Action::make('link_by_scan')
                ->label('Link by Scan')
                ->icon('heroicon-o-link')
                ->color('warning')
                ->modalHeading('Link a staged item to inventory')
                ->modalDescription('Pick the line, then scan it. An unknown code becomes a new item.')
                ->modalSubmitActionLabel('Link')
                ->schema([
                    Select::make('pallet_line_id')
                        ->label('Staged item')
                        ->required()
                        ->options(fn () => $this->getRecord()->lines
                            ->whereNull('inventory_item_id')
                            ->mapWithKeys(fn (PalletLine $l) => [$l->id => $l->description])
                            ->toArray())
                        ->helperText('Only items not yet linked to inventory appear here.'),
                    \Filament\Forms\Components\TextInput::make('code')
                        ->label('Barcode / UPC')
                        ->required()
                        ->placeholder('Scan or type…'),
                    Select::make('inventory_location_id')
                        ->label('Receive Into')
                        ->required()
                        ->searchable()
                        ->options(fn () => InventoryLocation::activeOptions()),
                ])
                ->action(function (array $data) {
                    try {
                        $line = $this->getRecord()->lines()->findOrFail($data['pallet_line_id']);

                        $result = app(ReceivingService::class)->linkLineByScan(
                            $line,
                            $data['code'],
                            InventoryLocation::findOrFail($data['inventory_location_id']),
                        );

                        Notification::make()
                            ->title($result['item']->name . ($result['created'] ? ' created and linked' : ' linked'))
                            ->body($result['created']
                                ? 'A new inventory item was added with that barcode.'
                                : 'Matched an item already in inventory.')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                })
                // Only worth offering while something is still unlinked.
                ->visible(fn () => ! in_array($this->getRecord()->status, ['received', 'processed'])
                    && $this->getRecord()->lines->whereNull('inventory_item_id')->isNotEmpty()),

            // The other half: build the list before the pallet lands, by name
            // rather than by barcode, since a staged item has not arrived to be
            // scanned yet.
            Action::make('add_expected_item')
                ->label('Add Expected Item')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->modalHeading('Add an item to this pallet')
                ->modalDescription('Only the name is needed. Everything else can wait until it arrives.')
                ->modalSubmitActionLabel('Add')
                ->schema([
                    // Staging happens off the paperwork, for things that often
                    // do not exist in inventory yet, so a free-text name is the
                    // whole requirement. Demanding an existing product here
                    // meant creating the product first, which is the opposite
                    // order to how a pallet is actually written down.
                    \Filament\Forms\Components\TextInput::make('name')
                        ->label('Item')
                        ->required()
                        ->autofocus()
                        ->maxLength(255)
                        ->placeholder('e.g. 2026 Topps Chrome Hobby')
                        ->helperText('Type what it is. It does not have to exist in inventory.'),
                    \Filament\Forms\Components\Radio::make('form_factor')
                        ->label('Case or single?')
                        ->options([
                            'unknown'   => 'Not sure yet',
                            'container' => 'Case / box holding several',
                            'single'    => 'A single item',
                        ])
                        ->default('unknown')
                        ->inline()
                        ->inlineLabel(false),
                    Select::make('inventory_item_id')
                        ->label('Link to an existing item')
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
                        ->helperText('Optional — or leave it and scan the barcode when the pallet lands.')
                        // Typing the name and then picking the same item from
                        // the list is the same fact entered twice.
                        ->live()
                        ->afterStateUpdated(function ($state, callable $get, callable $set) {
                            if ($state && blank($get('name'))) {
                                $set('name', InventoryItem::find($state)?->name);
                            }
                        }),
                    Select::make('inventory_location_id')
                        ->label('Receive Into')
                        ->searchable()
                        ->options(fn () => InventoryLocation::activeOptions())
                        ->helperText('Optional at this stage.'),
                    \Filament\Forms\Components\TextInput::make('case_count')
                        ->label('Cases Expected')
                        ->numeric()->minValue(1)->default(1),
                    \Filament\Forms\Components\TextInput::make('quantity_per_case')
                        ->label('Units per Case')
                        ->numeric()->minValue(1)->default(1),
                    \Filament\Forms\Components\TextInput::make('unit_cost')
                        ->label('Unit Cost')
                        ->numeric()->minValue(0)->prefix('$'),
                ])
                ->action(function (array $data) {
                    $item = ! empty($data['inventory_item_id'])
                        ? InventoryItem::find($data['inventory_item_id'])
                        : null;

                    $name = trim((string) ($data['name'] ?? '')) ?: $item?->name;

                    $line = PalletLine::create([
                        'pallet_id'             => $this->record->id,
                        'line_number'           => ((int) $this->record->lines()->max('line_number')) + 1,
                        'description'           => $name,
                        'is_container'          => match ($data['form_factor'] ?? 'unknown') {
                            'container' => true,
                            'single'    => false,
                            default     => null,
                        },
                        'inventory_item_id'     => $item?->id,
                        'inventory_location_id' => $data['inventory_location_id'] ?: null,
                        'case_count'            => (int) ($data['case_count'] ?: 1),
                        'quantity_per_case'     => (float) ($data['quantity_per_case'] ?: 1),
                        'unit_cost'             => (float) ($data['unit_cost'] ?: 0),
                        'line_status'           => 'pending',
                    ]);

                    // Stubs up front so the line can be scanned against
                    // immediately rather than only at bulk receive.
                    app(ReceivingService::class)->generateExpectedCases($line);

                    Notification::make()
                        ->title($name . ' added')
                        ->body($item
                            ? 'Ready to scan when it arrives.'
                            : 'Not linked to inventory yet — scan its barcode on arrival to link or create it.')
                        ->success()
                        ->send();

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
                        ->disk(\App\Services\PalletAttachmentService::DISK)

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
                // Kept as its own page because it is a scanning station: a live
                // camera viewfinder and a running tally, meant to be left open
                // on a phone at the pallet. The Scan Item modal above is the
                // one-off equivalent, not a replacement.
                Action::make('receive')
                    ->label('Open Scanning Station')
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
                // A vendor who sends a spreadsheet needs no AI to read it, and
                // staging a hundred lines by hand is not a workflow. (The
                // packing-slip reader that used to sit here is parked — the
                // page still exists, nothing links to it.)
                Action::make('import_csv')
                    ->label('Import Manifest CSV')
                    ->icon('heroicon-o-table-cells')
                    ->color('violet')
                    ->modalHeading('Import manifest lines')
                    ->modalDescription('One row per line. Columns: description, case_count, quantity_per_case, unit_cost.')
                    ->modalSubmitActionLabel('Import')
                    ->schema([
                        \Filament\Forms\Components\FileUpload::make('csv')
                            ->label('CSV file')
                            ->required()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'])
                            ->helperText('Lines land unmapped — match them to items afterwards.'),
                    ])
                    ->action(function (array $data) {
                        // storeFiles(false) hands back the uploaded file itself,
                        // so nothing is written to disk for an import that is
                        // read once and discarded.
                        $file = is_array($data['csv']) ? reset($data['csv']) : $data['csv'];

                        if (! $file instanceof \Illuminate\Http\UploadedFile) {
                            Notification::make()->title('No file was uploaded')->danger()->send();

                            return;
                        }

                        try {
                            $result = app(ReceivingService::class)
                                ->importManifestCsv($this->record, $file->getRealPath());
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Could not read that file')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($result['imported'] . ' ' . \Illuminate\Support\Str::plural('line', $result['imported']) . ' imported')
                            ->body($result['skipped'] > 0
                                ? $result['skipped'] . ' ' . \Illuminate\Support\Str::plural('row', $result['skipped']) . ' had no description and ' . ($result['skipped'] === 1 ? 'was' : 'were') . ' skipped.'
                                : 'Map them to inventory items, then review the manifest.')
                            ->success()
                            ->send();

                        $this->record->refresh();
                    })
                    ->visible(fn () => ! in_array($this->getRecord()->status, ['received', 'processed'])),
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
