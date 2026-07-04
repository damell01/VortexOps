<?php

namespace App\Filament\Resources\ReceivingSessionResource\Pages;

use App\Filament\Resources\ReceivingSessionResource;
use App\Models\InventoryLocation;
use App\Models\PalletLine;
use App\Models\Product;
use App\Models\ReceivingSession;
use App\Services\ReceivingSessionService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class ReviewReceivingSession extends Page
{
    protected static string $resource = ReceivingSessionResource::class;
    protected static ?string $title = 'Review Receiving Session';

    public ReceivingSession $record;

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
        $line = PalletLine::find($lineId);
        if (! $line?->inventory_item_id) {
            return;
        }

        $product = Product::find($line->inventory_item_id);
        if (! $product) {
            return;
        }

        app(ReceivingSessionService::class)->confirmLineMatch($line, $product, auth()->id());

        $this->panels[$lineId]['action'] = null;
        $this->flashOk = "Accepted: {$product->name}";
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

        $line    = PalletLine::find($lineId);
        $product = Product::find($productId);

        if (! $line || ! $product) {
            return;
        }

        if ($locationId) {
            $line->update(['inventory_location_id' => $locationId]);
        }

        app(ReceivingSessionService::class)->confirmLineMatch($line, $product, auth()->id());

        unset($this->panels[$lineId]);
        $this->flashOk = "Mapped to: {$product->name}";
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

        $line = PalletLine::find($lineId);
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
        $this->flashOk = "Created & mapped: {$product->name}";
        $this->refreshGrouped();
    }

    /**
     * Set the destination location on a line without changing the product match.
     */
    public function setLocation(int $lineId, int $locationId): void
    {
        PalletLine::where('id', $lineId)->update(['inventory_location_id' => $locationId]);
        $this->refreshGrouped();
    }

    /**
     * Accept all auto-matched lines in bulk.
     */
    public function acceptAllAuto(): void
    {
        $service = app(ReceivingSessionService::class);
        $count   = 0;

        foreach ($this->grouped['auto'] as $entry) {
            $line    = PalletLine::find($entry['id']);
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

        $pallet = $this->record->pallets()->first();

        if (! $pallet) {
            // Create a pallet for this session if none exists
            $pallet = \App\Models\Pallet::create([
                'vendor_id'            => $this->record->vendor_id,
                'receiving_session_id' => $this->record->id,
                'reference'            => "Session #{$this->record->id}",
                'status'               => 'receiving',
                'created_by'           => auth()->id(),
            ]);
        }

        $lines = [];
        foreach (explode("\n", $raw) as $row) {
            $parts = array_map('trim', explode('|', $row));
            if (empty($parts[0])) {
                continue;
            }
            $lines[] = [
                'description' => $parts[0],
                'qty'         => isset($parts[1]) ? (float) $parts[1] : 1,
                'unit_cost'   => isset($parts[2]) ? (float) $parts[2] : 0,
                'upc'         => $parts[3] ?? null,
            ];
        }

        if (! empty($lines)) {
            app(ReceivingSessionService::class)->importLines($this->record, $pallet, $lines);
        }

        $this->manualLines      = '';
        $this->showManualImport = false;
        $this->flashOk          = count($lines) . ' lines imported and matched.';
        $this->refreshGrouped();
    }

    // ── Complete session ──────────────────────────────────────────────────────

    public function completeSession(): void
    {
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
            Action::make('back')
                ->label('All Sessions')
                ->icon('heroicon-o-arrow-left')
                ->url(ReceivingSessionResource::getUrl())
                ->color('gray'),
        ];
    }
}
