<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
use App\Models\WhatnotShowOrder;
use App\Support\AdminModules;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EndOfStreamForm extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $moduleSlug = 'streams';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-camera';

    protected static ?string $title = 'End of Stream';

    public ?Show $show = null;

    /** Wizard position: 1 Items, 2 Show Details, 3 Review & Submit. */
    public int $step = 1;

    // Step 2 fields, mirrored from the log entry so the form can be edited
    // without writing on every keystroke.
    public string $hoursStreamed   = '';
    public string $shipments       = '';
    public string $pweCount        = '';
    public string $labelCount      = '';
    public string $packagesOver500 = '';
    public string $logNotes        = '';
    public string $search = '';
    public array $selectedItems = [];
    public array $itemQuantities = [];

    // The view toggles this with $set('showInventoryPicker', ...) but the
    // property was never declared, so opening the item picker threw
    // PublicPropertyNotFoundException and 500'd the page.
    public bool $showInventoryPicker = false;

    public function getTitle(): string | Htmlable
    {
        return $this->show ? "End of Stream: {$this->show->title}" : 'End of Stream';
    }

    public function getSubheading(): ?string
    {
        return 'Select the show and the items you sold. We\'ll handle the rest.';
    }

    public function getView(): string
    {
        // Rebuilt Items step. The previous view is kept at
        // filament.pages.end-of-stream-form so this is one line to revert.
        return 'filament.pages.end-of-stream-items';
    }

    public function mount(?string $showId = null): void
    {
        // Filament pages do not bind query strings to mount arguments, so the
        // ?showId= link from the shows list arrived null and the form always
        // opened on the show picker. Fall back to the request.
        $showId = $showId ?: request()->query('showId');

        if ($showId) {
            $this->show = Show::find($showId);
        }
    }

    public function selectShow(string $showId): void
    {
        if (empty($showId)) {
            $this->show = null;
        } else {
            $this->show = Show::findOrFail($showId);
        }
        $this->search = '';
        $this->selectedItems = [];
        $this->itemQuantities = [];
    }

    public function getShowsProperty()
    {
        $user = auth()->user();
        $streamer = $user?->streamer;

        if (!$streamer) {
            return [];
        }

        return Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->where('status', '!=', 'closed')
            ->orderBy('show_date', 'desc')
            ->limit(20)
            ->get();
    }

    public function getInventoryProperty()
    {
        $query = InventoryItem::query()
            ->where('is_active', true)
            ->with(['stock' => fn ($q) => $q->where('quantity', '>', 0)]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('brand', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('name')->limit(50)->get();
    }

    public function toggleItem(int $itemId): void
    {
        if (in_array($itemId, $this->selectedItems)) {
            $this->selectedItems = array_filter($this->selectedItems, fn ($id) => $id !== $itemId);
            unset($this->itemQuantities[$itemId]);
        } else {
            $this->selectedItems[] = $itemId;
            $this->itemQuantities[$itemId] = 1;
        }
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        if ($quantity > 0) {
            $this->itemQuantities[$itemId] = $quantity;
        }
    }

    /**
     * ── Line items ────────────────────────────────────────────────────────
     * These operate on streamer_log_items rows for the show's log entry, so
     * what the streamer sees is the same data submission will deduct from.
     * The older selectedItems/itemQuantities arrays are left in place for the
     * current view; the rebuilt view uses these instead.
     */

    /** The log entry for the selected show, created on first use. */
    public function logEntry(): ?\App\Models\StreamerLogEntry
    {
        if (! $this->show) {
            return null;
        }

        return \App\Models\StreamerLogEntry::firstOrCreate(
            ['show_id' => $this->show->id],
            [
                // Queried rather than auth()->user()->streamer: lazy loading is
                // disabled outside production, so the relation accessor throws.
                'streamer_id' => \App\Models\Streamer::where('user_id', auth()->id())->value('id'),
                'status'      => 'pending',
            ],
        );
    }

    /** Line items for the current show, newest last. */
    public function getLineItemsProperty()
    {
        $entry = $this->logEntry();

        return $entry
            ? $entry->items()->with('inventoryItem')->orderBy('id')->get()
            : collect();
    }

    /** Totals for the summary panel. */
    public function getSummaryProperty(): array
    {
        $lines = $this->lineItems;

        return [
            'items'       => $lines->count(),
            'units'       => (int) $lines->sum('quantity'),
            'productCost' => (float) $lines->sum(fn ($l) => $l->total_cost),
            'unmatched'   => $lines->whereNull('inventory_item_id')->count(),
        ];
    }

    public function addLineItem(int $inventoryItemId, int $quantity = 1): void
    {
        $entry = $this->logEntry();
        if (! $entry) {
            return;
        }

        $item = \App\Models\InventoryItem::find($inventoryItemId);
        if (! $item) {
            return;
        }

        // Adding the same product twice bumps the existing line rather than
        // creating a duplicate the streamer then has to reconcile by hand.
        $line = $entry->items()->where('inventory_item_id', $item->id)->first();

        if ($line) {
            $line->increment('quantity', max(1, $quantity));
        } else {
            $entry->items()->create([
                'inventory_item_id' => $item->id,
                'item_name'         => $item->name,
                'quantity'          => max(1, $quantity),
                'unit_cost'         => $item->average_cost,
            ]);
        }

        $this->showInventoryPicker = false;
        $this->search = '';
    }

    /** For something not in the catalogue yet; stays unmatched by design. */
    public function addManualLineItem(string $name, int $quantity = 1, ?float $unitCost = null): void
    {
        $entry = $this->logEntry();
        $name = trim($name);

        if (! $entry || $name === '') {
            return;
        }

        $entry->items()->create([
            'inventory_item_id' => null,
            'item_name'         => $name,
            'quantity'          => max(1, $quantity),
            'unit_cost'         => $unitCost,
        ]);
    }

    public function setLineQuantity(int $lineId, int $quantity): void
    {
        $line = $this->logEntry()?->items()->find($lineId);
        $line?->update(['quantity' => max(1, $quantity)]);
    }

    public function setLineCost(int $lineId, float $unitCost): void
    {
        $line = $this->logEntry()?->items()->find($lineId);
        $line?->update(['unit_cost' => max(0, $unitCost)]);
    }

    public function removeLineItem(int $lineId): void
    {
        $this->logEntry()?->items()->find($lineId)?->delete();
    }

    /** Load step 2 fields from the entry when the wizard reaches them. */
    public function loadDetails(): void
    {
        $entry = $this->logEntry();
        if (! $entry) {
            return;
        }

        $this->hoursStreamed   = (string) ($entry->hours_streamed ?? '');
        $this->shipments       = (string) ($entry->number_of_shipments ?? '');
        $this->pweCount        = (string) ($entry->pwe_count ?? '');
        $this->labelCount      = (string) ($entry->label_count ?? '');
        $this->packagesOver500 = (string) ($entry->number_of_packages_over_500 ?? '');
        $this->logNotes        = (string) ($entry->notes ?? '');
    }

    public function saveDetails(): void
    {
        $this->logEntry()?->update([
            'hours_streamed'              => $this->hoursStreamed !== '' ? (float) $this->hoursStreamed : null,
            'number_of_shipments'         => $this->shipments !== '' ? (int) $this->shipments : null,
            'pwe_count'                   => $this->pweCount !== '' ? (int) $this->pweCount : null,
            'label_count'                 => $this->labelCount !== '' ? (int) $this->labelCount : null,
            'number_of_packages_over_500' => $this->packagesOver500 !== '' ? (int) $this->packagesOver500 : null,
            'notes'                       => $this->logNotes !== '' ? $this->logNotes : null,
        ]);
    }

    public function goToStep(int $step): void
    {
        $step = max(1, min(3, $step));

        // Leaving the details step persists it, so moving between steps never
        // silently discards what was typed.
        if ($this->step === 2 && $step !== 2) {
            $this->saveDetails();
        }

        if ($step === 2) {
            $this->loadDetails();
        }

        $this->step = $step;
    }

    /** Problems submission would hit, shown on the review step beforehand. */
    public function getDeductionPreviewProperty(): array
    {
        $entry = $this->logEntry();
        if (! $entry) {
            return [];
        }

        $problems = [];

        foreach ($entry->items()->with('inventoryItem')->get() as $line) {
            if (! $line->inventoryItem) {
                $problems[] = "\"{$line->item_name}\" is not linked to an inventory product.";
                continue;
            }

            $onHand = \App\Models\InventoryStock::where('inventory_item_id', $line->inventory_item_id)
                ->whereIn('inventory_location_id', \App\Models\Streamer::where('user_id', auth()->id())
                    ->first()?->inventoryLocations->pluck('id') ?? [])
                ->sum('quantity');

            if ((float) $onHand < (int) $line->quantity) {
                $problems[] = "\"{$line->item_name}\" needs {$line->quantity} but only " . (float) $onHand . " on hand.";
            }
        }

        return $problems;
    }

    public function submit(): void
    {
        if (! $this->show) {
            Notification::make()
                ->title('No show selected')
                ->body('Please select a show first.')
                ->danger()
                ->send();

            return;
        }

        $entry = $this->logEntry();

        if (! $entry) {
            Notification::make()->title('Could not open the log for this show')->danger()->send();

            return;
        }

        // Reads the line items the streamer actually sees, rather than the old
        // selectedItems array writing WhatnotShowOrder rows. Items are entered
        // by hand now, so there are no scraped orders to reconcile against.
        $lines = $entry->items()->with('inventoryItem')->get();

        if ($lines->isEmpty()) {
            Notification::make()
                ->title('No items added')
                ->body('Add at least one item you sold during this show.')
                ->warning()
                ->send();

            return;
        }

        try {
            // Product cost is derived from the lines so it cannot drift from
            // what was logged.
            $entry->update([
                'product_cost' => $lines->sum(fn ($line) => $line->total_cost),
                'status'       => 'streamer_reviewed',
            ]);

            $problems = $entry->submitReport();

            if (empty($problems)) {
                Notification::make()
                    ->title('Report submitted')
                    ->body($lines->count() . ' item(s) logged and stock deducted.')
                    ->success()
                    ->send();
            } else {
                // Submission still stands, but the streamer is told exactly what
                // did not deduct instead of being shown a clean success.
                Notification::make()
                    ->title('Submitted, but some stock did not deduct')
                    ->body(implode("\n", $problems))
                    ->warning()
                    ->duration(12000)
                    ->send();
            }

            $this->selectedItems = [];
            $this->itemQuantities = [];
        } catch (\Throwable $e) {
            report($e);

            Notification::make()
                ->title('Could not submit the report')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        // ShowResource's "End of Stream" action is visible to admins as well as
        // the show's own streamers, so gating this page to streamers alone sent
        // admins from a visible button straight to a 403.
        return AdminModules::isEnabled('streams')
            && (($user?->isStreamer() ?? false)
                || ($user?->isAdmin() ?? false)
                || ($user?->isOwner() ?? false));
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return 'end-of-stream';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Show to streamers who can access the form
        return auth()->user()?->isStreamer() ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return 'End of Stream';
    }

    public static function getNavigationGroup(): string | null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 38;
    }
}
