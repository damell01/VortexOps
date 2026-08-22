<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Resources\InventoryItemResource;
use App\Models\InventoryLocation;
use App\Models\InventoryStock;
use App\Models\Show;
use App\Services\InventoryService;
use App\Support\InventoryVisibility;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ManageStock extends Page
{
    use InteractsWithRecord;

    protected static string $resource = InventoryItemResource::class;
    protected static ?string $title = 'Move or correct stock';

    public const ADJUST = 'adjust';
    public const TRANSFER = 'transfer';
    public const SEND = 'send';

    public string $operation = self::ADJUST;
    public ?int $fromLocationId = null;
    public ?int $toLocationId = null;
    public ?string $newQuantity = null;
    public ?string $moveQuantity = null;
    public string $reason = '';

    /** Structured reason for manual count reductions. */
    public string $adjustmentType = 'correction';
    public ?int $relatedShowId = null;

    public function getView(): string
    {
        return 'filament.resources.inventory-item-resource.pages.manage-stock';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->authorize('view', $this->record);
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

    public function stockRows(): array
    {
        $visible = InventoryVisibility::locationIdsFor(auth()->user());

        return InventoryStock::with('location')
            ->where('inventory_item_id', $this->record->id)
            ->when($visible !== null, fn ($q) => $q->whereIn('inventory_location_id', $visible))
            ->get()
            ->map(fn (InventoryStock $s) => [
                'location_id' => $s->inventory_location_id,
                'location' => $s->location?->name ?? 'Unknown',
                'quantity' => (float) $s->quantity,
            ])
            ->sortByDesc('quantity')
            ->values()
            ->all();
    }

    public function getRowsProperty(): array
    {
        return $this->stockRows();
    }

    public function getAvailableProperty(): float
    {
        foreach ($this->stockRows() as $row) {
            if ($row['location_id'] === $this->fromLocationId) return $row['quantity'];
        }
        return 0.0;
    }

    public function getSourceOptionsProperty(): array
    {
        $visible = InventoryVisibility::locationIdsFor(auth()->user());

        return InventoryLocation::where('status', 'active')
            ->when($visible !== null, fn ($q) => $q->whereIn('id', $visible))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function getDestinationOptionsProperty(): array
    {
        if ($this->operation === self::SEND) {
            $own = InventoryVisibility::destinationFor(auth()->user());
            return $own ? [$own->id => $own->name] : [];
        }

        return array_diff_key($this->sourceOptions, [$this->fromLocationId => true]);
    }

    public function getAdjustmentTypeOptionsProperty(): array
    {
        return [
            'correction' => 'Inventory Correction',
            'giveaway' => 'Giveaway',
            'promo' => 'Promo / Bonus',
            'loss' => 'Lost / Missing',
            'internal_use' => 'Internal Use',
            'other' => 'Other',
        ];
    }

    public function getRelatedShowOptionsProperty(): array
    {
        return Show::query()
            ->inChannelContext()
            ->whereDate('show_date', '>=', today()->subDays(90))
            ->whereDate('show_date', '<=', today()->addDays(7))
            ->orderByDesc('show_date')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (Show $show) => [
                $show->id => ($show->show_date?->format('M j') ?? '—') . ' — ' . $show->title,
            ])
            ->all();
    }

    public function setOperation(string $operation): void
    {
        if (! in_array($operation, [self::ADJUST, self::TRANSFER, self::SEND], true)) return;

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

    private function syncFromLocation(): void
    {
        $this->fromLocationId = $this->fromLocationId === null ? null : (int) $this->fromLocationId;
        $this->newQuantity = (string) $this->available;
        if ($this->toLocationId === $this->fromLocationId) $this->toLocationId = null;
    }

    public function getEffectProperty(): string
    {
        if ($this->fromLocationId === null) return 'Choose where the stock is first.';

        $available = $this->available;

        if ($this->operation === self::ADJUST) {
            if ($this->newQuantity === null || $this->newQuantity === '') return '—';

            $change = (float) $this->newQuantity - $available;
            if ($change == 0.0) return 'Leave it unchanged.';

            $label = $this->adjustmentTypeOptions[$this->adjustmentType] ?? 'Adjustment';

            return sprintf(
                '%s %s units — %s becomes %s. Reason: %s.',
                $change > 0 ? 'Add' : 'Remove',
                number_format(abs($change)),
                number_format($available),
                number_format((float) $this->newQuantity),
                $label,
            );
        }

        if ($this->moveQuantity === null || $this->moveQuantity === '' || ! $this->toLocationId) {
            return 'Choose how many and where they are going.';
        }

        $moving = (float) $this->moveQuantity;
        $names = $this->sourceOptions + $this->destinationOptions;

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
            if (! array_key_exists((int) $this->fromLocationId, $this->sourceOptions)) {
                Notification::make()->title('You cannot take stock from there')->danger()->send();
                return;
            }

            $from = InventoryLocation::findOrFail($this->fromLocationId);

            if ($this->operation === self::ADJUST) {
                $newQuantity = (float) $this->newQuantity;
                if ($newQuantity < 0) {
                    Notification::make()->title('Quantity cannot be negative')->danger()->send();
                    return;
                }

                if (! array_key_exists($this->adjustmentType, $this->adjustmentTypeOptions)) {
                    Notification::make()->title('Choose a valid adjustment reason')->danger()->send();
                    return;
                }

                $reductionOnly = ['giveaway', 'promo', 'loss', 'internal_use'];
                if (in_array($this->adjustmentType, $reductionOnly, true) && $newQuantity >= $this->available) {
                    Notification::make()
                        ->title('This reason should remove stock')
                        ->body('Set the new quantity below the current quantity, or choose Inventory Correction.')
                        ->warning()
                        ->send();
                    return;
                }

                if ($this->adjustmentType === 'other' && trim($this->reason) === '') {
                    Notification::make()->title('Add a note for Other')->warning()->send();
                    return;
                }

                $label = $this->adjustmentTypeOptions[$this->adjustmentType];
                $reason = $label . (trim($this->reason) !== '' ? ' — ' . trim($this->reason) : '');

                $service->adjustStock(
                    $this->record,
                    $from,
                    $newQuantity,
                    $reason,
                    $this->adjustmentType === 'correction' ? 'adjustment' : $this->adjustmentType,
                    $this->relatedShowId ? 'show' : null,
                    $this->relatedShowId,
                );

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

        $this->reason = '';
        $this->adjustmentType = 'correction';
        $this->relatedShowId = null;
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
