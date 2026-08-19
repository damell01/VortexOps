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

    /** Set once the relations have been pulled in, so they are pulled once. */
    private bool $relationsLoaded = false;

    public function getRecord(): \App\Models\Pallet
    {
        $record = parent::getRecord();

        // Filament asks for the record constantly — every action's visible(),
        // label() and modal callback, plus the view itself. Loading on each
        // call re-ran all six relationship queries every time, which is how
        // one pallet page issued nearly five hundred of them.
        //
        // loadCount as well as load: the view gates its attachments block on
        // attachments_count, which load() never sets — so the block stayed
        // hidden even when the pallet had files.
        if (! $this->relationsLoaded) {
            $record->load(['vendor', 'lines.inventoryItem', 'lines.location', 'lines.cases', 'attachments'])
                ->loadCount('attachments');

            $this->relationsLoaded = true;
        }

        return $record;
    }

    /**
     * Pull the relations again after something has changed them.
     *
     * Every action that writes calls $this->record->refresh(), which empties
     * the loaded relations — so the flag has to drop with them or the page
     * renders against a stale object.
     */
    public function refreshLoadedRelations(): void
    {
        $this->relationsLoaded = false;
    }

    public function getView(): string
    {
        return 'filament.pages.view-pallet';
    }

    /**
     * Scan a staged line into inventory, fired from its own row.
     *
     * Its own method rather than a header action: returned from
     * getHeaderActions() it rendered as a header button too — a control with
     * no line to act on, sitting beside the ones that have one. Filament
     * resolves mountAction('linkLine') to this, so the row can reach it and
     * the header cannot.
     *
     * Standing at the pallet you already know which box you are holding, so
     * picking it out of a dropdown a second time is the step worth removing.
     */
    public function linkLineAction(): Action
    {
        return Action::make('linkLine')
                ->modalHeading(fn (array $arguments) => 'Scan ' . ($this->lineFor($arguments)?->description ?? 'this item'))
                ->modalDescription('An unknown code becomes a new inventory item, named as staged.')
                ->modalSubmitActionLabel('Link')
                ->schema(fn (array $arguments) => [
                    \Filament\Forms\Components\TextInput::make('code')
                        ->label('Barcode / UPC')
                        ->required()
                        ->autofocus()
                        ->placeholder('Scan or type…')
                        // Carried over when a camera scan could not finish on
                        // its own — the code is already known, so it is not
                        // asked for twice.
                        ->default($arguments['code'] ?? null),
                    // A handheld scanner types into the field above; a phone
                    // needs the camera, and this is the screen being used on
                    // a phone at the pallet.
                    \Filament\Schemas\Components\View::make('filament.components.scan-capture-button'),
                    Select::make('inventory_location_id')
                        ->label('Receive Into')
                        ->required()
                        ->searchable()
                        ->options(fn () => InventoryLocation::activeOptions())
                        // Whatever the line already carries, else wherever the
                        // rest of this pallet is going, else the only location
                        // there is. Asking per line is the same answer typed
                        // over and over while holding a box.
                        ->default(fn () => $this->defaultReceivingLocationId($arguments)),
                    \Filament\Forms\Components\Checkbox::make('confirm_case')
                        ->label('Count one case as received')
                        ->helperText('You are holding it, so this is normally right.')
                        ->default(true),
                ])
                ->action(function (array $arguments, array $data) {
                    $line = $this->lineFor($arguments);

                    if (! $line) {
                        Notification::make()->title('That line is no longer on this pallet')->danger()->send();

                        return;
                    }

                    try {
                        $result = app(ReceivingService::class)->linkLineByScan(
                            $line,
                            $data['code'],
                            InventoryLocation::findOrFail($data['inventory_location_id']),
                        );

                        $body = $result['created']
                            ? 'Added to inventory with that barcode.'
                            : 'Matched an item already in inventory.';

                        if ($data['confirm_case'] ?? false) {
                            $count  = app(ReceivingService::class)->confirmOneCase($result['line']);
                            $body  .= " {$count['received']} of {$count['expected']} cases in.";
                        }

                        Notification::make()
                            ->title($result['item']->name . ($result['created'] ? ' created and linked' : ' linked'))
                            ->body($body)
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                    $this->refreshLoadedRelations();
                });
    }

    /**
     * A camera scan, tied straight to the line it was fired from.
     *
     * The whole interaction is: press the button on the row, point the camera,
     * done. No form, because by the time the code is read every question the
     * form was asking has an answer — the line came from the button, the code
     * came from the scan, and the location is the one this pallet is being
     * unloaded into.
     *
     * The location is the only one that can genuinely be unknown, and when it
     * is, this hands over to the modal with the code already filled in rather
     * than failing on the last step of a scan that worked.
     */
    public function scanLineIntoInventory(int $lineId, string $code): void
    {
        $line = $this->lineFor(['line' => $lineId]);

        if (! $line) {
            Notification::make()->title('That line is no longer on this pallet')->danger()->send();

            return;
        }

        $locationId = $this->defaultReceivingLocationId(['line' => $lineId]);

        if (! $locationId) {
            $this->mountAction('linkLine', ['line' => $lineId, 'code' => $code]);

            return;
        }

        try {
            $result = app(ReceivingService::class)->linkLineByScan(
                $line,
                $code,
                InventoryLocation::findOrFail($locationId),
            );

            $count = app(ReceivingService::class)->confirmOneCase($result['line']);

            Notification::make()
                ->title($result['item']->name . ($result['created'] ? ' created and linked' : ' linked'))
                ->body(($result['created'] ? 'Added to inventory with that barcode. ' : '')
                    . "{$count['received']} of {$count['expected']} cases in.")
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->record->refresh();
                    $this->refreshLoadedRelations();
    }

    /** Already linked, and another one of it just came off the pallet. */
    public function confirmCaseAction(): Action
    {
        return Action::make('confirmCase')
                ->requiresConfirmation()
                ->modalHeading(fn (array $arguments) => 'Count one case of ' . ($this->lineFor($arguments)?->description ?? 'this item'))
                ->modalDescription('Credits one case to stock. Nothing else changes.')
                ->modalSubmitActionLabel('Count it')
                ->action(function (array $arguments) {
                    $line = $this->lineFor($arguments);

                    if (! $line) {
                        Notification::make()->title('That line is no longer on this pallet')->danger()->send();

                        return;
                    }

                    try {
                        $result = app(ReceivingService::class)->confirmOneCase($line);

                        Notification::make()
                            ->title($line->description . ' — ' . $result['received'] . ' of ' . $result['expected'])
                            ->body($result['complete'] ? 'That line is complete.' : 'Click again for the next case.')
                            ->success()
                            ->send();
                    } catch (\RuntimeException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }

                    $this->record->refresh();
                    $this->refreshLoadedRelations();
                });
    }

    /**
     * The line a row-level action was fired for.
     *
     * Resolved through this pallet's own lines rather than by id alone, so a
     * stale page cannot act on a line belonging to a different pallet.
     */
    private function lineFor(array $arguments): ?PalletLine
    {
        $id = $arguments['line'] ?? null;

        return $id ? $this->getRecord()->lines->firstWhere('id', (int) $id) : null;
    }

    /**
     * Where a scanned line should land, guessed well enough not to be asked.
     *
     * In order: what the line already says, where the rest of the pallet is
     * going, and — when the site only has one place to put things — that one.
     * A pallet is usually unloaded into a single location, so the honest
     * default is the answer already given a moment ago.
     */
    private function defaultReceivingLocationId(array $arguments): ?int
    {
        $onLine = $this->lineFor($arguments)?->inventory_location_id;

        if ($onLine) {
            return (int) $onLine;
        }

        $elsewhereOnPallet = $this->getRecord()->lines
            ->pluck('inventory_location_id')
            ->filter()
            ->first();

        if ($elsewhereOnPallet) {
            return (int) $elsewhereOnPallet;
        }

        return InventoryLocation::defaultReceivingId();
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
                    \Filament\Schemas\Components\View::make('filament.components.scan-capture-button'),
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
                    $this->refreshLoadedRelations();
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
                    $this->refreshLoadedRelations();
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
                    $this->refreshLoadedRelations();
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
                    $this->refreshLoadedRelations();
                })
                ->visible(fn () => ! in_array($this->getRecord()->status, ['received', 'processed'])),

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
                        // Pre-filled from the staging location set in Settings,
                        // so a line staged by name is one scan away from being
                        // receivable rather than two.
                        ->default(fn () => InventoryLocation::defaultReceivingId())
                        ->helperText('Optional — defaults to your receiving location.'),
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
                    $this->refreshLoadedRelations();
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
                    $this->refreshLoadedRelations();
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
                    $this->refreshLoadedRelations();
                        } catch (\RuntimeException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    })
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
                    $this->refreshLoadedRelations();
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
                    $this->refreshLoadedRelations();
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
