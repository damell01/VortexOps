<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryLocation;
use App\Services\PalletCorrectionService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

/**
 * Everything this pallet brought into inventory, in one editable table.
 *
 * A pallet is typed from a packing slip and scanned in a warehouse, so some of
 * it is wrong by the time it lands: a cost mistyped, an item carrying the
 * vendor's wording rather than yours, a miscount, a line put in the wrong
 * place. Fixing those one product page at a time means leaving the pallet,
 * finding each item, and losing the context that told you it was wrong — which
 * is why it mostly does not get done.
 *
 * The corrections are not all the same kind of thing, and this page does not
 * pretend they are. Cost and name are facts about a purchase and are simply
 * rewritten. Quantity and location describe where stock is, and stock is a
 * ledger: those go through the same adjustment and transfer paths the rest of
 * the app uses, so the totals still add up and the correction is visible as a
 * correction rather than as a receipt that quietly changed.
 */
class PalletItems extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Items from this pallet';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** Row currently being corrected, by line id. Null when the table is idle. */
    public ?int $editingLineId = null;

    /** @var array<string, mixed> */
    public array $draft = [];

    public function getView(): string
    {
        return 'filament.resources.pallet-resource.pages.pallet-items';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorize('view', $this->record);

        $this->refreshRows();
    }

    public function getSubheading(): ?string
    {
        return $this->record->displayName()
            . ' — fix costs, names, counts and locations without leaving the pallet.';
    }

    public function refreshRows(): void
    {
        $this->record->refresh();
        $this->rows = app(PalletCorrectionService::class)->itemsFrom($this->record);
    }

    /** @return array<int, string> */
    public function getLocationOptionsProperty(): array
    {
        return InventoryLocation::activeOptions();
    }

    /** @return array{units: float, cost: float, lines: int, outstanding: int} */
    public function getTotalsProperty(): array
    {
        return [
            'lines'       => count($this->rows),
            'units'       => array_sum(array_column($this->rows, 'units')),
            'cost'        => array_sum(array_column($this->rows, 'line_total')),
            'outstanding' => count(array_filter($this->rows, fn ($r) => ! $r['complete'])),
        ];
    }

    public function edit(int $lineId): void
    {
        $row = collect($this->rows)->firstWhere('line_id', $lineId);

        if (! $row) {
            return;
        }

        $this->editingLineId = $lineId;
        $this->draft = [
            'name'        => $row['name'],
            'unit_cost'   => $row['unit_cost'],
            'units'       => $row['in_stock'],
            'location_id' => $row['location_id'],
            'reason'      => '',
        ];
    }

    public function cancelEdit(): void
    {
        $this->editingLineId = null;
        $this->draft = [];
    }

    /**
     * Apply whatever actually changed, and nothing else.
     *
     * Each field goes through its own path, and a field left alone writes
     * nothing — otherwise saving a renamed row would also log a stock
     * adjustment of zero and a transfer to the location it was already in,
     * which is how an audit trail fills up with events that never happened.
     */
    public function save(): void
    {
        $line = $this->record->lines()->find($this->editingLineId);

        if (! $line) {
            Notification::make()->title('That line is no longer on this pallet')->danger()->send();
            $this->cancelEdit();

            return;
        }

        $row      = collect($this->rows)->firstWhere('line_id', $this->editingLineId);
        $service  = app(PalletCorrectionService::class);
        $happened = [];

        try {
            if (trim((string) $this->draft['name']) !== $row['name']) {
                $service->renameItem($line, (string) $this->draft['name']);
                $happened[] = 'renamed';
            }

            if ((float) $this->draft['unit_cost'] !== (float) $row['unit_cost']) {
                $service->correctUnitCost($line, (float) $this->draft['unit_cost']);
                $happened[] = 'cost corrected';
            }

            if ((float) $this->draft['units'] !== (float) $row['in_stock']) {
                $service->correctQuantity(
                    $line,
                    (float) $this->draft['units'],
                    $this->draft['reason'] ?: null,
                );
                $happened[] = 'stock adjusted';
            }

            if ((int) $this->draft['location_id'] !== (int) $row['location_id']) {
                $service->moveToLocation(
                    $line,
                    InventoryLocation::findOrFail($this->draft['location_id']),
                    $this->draft['reason'] ?: null,
                );
                $happened[] = 'moved';
            }
        } catch (\RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($happened === [] ? 'Nothing changed' : ucfirst(implode(', ', $happened)))
            ->body($happened === []
                ? 'Every field matched what was already there.'
                : 'Quantity and location changes are recorded in this item’s history.')
            ->success()
            ->send();

        $this->cancelEdit();
        $this->refreshRows();
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back_to_pallet')
                ->label('Back to pallet')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(fn () => PalletResource::getUrl('view', ['record' => $this->record])),
        ];
    }
}
