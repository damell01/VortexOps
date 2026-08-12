<?php

namespace App\Filament\Resources\ReceivingSessionResource\Pages;

use App\Filament\Resources\ReceivingSessionResource;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\PalletLine;
use App\Models\Product;
use App\Models\ProductIdentity;
use App\Models\ReceivingSession;
use App\Services\ReceivingSessionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ReviewReceivingSession extends Page
{
    use WithFileUploads;

    protected static string $resource = ReceivingSessionResource::class;
    protected static ?string $title = 'Review Receiving Session';

    public ReceivingSession $record;

    // Uploaded manifest (CSV) awaiting parse.
    public ?TemporaryUploadedFile $csvFile = null;

    // ── Per-line panel state ───────────────────────────────────────────────────
    // keyed by PalletLine id: ['action' => 'accept'|'choose'|'create'|null, 'selectedProductId' => int|null, ...]
    public array $panels = [];

    // Manual line import form
    public bool   $showManualImport = false;
    public string $manualLines      = '';   // raw textarea: "Description | Qty | Unit Cost"

    // Flash
    public ?string $flashOk    = null;
    public ?string $flashError = null;

    // ── Computed (refreshed on every action) ───────────────────────────────────
    public array $grouped = [];   // ['auto' => [...], 'review' => [...], 'new' => [...]]
    public bool  $showAuto = false;
    public bool  $showPreview = false;

    // ── Boot ──────────────────────────────────────────────────────────────────

    public function mount(ReceivingSession $record): void
    {
        $this->record = $record;
        $this->refreshGrouped();
    }

    public function getView(): string
    {
        return 'filament.resources.receiving-session-resource.pages.review-receiving-session';
    }

    // ── Computed ───────────────────────────────────────────────────────────────

    public function getLocationsProperty()
    {
        return InventoryLocation::orderBy('name')->get(['id', 'name', 'type']);
    }

    public function getProductsProperty()
    {
        return Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'brand', 'year', 'set_name']);
    }

    /**
     * Build receiving preview: what will happen when completeSession() is called.
     */
    public function getPreviewProperty(): array
    {
        $allLines = collect($this->grouped['auto'])
            ->concat($this->grouped['review'])
            ->concat($this->grouped['new']);

        $newProducts   = 0;
        $updateProducts = 0;
        $lotsToCreate  = 0;
        $totalCases    = 0;
        $totalCost     = 0.0;
        $warnings      = [];

        $existingProductIds = [];

        foreach ($allLines as $line) {
            $cases    = (int) ($line['case_count'] ?? 0);
            $cost     = (float) str_replace(',', '', $line['unit_cost'] ?? 0);
            $prodId   = $line['matched_id'] ?? null;

            if ($prodId) {
                if (in_array($prodId, $existingProductIds)) {
                    $updateProducts++;
                } else {
                    $existingProductIds[] = $prodId;
                    $updateProducts++;
                    $lotsToCreate++;
                }
                $totalCases += $cases;
                $totalCost  += $cases * $cost;
            } else {
                $newProducts++;
            }
        }

        // Low-confidence warning
        $lowConf = collect($this->grouped['review'])->where('confidence', '<', 0.85)->count();
        if ($lowConf > 0) {
            $warnings[] = "{$lowConf} low confidence match(es)";
        }

        // Missing UPC warning
        if ($newProducts > 0) {
            $warnings[] = "{$newProducts} new product(s) without catalog entry";
        }

        $avgCost = $totalCases > 0 ? $totalCost / $totalCases : 0.0;

        return [
            'new_products'    => $newProducts,
            'update_products' => $updateProducts,
            'lots_to_create'  => $lotsToCreate,
            'total_cases'     => $totalCases,
            'total_cost'      => $totalCost,
            'avg_cost'        => $avgCost,
            'warnings'        => $warnings,
        ];
    }

    /**
     * Load rich product card data for all matched products in the session.
     * Returns: [productId => ['stock' => [...], 'avg_cost' => float, 'aliases' => [...], 'last_received' => ?string, 'last_vendor' => ?string]]
     */
    public function getProductCardsProperty(): array
    {
        $ids = collect($this->grouped['review'])
            ->concat($this->grouped['auto'])
            ->pluck('matched_id')
            ->filter()
            ->unique()
            ->values()
            ->toArray();

        if (empty($ids)) {
            return [];
        }

        $cards = [];

        $stocks = InventoryStock::with('location')
            ->whereIn('inventory_item_id', $ids)
            ->where('quantity', '>', 0)
            ->get()
            ->groupBy('inventory_item_id');

        $products = Product::whereIn('id', $ids)
            ->with(['lots' => fn ($q) => $q->latest('received_at')->limit(1)])
            ->get()
            ->keyBy('id');

        $aliases = ProductIdentity::whereIn('product_id', $ids)
            ->where('type', ProductIdentity::TYPE_ALIAS)
            ->orderByDesc('times_confirmed')
            ->get()
            ->groupBy('product_id');

        foreach ($ids as $id) {
            $product   = $products[$id] ?? null;
            $lastLot   = $product?->lots->first();
            $stockRows = $stocks[$id] ?? collect();

            $cards[$id] = [
                'stock'         => $stockRows->map(fn ($s) => [
                    'location' => $s->location?->name ?? 'Unknown',
                    'qty'      => (int) $s->quantity,
                ])->values()->toArray(),
                'total_stock'   => (int) $stockRows->sum('quantity'),
                'avg_cost'      => number_format((float) ($product?->average_cost ?? 0), 2),
                'last_received' => $lastLot?->received_at?->diffForHumans() ?? 'Never',
                'aliases'       => ($aliases[$id] ?? collect())->pluck('value')->take(5)->toArray(),
            ];
        }

        return $cards;
    }

    // ── Data refresh ──────────────────────────────────────────────────────────

    private function refreshGrouped(): void
    {
        $session = $this->record->fresh();
        $lines   = $session->palletLines()->with(['product', 'productIdentity'])->get();

        $auto   = [];
        $review = [];
        $new    = [];

        foreach ($lines as $line) {
            $conf = (float) $line->match_confidence;
            $entry = $this->lineToArray($line);

            if ($line->inventory_item_id && $conf >= 0.95) {
                $auto[] = $entry;
            } elseif ($line->inventory_item_id && $conf >= 0.80) {
                $review[] = $entry;
            } else {
                $new[] = $entry;
            }
        }

        $this->grouped = compact('auto', 'review', 'new');
        $this->record  = $session;
    }

    private function lineToArray(PalletLine $line): array
    {
        return [
            'id'               => $line->id,
            'line_number'      => $line->line_number,
            'vendor_desc'      => $line->vendor_description ?? $line->description,
            'matched_name'     => $line->product?->name,
            'matched_id'       => $line->inventory_item_id,
            'confidence'       => (float) $line->match_confidence,
            'confidence_pct'   => round((float) $line->match_confidence * 100),
            'stage'            => $line->match_stage,
            'reasons'          => $line->match_reasons ?? [],
            'location_id'      => $line->inventory_location_id,
            'case_count'       => $line->case_count,
            'unit_cost'        => number_format((float) $line->unit_cost, 2),
        ];
    }

    // ── Line panel actions ─────────────────────────────────────────────────────

    public function openPanel(int $lineId, string $action): void
    {
        $this->panels[$lineId] = array_merge($this->panels[$lineId] ?? [], [
            'action'            => $action,
            'selectedProductId' => null,
            'newName'           => '',
            'newBrand'          => '',
            'newSport'          => '',
            'newYear'           => '',
            'newUpc'            => '',
            'locationId'        => 0,
        ]);
    }

    public function closePanel(int $lineId): void
    {
        unset($this->panels[$lineId]);
    }

    /**
     * Accept the AI-suggested product match for a line.
     */
    public function acceptLine(int $lineId): void
    {
        $line = $this->record->palletLines()->find($lineId);
        if (! $line?->inventory_item_id) {
            return;
        }

        $product = Product::find($line->inventory_item_id);
        if (! $product) {
            return;
        }

        app(ReceivingSessionService::class)->confirmLineMatch($line, $product, auth()->id());

        $this->panels[$lineId]['action'] = null;
        $this->flashOk = "Accepted: {$product->name} — alias saved for next time";
        $this->refreshGrouped();
    }

    /**
     * Confirm a manually chosen product for a review line.
     */
    public function confirmChoose(int $lineId): void
    {
        $productId = $this->panels[$lineId]['selectedProductId'] ?? null;
        $locationId = $this->panels[$lineId]['locationId'] ?? null;

        if (! $productId) {
            $this->flashError = 'Select a product first.';
            return;
        }

        $line    = $this->record->palletLines()->find($lineId);
        $product = Product::find($productId);

        if (! $line || ! $product) {
            return;
        }

        if ($locationId) {
            $line->update(['inventory_location_id' => $locationId]);
        }

        app(ReceivingSessionService::class)->confirmLineMatch($line, $product, auth()->id());

        unset($this->panels[$lineId]);
        $this->flashOk = "Mapped to: {$product->name} — alias saved, will auto-match next time";
        $this->refreshGrouped();
    }

    /**
     * Create a new Product from the inline form and map the line to it.
     */
    public function confirmCreate(int $lineId): void
    {
        $panel = $this->panels[$lineId] ?? [];
        $name  = trim($panel['newName'] ?? '');

        if ($name === '') {
            $this->flashError = 'Product name is required.';
            return;
        }

        $line = $this->record->palletLines()->find($lineId);
        if (! $line) {
            return;
        }

        $product = Product::create([
            'name'         => $name,
            'brand'        => $panel['newBrand'] ?: null,
            'sport'        => $panel['newSport'] ?: null,
            'year'         => $panel['newYear'] ? (int) $panel['newYear'] : null,
            'upc'          => $panel['newUpc'] ?: null,
            'unit_cost'    => (float) $line->unit_cost,
            'average_cost' => (float) $line->unit_cost,
            'is_active'    => true,
        ]);

        if ($panel['locationId'] ?? null) {
            $line->update(['inventory_location_id' => $panel['locationId']]);
        }

        app(ReceivingSessionService::class)->confirmLineMatch($line, $product, auth()->id());

        unset($this->panels[$lineId]);
        $this->flashOk = "Created & mapped: {$product->name} — added to catalog";
        $this->refreshGrouped();
    }

    /**
     * Set the destination location on a line without changing the product match.
     */
    public function setLocation(int $lineId, int $locationId): void
    {
        $this->record->palletLines()->where('id', $lineId)->update(['inventory_location_id' => $locationId]);
        $this->refreshGrouped();
    }

    /**
     * Accept all auto-matched lines in bulk.
     */
    public function acceptAllAuto(): void
    {
        $service = app(ReceivingSessionService::class);
        $count   = 0;

        $ids   = array_column($this->grouped['auto'], 'id');
        $lines = PalletLine::with(['product', 'pallet', 'receivingSession'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        foreach ($this->grouped['auto'] as $entry) {
            $line    = $lines[$entry['id']] ?? null;
            $product = $line?->product;
            if ($line && $product) {
                $service->confirmLineMatch($line, $product, auth()->id());
                $count++;
            }
        }

        $this->flashOk = "Confirmed {$count} auto-matched line(s).";
        $this->refreshGrouped();
    }

    // ── Manual line import ────────────────────────────────────────────────────

    public function importManualLines(): void
    {
        $raw = trim($this->manualLines);
        if ($raw === '') {
            return;
        }

        $lines = [];
        foreach (explode("\n", $raw) as $row) {
            $parts = array_map('trim', explode('|', $row));
            if ($parts[0] === '') {
                continue;
            }
            $lines[] = [
                'description' => $parts[0],
                'qty'         => isset($parts[1]) ? (float) $parts[1] : 1,
                'unit_cost'   => isset($parts[2]) ? (float) $parts[2] : 0,
                'upc'         => ($parts[3] ?? '') !== '' ? $parts[3] : null,
            ];
        }

        $this->runImport($lines, count($lines) . ' lines imported and matched.');
        $this->manualLines = '';
    }

    /**
     * Parse an uploaded CSV manifest into lines and run the same matching
     * pipeline. Columns are: Description, Qty, Unit Cost, UPC (optional). A
     * header row is skipped automatically when the second column isn't numeric.
     */
    public function importCsvFile(): void
    {
        $this->validate(
            ['csvFile' => 'required|file|mimes:csv,txt|max:2048'],
            [],
            ['csvFile' => 'manifest file'],
        );

        $name  = $this->csvFile->getClientOriginalName();
        $lines = self::parseManifest((string) file_get_contents($this->csvFile->getRealPath()));

        if (empty($lines)) {
            $this->flashError = "No usable rows found in \"{$name}\". Expected: Description, Qty, Unit Cost, UPC.";

            return;
        }

        $this->runImport($lines, count($lines) . " lines parsed from \"{$name}\" and matched.");
        $this->csvFile = null;
    }

    /**
     * Parse a CSV manifest (Description, Qty, Unit Cost, UPC) into import lines.
     * A header row is dropped automatically when the second column isn't
     * numeric. Pure and static so it's unit-testable without a Livewire mount.
     *
     * @return array<int, array{description:string, qty:float, unit_cost:float, upc:?string}>
     */
    public static function parseManifest(string $contents): array
    {
        $rows = array_map('str_getcsv', preg_split('/\r\n|\r|\n/', trim($contents)) ?: []);

        // Drop a header row (e.g. "Description,Qty,...") when the qty cell is non-numeric.
        if (isset($rows[0][1]) && ! is_numeric(trim((string) $rows[0][1]))) {
            array_shift($rows);
        }

        $lines = [];
        foreach ($rows as $cols) {
            $desc = trim((string) ($cols[0] ?? ''));
            if ($desc === '') {
                continue;
            }
            $lines[] = [
                'description' => $desc,
                'qty'         => isset($cols[1]) ? (float) $cols[1] : 1,
                'unit_cost'   => isset($cols[2]) ? (float) $cols[2] : 0,
                'upc'         => isset($cols[3]) && trim((string) $cols[3]) !== '' ? trim((string) $cols[3]) : null,
            ];
        }

        return $lines;
    }

    /**
     * Shared import path for both manual paste and CSV upload: resolve (or
     * create) the session's pallet, run the matching pipeline, and refresh.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    private function runImport(array $lines, string $okMessage): void
    {
        if (empty($lines)) {
            return;
        }

        $pallet = $this->record->pallets()->first();

        if (! $pallet) {
            $pallet = \App\Models\Pallet::create([
                'vendor_id'            => $this->record->vendor_id,
                'receiving_session_id' => $this->record->id,
                'reference'            => "Session #{$this->record->id}",
                'status'               => 'receiving',
                'created_by'           => auth()->id(),
            ]);
        }

        app(ReceivingSessionService::class)->importLines($this->record, $pallet, $lines);

        $this->showManualImport = false;
        $this->flashOk          = $okMessage;
        $this->refreshGrouped();
    }

    // ── Complete session ──────────────────────────────────────────────────────

    public function completeSession(): void
    {
        if ($this->record->isComplete()) {
            $this->flashError = 'This session has already been completed.';
            return;
        }

        $pendingReview = count($this->grouped['review']);
        $pendingNew    = count($this->grouped['new']);

        if ($pendingReview > 0 || $pendingNew > 0) {
            $this->flashError = "Resolve all review and new-product lines before completing. ({$pendingReview} review, {$pendingNew} new)";
            return;
        }

        try {
            $result = app(ReceivingSessionService::class)->completeSession($this->record);

            Notification::make()
                ->title('Session Complete')
                ->body("{$result['cases_received']} cases received, {$result['lots_created']} lots created.")
                ->success()
                ->send();

            $this->refreshGrouped();
        } catch (\Throwable $e) {
            $this->flashError = $e->getMessage();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sample_manifest')
                ->label('Sample manifest')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Download a sample CSV to try the upload + AI matching flow')
                ->action(fn () => response()->streamDownload(function () {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, ['Description', 'Qty', 'Unit Cost', 'UPC']);
                    // A mix that exercises every match outcome against demo inventory:
                    fputcsv($out, ['2024 Bowman Chrome Hobby Box', 6, '125.00', '']);   // exact → auto
                    fputcsv($out, ['Pokemon SV Booster Pack', 100, '4.25', '']);        // close → auto/review
                    fputcsv($out, ['Prizm Basketball Hobby', 4, '185.00', '']);         // fuzzy → review
                    fputcsv($out, ['Topps Series 1 Baseball Hobby', 12, '95.00', '']);  // fuzzy → review
                    fputcsv($out, ['2025 Panini Mosaic Soccer Hobby Box', 3, '160.00', '']); // no match → new product
                    fclose($out);
                }, 'sample-manifest.csv', ['Content-Type' => 'text/csv'])),

            Action::make('back')
                ->label('All Sessions')
                ->icon('heroicon-o-arrow-left')
                ->url(ReceivingSessionResource::getUrl())
                ->color('gray'),
        ];
    }
}
