<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryCase;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletAttachment;
use App\Models\PalletLine;
use App\Services\PalletScanningService;
use App\Services\ReceivingService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ReceivePallet extends Page
{
    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Receive Pallet';

    public Pallet $record;

    public string $barcodeInput = '';
    public ?string $lastScannedResult = null;
    public bool $lastScanSuccess = false;
    public ?array $lastScanDetails = null;

    public ?string $receivedByName = null;
    public ?array $uploadedAttachments = [];

    /** @var array<int, array{id:int, line_number:int, description:string, case_count:int, received:int, mapped:bool}> */
    public array $lineProgress = [];

    public function getView(): string
    {
        return 'filament.pages.receive-pallet';
    }

    public function mount(Pallet $record): void
    {
        $this->record = $record;
        $this->loadRelations();

        // Opening the station is the moment receiving starts, so the pallet
        // says so without anybody pressing a separate button first. Idempotent:
        // a pallet worked over three days keeps the date the first box was
        // opened rather than the date of the latest visit.
        $this->record->markReceivingStarted();

        // Whoever is signed in is doing the receiving. Asking them to type
        // their own name is a question the app already knows the answer to,
        // and one that gets skipped and left blank. Still editable, because
        // somebody signing in on the warehouse tablet may be receiving on
        // behalf of the person actually holding the scanner.
        $this->receivedByName ??= auth()->user()?->name;

        $this->refreshProgress();
    }

    /**
     * Re-apply the eager loads on every request, not just the first.
     *
     * mount() runs once; every scan after it is a Livewire round trip that
     * re-hydrates this pallet from the database with no relations loaded. With
     * lazy loading disabled that is a 500 rather than an extra query — the same
     * fault that made every tab on the item page fail while looking merely
     * slow. This page is worse to get wrong: it fails while somebody is
     * standing at a pallet with a scanner.
     */
    public function booted(): void
    {
        if ($this->record ?? null) {
            $this->loadRelations();
        }
    }

    private function loadRelations(): void
    {
        $this->record->load(['vendor', 'lines.cases', 'lines.inventoryItem', 'lines.location']);
    }

    public function refreshProgress(): void
    {
        $this->lineProgress = $this->record->lines->map(fn (PalletLine $line) => [
            'id'          => $line->id,
            'line_number' => $line->line_number,
            'description' => $line->description,
            'case_count'  => $line->case_count,
            'received'    => $line->cases->where('status', '!=', 'expected')->count(),
            'mapped'      => $line->isFullyMapped(),
            'item_name'   => $line->inventoryItem?->name,
            'location'    => $line->location?->name,
        ])->toArray();
    }

    /**
     * The staged line the next scan belongs to, if one was tapped.
     *
     * Tapping the row first is the honest version of "which box is this?" —
     * standing at the pallet you already know, and the code on the box is the
     * one thing the app does not.
     */
    public ?int $targetLineId = null;

    /**
     * A scanned code with nowhere obvious to go.
     *
     * Held rather than discarded: the box has been scanned, so throwing the
     * code away and asking for it again is asking somebody to do the one part
     * that already worked.
     */
    public ?string $pendingCode = null;

    /** The staged lines a held code could belong to. */
    public function pendingChoices(): \Illuminate\Support\Collection
    {
        return $this->pendingCode === null
            ? collect()
            : $this->unmappedLines();
    }

    /** Put the held code on the line somebody picked, and count the box. */
    public function assignPendingTo(int $lineId): void
    {
        $line = $this->record->lines->firstWhere('id', $lineId);
        $code = $this->pendingCode;

        $this->pendingCode = null;

        if (! $line || $code === null) {
            return;
        }

        if ($this->linkAndCount($line, $code)) {
            $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
            $this->refreshProgress();
        }
    }

    public function discardPending(): void
    {
        $this->pendingCode = null;
    }

    /** Aim the next scan at a line. Tapping the same row again clears it. */
    public function targetLine(?int $lineId): void
    {
        $this->targetLineId = ($this->targetLineId === $lineId) ? null : $lineId;
        $this->lastScannedResult = null;
        $this->lastScanDetails = null;

        // Setting a flag is not what pressing "Scan" means. The scanner is at
        // the top of a long page and the line you tapped is somewhere down it,
        // so aiming without moving looked like the button did nothing.
        if ($this->targetLineId !== null) {
            $this->dispatch('scan-line-targeted');
        }
    }

    /**
     * Link a staged line to a product by scanning it, then count the box.
     *
     * This is what staging is for. A line is written down from the packing
     * slip before anything arrives, so it names a thing that may not exist in
     * inventory yet — and requiring it to be "mapped" first turned the arrival
     * of a pallet into a data-entry job standing in front of it. The scan is
     * the mapping: a known code links, an unknown one creates the product
     * under the name that was staged, and either way the box in your hand
     * counts as received.
     */
    private function linkAndCount(PalletLine $line, string $barcode): bool
    {
        $locationId = $line->inventory_location_id ?: InventoryLocation::defaultReceivingId();

        if (! $locationId) {
            $this->lastScannedResult = '✗ No receiving location is set. Choose one on the line, or set a default under Settings → Receiving.';
            $this->lastScanSuccess = false;

            return false;
        }

        try {
            $result = app(ReceivingService::class)->linkLineByScan(
                $line,
                $barcode,
                InventoryLocation::findOrFail($locationId),
            );

            $count = app(ReceivingService::class)->confirmOneCase($result['line']);

            $this->lastScannedResult = sprintf(
                '✓ %s %s — %d of %d boxes in',
                $result['item']->name,
                $result['created'] ? 'added to inventory' : 'matched in inventory',
                $count['received'],
                $count['expected'],
            );
            $this->lastScanDetails = null;
            $this->lastScanSuccess = true;
            $this->targetLineId = null;

            return true;
        } catch (\RuntimeException $e) {
            $this->lastScannedResult = "✗ {$e->getMessage()}";
            $this->lastScanDetails = null;
            $this->lastScanSuccess = false;

            return false;
        }
    }

    /** Staged lines that are not linked to a product yet. */
    private function unmappedLines(): \Illuminate\Support\Collection
    {
        return $this->record->lines->reject(fn (PalletLine $line) => $line->isFullyMapped())->values();
    }

    public function submitBarcode(): void
    {
        $barcode = trim($this->barcodeInput);
        $this->barcodeInput = '';

        if (empty($barcode)) {
            return;
        }

        // The scanned code is used as scanned.
        //
        // This used to run preg_replace('/^[^0-9]*/', ...) over it to drop
        // "common prefixes", which strips every leading non-digit — so a code
        // that is not purely numeric is destroyed before anything looks it up.
        // Case labels and the SKUs this app generates itself both start with
        // letters (Product::generateSku produces VB250815abcd), so the codes
        // most likely to be scanned at a pallet were the ones being mangled.
        //
        // A scanner prefix is still worth handling, but as a second attempt
        // rather than by rewriting the code before the first one.
        $rawBarcode = $barcode;
        $numeric    = preg_replace('/^[^0-9]*/', '', $barcode);

        // A row was tapped, so there is no question about which line this is.
        if ($this->targetLineId) {
            $line = $this->record->lines->firstWhere('id', $this->targetLineId);

            if ($line && ! $line->isFullyMapped()) {
                if ($this->linkAndCount($line, $barcode)) {
                    $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
                    $this->refreshProgress();
                }

                return;
            }
        }

        // A case label on this pallet is the most specific thing the code can
        // be, so it is checked first.
        //
        // It used to be checked last and was therefore never checked at all:
        // the scanner below throws for any code that is not a known product or
        // container barcode, and a per-box case label is neither — so the
        // receiveCaseByBarcode call further down sat behind an exception that
        // always fired first. Scanning a case at the pallet did everything
        // except receive the case.
        foreach ([$rawBarcode, $numeric] as $candidate) {
            if ($candidate === '') {
                continue;
            }

            $case = InventoryCase::findByBarcode($candidate);

            if (! $case || $case->status !== 'expected') {
                continue;
            }

            try {
                $received = app(ReceivingService::class)->receiveCaseByBarcode($candidate);
                $this->lastScannedResult = "✓ Received {$candidate} — " . ($received->palletLine->inventoryItem?->name ?? 'case');
                $this->lastScanDetails = null;
                $this->lastScanSuccess = true;
            } catch (\RuntimeException $e) {
                $this->lastScannedResult = "✗ {$e->getMessage()}";
                $this->lastScanDetails = null;
                $this->lastScanSuccess = false;
            }

            $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
            $this->refreshProgress();

            return;
        }

        $barcode = $rawBarcode;

        try {
            $scanner = app(PalletScanningService::class);
            $scanResult = $scanner->scanBarcode($barcode);

            if ($scanResult['type'] === 'case') {
                // This is a case barcode - show what's inside
                $this->lastScannedResult = "📦 {$scanResult['label']}";
                $this->lastScanDetails = [
                    'type'      => 'case',
                    'parent'    => $scanResult['parent']->name,
                    'child'     => $scanResult['child']->name,
                    'quantity'  => $scanResult['quantity'],
                ];
                $this->lastScanSuccess = true;
            } else {
                // This is an individual item - try to receive it as a case
                $case = app(ReceivingService::class)->receiveCaseByBarcode($barcode);
                $this->lastScannedResult = "✓ Received item {$barcode} — {$case->palletLine->inventoryItem?->name}";
                $this->lastScanDetails = null;
                $this->lastScanSuccess = true;
                $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
                $this->refreshProgress();
            }
        } catch (\RuntimeException $e) {
            // Nothing already in inventory answers to this code. Before
            // assuming that is a mistake, check whether it is simply a staged
            // line arriving for the first time — which is the ordinary case on
            // a pallet, not an error. With exactly one line still unlinked
            // there is no ambiguity about which it is.
            $unmapped = $this->unmappedLines();

            if ($unmapped->count() === 1) {
                if ($this->linkAndCount($unmapped->first(), $barcode)) {
                    $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
                    $this->refreshProgress();
                }

                return;
            }

            if ($unmapped->count() > 1) {
                // Keep the code and ask which line it is, rather than sending
                // somebody back to scan the same box twice. The scan already
                // happened; the only thing missing is which of the staged lines
                // it belongs to, and that is one tap.
                $this->pendingCode = $barcode;
                $this->lastScannedResult = null;
                $this->lastScanDetails = null;
                $this->lastScanSuccess = false;

                return;
            }

            $this->lastScannedResult = "✗ {$e->getMessage()}";
            $this->lastScanDetails = null;
            $this->lastScanSuccess = false;
        }
    }

    public function receiveLine(int $lineId): void
    {
        $line = PalletLine::where('id', $lineId)
            ->where('pallet_id', $this->record->id)
            ->firstOrFail();

        try {
            $count = app(ReceivingService::class)->receiveAllCasesForLine($line);
            Notification::make()
                ->title("Received {$count} cases for line #{$line->line_number}")
                ->success()
                ->send();
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }

        $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
        $this->refreshProgress();
    }

    public function markLineAsShort(int $lineId): void
    {
        $line = PalletLine::where('id', $lineId)
            ->where('pallet_id', $this->record->id)
            ->firstOrFail();

        try {
            // Filing the report lives in ReceivingService so this page and the
            // scanner say the same thing by "short" — they had grown separate
            // copies, which is how three screens ended up with three different
            // ideas of what a partial delivery was.
            $result = app(ReceivingService::class)->markLineShort($line);

            if ($result['short'] > 0) {
                Notification::make()
                    ->title("Marked {$result['short']} case(s) as missing")
                    ->body("{$line->description} — {$result['short']} short")
                    ->warning()
                    ->send();
            }
        } catch (\Throwable $e) {
            Notification::make()->title('Could not mark as short')->body($e->getMessage())->danger()->send();
        }
    }

    public function finalizePallet(): void
    {
        try {
            $this->record->update([
                'status'              => 'received',
                'received_by_name'    => $this->receivedByName,
                'attachments_count'   => $this->record->attachments()->count(),
            ]);

            app(ReceivingService::class)->receivePallet($this->record);

            Notification::make()
                ->title('✓ Pallet received and finalized')
                ->body('All items have been recorded. View the pallet for details.')
                ->success()
                ->send();

            $this->redirect(PalletResource::getUrl('view', ['record' => $this->record]));
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            // Leaving is not abandoning. A shipment turns up over days — half a
            // pallet on Tuesday, the rest on Friday — and every case scanned is
            // already committed to stock, so stopping partway needs no save and
            // loses nothing. Saying that on the button is the point: "Back"
            // reads like cancelling, which is why people asked whether they had
            // to finish in one go.
            Action::make('pause')
                ->label('Pause — keep it open')
                ->icon('heroicon-o-pause-circle')
                ->color('gray')
                ->action(function () {
                    $progress = $this->record->receivingProgress();

                    Notification::make()
                        ->title('Paused — nothing lost')
                        ->body("{$progress['received']} of {$progress['expected']} cases are in and counted. "
                            . 'Pick it up from the pallet whenever the rest turns up.')
                        ->success()
                        ->send();

                    $this->redirect(PalletResource::getUrl('view', ['record' => $this->record]));
                }),

            // Damage, seal numbers, the paperwork taped to the shrink wrap —
            // all photographed here, at the pallet, mid-receipt. This page
            // could list attachments but not add any: it told you to go and
            // edit the pallet instead, which is a page change, a form, a save
            // and a walk back, one-handed, holding a box.
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

            Action::make('map_line')
                ->label('Map Line to Item')
                ->icon('heroicon-o-link')
                ->color('info')
                ->form([
                    Select::make('pallet_line_id')
                        ->label('Manifest Line')
                        ->options(fn () => $this->record->lines
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
                        ->helperText('Suggestions based on previous show history. Type a name, SKU, or barcode to search all items.')
                        ->createOptionForm([
                            TextInput::make('name')->required(),
                            TextInput::make('sku')->maxLength(100),
                            TextInput::make('category')->maxLength(100),
                        ])
                        ->createOptionUsing(function (array $data) {
                            return InventoryItem::create(array_merge($data, ['is_active' => true]))->getKey();
                        }),
                    Select::make('inventory_location_id')
                        ->label('Receive Into Location')
                        ->options(fn () => InventoryLocation::activeOptions())
                        ->required()
                        ->searchable(),
                ])
                ->action(function (array $data) {
                    try {
                        $line = PalletLine::where('id', $data['pallet_line_id'])
                            ->where('pallet_id', $this->record->id)
                            ->firstOrFail();
                        $item     = InventoryItem::findOrFail($data['inventory_item_id']);
                        $location = InventoryLocation::findOrFail($data['inventory_location_id']);
                        app(ReceivingService::class)->mapLine($line, $item, $location);
                        Notification::make()->title('Line mapped')->success()->send();
                        $this->record->refresh()->load(['lines.cases', 'lines.inventoryItem', 'lines.location']);
                        $this->refreshProgress();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Could not map line')->body($e->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
