<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Services\InventoryService;
use App\Support\InventoryVisibility;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Moving and correcting one item's stock, on a page rather than in a dialog.
 *
 * These were three modals, and a modal is the wrong shape for them: each asks
 * you to decide a number against figures you cannot see while the dialog is
 * covering them. What is here now, where, and what will it be afterwards —
 * those are the questions, and the answer to all three is a table that the
 * form sits next to rather than on top of.
 *
 * One page rather than three near-identical ones. Adjusting, transferring and
 * sending to your own inventory are the same act — stock at one place becoming
 * stock at another, or a different amount of it — and splitting them into
 * separate screens means three places for the same mistake to be made
 * differently.
 */
class ManageStock extends Page
{
    use InteractsWithRecord;

    protected static string $resource = InventoryItemResource::class;

    protected static ?string $title = 'Move or correct stock';

    public const ADJUST   = 'adjust';
    public const TRANSFER = 'transfer';
    public const SEND     = 'send';

    public string $operation = self::ADJUST;

    public ?int $fromLocationId = null;
    public ?int $toLocationId   = null;

    /** The exact amount an adjustment should leave behind. */
    public ?string $newQuantity = null;

    /** How much a transfer should move. */
    public ?string $moveQuantity = null;

    public string $reason = '';

    public function getView(): string
    {
        return 'filament.resources.inventory-item-resource.pages.manage-stock';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorize('view', $this->record);

        // Start where the stock actually is. With one location that is the
        // whole question answered; with several it is still the likeliest.
        $this->fromLocationId = $this->stockRows()[0]['location_id'] ?? null;

        if (InventoryVisibility::destinationFor(auth()->user())) {
            $this->operation = self::SEND;
        }

        $this->syncFromLocation();
    }

    public function getSubheading(): ?string
    {
        return $this->record->name . ' — every change here is written to this item’s history.';
    }

    /**
     * Where this item is, and how much of it, limited to what you may see.
     *
     * @return array<int, array<string, mixed>>
     */
    public function stockRows(): array
    {
        $visible = InventoryVisibility::locationIdsFor(auth()->user());

        return InventoryStock::with('location')
            ->where('inventory_item_id', $this->record->id)
            ->when($visible !== null, fn ($q) => $q->whereIn('inventory_location_id', $visible))
            ->get()
            ->map(fn (InventoryStock $s) => [
                'location_id' => $s->inventory_location_id,
                'location'    => $s->location?->name ?? 'Unknown',
                'quantity'    => (float) $s->quantity,
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function getRowsProperty(): array
    {
        return $this->stockRows();
    }

    public function getAvailableProperty(): float
    {
        foreach ($this->stockRows() as $row) {
            if ($row['location_id'] === $this->fromLocationId) {
                return $row['quantity'];
            }
        }

        return 0.0;
    }

    /** @return array<int, string> */
    public function getSourceOptionsProperty(): array
    {
        $visible = InventoryVisibility::locationIdsFor(auth()->user());

        return InventoryLocation::where('status', 'active')
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /** @return array<int, string> */
    public function getDestinationOptionsProperty(): array
    {
        if ($this->operation === self::SEND) {
            $own = InventoryVisibility::destinationFor(auth()->user());

            return $own ? [$own->id => $own->name] : [];
        }

        return array_diff_key($this->sourceOptions, [$this->fromLocationId => true]);
    }

    public function setOperation(string $operation): void
    {
        if (! in_array($operation, [self::ADJUST, self::TRANSFER, self::SEND], true)) {
            return;
        }

        $this->operation = $operation;

        if ($operation === self::SEND) {
            $this->toLocationId = InventoryVisibility::destinationFor(auth()->user())?->id;
        }

        $this->syncFromLocation();
    }

    public function updatedFromLocationId(): void
    {
        $this->syncFromLocation();
    }

    /**
     * Keep the numbers describing the location that is actually selected.
     *
     * The adjustment box starts at what is already there, so leaving it alone
     * means "no change" rather than "set it to zero" — which is the difference
     * between a form you can open safely and one you cannot.
     */
    private function syncFromLocation(): void
    {
        $this->fromLocationId = $this->fromLocationId === null ? null : (int) $this->fromLocationId;
        $this->newQuantity    = (string) $this->available;

        if ($this->toLocationId === $this->fromLocationId) {
            $this->toLocationId = null;
        }
    }

    /** What pressing the button will do, in words, before it is pressed. */
    public function getEffectProperty(): string
    {
        if ($this->fromLocationId === null) {
            return 'Choose where the stock is first.';
        }

        $available = $this->available;

        if ($this->operation === self::ADJUST) {
            if ($this->newQuantity === null || $this->newQuantity === '') {
                return '—';
            }

            $change = (float) $this->newQuantity - $available;

            if ($change == 0.0) {
                return 'Leave it unchanged.';
            }

            return sprintf(
                '%s %s units — %s becomes %s.',
                $change > 0 ? 'Add' : 'Remove',
                number_format(abs($change)),
                number_format($available),
                number_format((float) $this->newQuantity),
            );
        }

        if ($this->moveQuantity === null || $this->moveQuantity === '' || ! $this->toLocationId) {
            return 'Choose how many and where they are going.';
        }

        $moving = (float) $this->moveQuantity;
        $names  = $this->sourceOptions + $this->destinationOptions;

        return sprintf(
            'Move %s units — %s keeps %s, %s receives them.',
            number_format($moving),
            $names[$this->fromLocationId] ?? 'here',
            number_format(max(0, $available - $moving)),
            $names[$this->toLocationId] ?? 'there',
        );
    }

    public function submit(): void
    {
        $service = app(InventoryService::class);

        try {
            // Re-checked here, not just filtered in the dropdown. The options
            // are rebuilt on every render, but a page left sitting is a page
            // whose options are old — and the field is public state a client
            // can set to anything. Without this a streamer could take stock
            // off another streamer's shelf by naming it.
            if (! array_key_exists((int) $this->fromLocationId, $this->sourceOptions)) {
                Notification::make()->title('You cannot take stock from there')->danger()->send();

                return;
            }

            $from = InventoryLocation::findOrFail($this->fromLocationId);

            if ($this->operation === self::ADJUST) {
                if ($this->reason === '') {
                    Notification::make()
                        ->title('Say why')
                        ->body('An adjustment without a reason is a number nobody can explain later.')
                        ->warning()
                        ->send();

                    return;
                }

                $service->adjustStock($this->record, $from, (float) $this->newQuantity, $this->reason);

                Notification::make()->title('Stock adjusted')->body($this->effect)->success()->send();
            } else {
                $allowed = $this->destinationOptions;

                if (! array_key_exists((int) $this->toLocationId, $allowed)) {
                    Notification::make()->title('You cannot send stock there')->danger()->send();

                    return;
                }

                $service->transferStock(
                    $this->record,
                    $from,
                    InventoryLocation::findOrFail($this->toLocationId),
                    (float) $this->moveQuantity,
                    $this->reason ?: 'Moved from the stock screen',
                );

                Notification::make()->title('Stock moved')->body($this->effect)->success()->send();
            }
        } catch (\RuntimeException | \Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        $this->reason       = '';
        $this->moveQuantity = null;
        $this->record->refresh();
        $this->syncFromLocation();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Back to item')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => InventoryItemResource::getUrl('view', ['record' => $this->record])),
        ];
    }
}
