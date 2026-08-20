<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryLocation;
use App\Models\Pallet;
use App\Models\PalletLine;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * Type a pallet's contents as a table, one row per line.
 *
 * The repeater on the edit form gives each line a card roughly a screen tall,
 * so a twelve-line pallet is a great deal of scrolling and the shape of the
 * delivery — which is the thing you are checking against the packing slip — is
 * never visible at once. Reading a slip is a columnar job: the same field for
 * every line, one after another. This is that.
 *
 * The optional per-line settings the repeater carries are handled once for the
 * batch instead of once per row, because in practice a delivery lands in one
 * place, and answering the same question twelve times is how people stop
 * answering it.
 */
class AddPalletLines extends Page
{
    // Supplies $record, resolveRecord() and getRecord() — a plain resource Page
    // has no record of its own, and this one is meaningless without a pallet.
    use InteractsWithRecord;

    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Add Manifest Lines';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public ?int $locationId = null;

    public function getView(): string
    {
        return 'filament.resources.pallet-resource.pages.add-pallet-lines';
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $this->authorize('update', $this->record);

        $this->locationId = InventoryLocation::defaultReceivingId();

        // Enough rows to start typing into without pressing anything first.
        $this->rows = [$this->blankRow(), $this->blankRow(), $this->blankRow()];
    }

    public function getSubheading(): ?string
    {
        $ref = $this->record->reference ?: ('#' . $this->record->id);

        return "Pallet {$ref} — type a line per item. Tab across, Enter for a new row.";
    }

    /** @return array<string, mixed> */
    private function blankRow(): array
    {
        return [
            'description'       => '',
            'is_container'      => '',
            'case_count'        => 1,
            'quantity_per_case' => 1,
            'unit_cost'         => null,
        ];
    }

    public function addRow(): void
    {
        $this->rows[] = $this->blankRow();
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);

        $this->rows = array_values($this->rows);

        // Never leave the table with nothing to type into.
        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
        }
    }

    /**
     * Rows with a name on them. Everything else is scaffolding.
     *
     * Blank rows are not an error — three are provided up front and a pallet
     * rarely has exactly three lines, so discarding them silently is the only
     * behaviour that does not punish using the page as intended.
     *
     * @return array<int, array<string, mixed>>
     */
    public function filledRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            fn ($row) => trim((string) ($row['description'] ?? '')) !== '',
        ));
    }

    /** @return array{lines: int, units: float, cost: float} */
    public function getTotalsProperty(): array
    {
        $lines = $this->filledRows();

        $units = array_sum(array_map(
            fn ($r) => (float) ($r['case_count'] ?: 0) * (float) ($r['quantity_per_case'] ?: 0),
            $lines,
        ));

        $cost = array_sum(array_map(
            fn ($r) => (float) ($r['case_count'] ?: 0) * (float) ($r['quantity_per_case'] ?: 0) * (float) ($r['unit_cost'] ?: 0),
            $lines,
        ));

        return ['lines' => count($lines), 'units' => $units, 'cost' => $cost];
    }

    public function save(): void
    {
        $rows = $this->filledRows();

        if ($rows === []) {
            Notification::make()
                ->title('Nothing to add')
                ->body('Give at least one line an item name.')
                ->warning()
                ->send();

            return;
        }

        // Continue the pallet's existing numbering rather than restarting it —
        // this page is usually reached to add to a pallet, not to start one.
        $nextNumber = ((int) $this->record->lines()->max('line_number')) + 1;

        DB::transaction(function () use ($rows, &$nextNumber) {
            foreach ($rows as $row) {
                PalletLine::create([
                    'pallet_id'         => $this->record->id,
                    'line_number'       => $nextNumber++,
                    'description'       => trim($row['description']),
                    // '' means "not sure yet", which is a real answer while
                    // reading a slip and must not become false.
                    'is_container'      => $row['is_container'] === '' ? null : (bool) $row['is_container'],
                    'case_count'        => (float) ($row['case_count'] ?: 1),
                    'quantity_per_case' => (float) ($row['quantity_per_case'] ?: 1),
                    'unit_cost'         => $row['unit_cost'] === null || $row['unit_cost'] === ''
                        ? null
                        : (float) $row['unit_cost'],
                    'inventory_location_id' => $this->locationId ?: null,
                ]);
            }
        });

        Notification::make()
            ->title(count($rows) . ' ' . str('line')->plural(count($rows)) . ' added')
            ->success()
            ->send();

        $this->redirect(PalletResource::getUrl('view', ['record' => $this->record]));
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
