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

    // Photographing a box only makes sense with the box in hand, which is here:
    // the pallet has landed and its lines are being scanned in.
    use \Livewire\WithFileUploads;

    public ?array $newAttachments = null;

    /**
     * Photos being attached to products, keyed by pallet line id.
     *
     * Keyed rather than a single property because the rows each have their own
     * button and someone working down a pallet will not wait for one upload to
     * finish before starting the next.
     *
     * @var array<int|string, mixed>
     */
    public array $linePhotos = [];

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

    /**
     * Photograph the item a line points at, with the box in front of you.
     *
     * Products get their picture from a catalogue or from nothing at all, and
     * for single-source break product there is often no catalogue to get one
     * from. The one moment a real photo is free is while the pallet is being
     * unloaded — so the button lives on the row, next to counting the case.
     *
     * Only once the line is linked, because until then there is no product for
     * the picture to belong to.
     *
     * Fires from wire:model="linePhotos.{id}" — Livewire calls this with the
     * upload and the array key as soon as the file lands.
     */
    public function updatedLinePhotos($value, $key): void
    {
        $line = $this->lineFor(['line' => (int) $key]);

        // Never left holding an upload for a row that has gone away, or one
        // already written to a product — the next photo would otherwise be
        // rejected as a duplicate key.
        unset($this->linePhotos[$key]);

        if (! $line?->inventoryItem) {
            Notification::make()->title('Link the line to an item first')->danger()->send();

            return;
        }

        if (! $value instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
            return;
        }

        $path = $value->store('product-images', InventoryItem::IMAGE_DISK);

        if (! $path) {
            Notification::make()->title('That photo could not be saved')->danger()->send();

            return;
        }

        $previous = $line->inventoryItem->image_path;

        $line->inventoryItem->update(['image_path' => $path]);

        // The old file is nothing's picture now, and leaving it behind fills the
        // disk with images no page will ever ask for.
        if ($previous && $previous !== $path) {
            Storage::disk(InventoryItem::IMAGE_DISK)->delete($previous);
        }

        Notification::make()
            ->title('Photo saved')
            ->body($line->inventoryItem->name . ' now has a picture of the real thing.')
            ->success()
            ->send();

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
            // Typing a slip is a columnar job — the same field down every line —
            // so it gets a table rather than the edit form's stack of cards,
            // where a twelve-line pallet is a screen of scrolling per line.
            // The one button this page exists for, and it used to be three
            // levels down a "..." menu called "Open Scanning Station" while
            // "Add Lines" got a header button. Receiving is the job; the label
            // says which part of it you are in, because "Start" on a pallet you
            // half-received on Tuesday is a lie about what is about to happen.
            Action::make('receive_now')
                ->label(function () {
                    $progress = $this->getRecord()->receivingProgress();

                    if (! $progress['started']) {
                        return 'Start receiving';
                    }

                    return "Continue receiving ({$progress['received']} of {$progress['expected']})";
                })
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->size(\Filament\Support\Enums\Size::Large)
                ->url(fn () => PalletResource::getUrl('receive', ['record' => $this->getRecord()]))
                // A finished pallet is finished. The manifest is no longer a
                // gate: it used to require lines before receiving could start,
                // so a delivery nobody typed up first had to be keyed in as
                // expectations — and nothing downstream reads them. Lines can
                // be added from the scanner as the box is unpacked.
                ->visible(fn () => ! in_array($this->getRecord()->status, ['received', 'processed'], true)),

            // The other direction of the same question. From an item you ask
            // "where did this come from"; from a pallet, "what did this bring
            // in" — and the answer is a table you can correct in place rather
            // than a list of links out to product pages.
            Action::make('pallet_items')
                ->label('Items from this pallet')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(fn () => PalletResource::getUrl('items', ['record' => $this->record]))
                ->visible(fn () => $this->getRecord()->lines()->exists()),

            Action::make('add_lines')
                ->label('Add Lines')
                ->icon('heroicon-o-table-cells')
                ->color('gray')
                ->url(fn () => PalletResource::getUrl('add-lines', ['record' => $this->record])),

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
                            ->body('If it really is on this pallet, add it to the manifest first.')
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
