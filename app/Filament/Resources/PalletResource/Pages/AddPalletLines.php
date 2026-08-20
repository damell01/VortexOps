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

    protected static ?string $title = 'Manifest Lines';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /**
     * Lines removed from the table but still in the database.
     *
     * Deleting on click would destroy a line before anyone pressed save, so a
     * mis-click could not be undone by leaving the page — which is the one
     * recovery people reach for.
     *
     * @var array<int, int>
     */
    public array $deletedIds = [];

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

        // Existing lines first, so this edits the manifest rather than only
        // appending to it — otherwise a correction means going back to the
        // stack-of-cards form this page exists to replace.
        $this->rows = $this->record->lines()
            ->orderBy('line_number')
            ->get()
            ->map(fn (PalletLine $line) => [
                'id'                => $line->id,
                'description'       => (string) $line->description,
                'inventory_item_id' => (string) ($line->inventory_item_id ?? ''),
                'is_container'      => $line->is_container === null ? '' : (string) (int) $line->is_container,
                'case_count'        => $line->case_count,
                'quantity_per_case' => $line->quantity_per_case,
                'unit_cost'         => $line->unit_cost,
            ])
            ->all();

        // Always leave somewhere to type without pressing anything first.
        $blanks = max(1, 3 - count($this->rows));
        for ($i = 0; $i < $blanks; $i++) {
            $this->rows[] = $this->blankRow();
        }
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
            'id'                => null,
            'description'       => '',
            'inventory_item_id' => '',
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
        if ($id = ($this->rows[$index]['id'] ?? null)) {
            $this->deletedIds[] = (int) $id;
        }

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

    /**
     * Items a line can be pointed at, for "more stock of something I already
     * have". Blank is the common answer and stays first.
     *
     * @return array<int, string>
     */
    public function getItemOptionsProperty(): array
    {
        return \App\Models\InventoryItem::where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * How many things a row describes.
     *
     * Only a case line multiplies. A single item has a quantity, so treating
     * its units-per-case as a multiplier would silently scale it by whatever
     * was last left in a field the row no longer shows.
     *
     * @param  array<string, mixed>  $row
     */
    private function unitsFor(array $row): float
    {
        $count = (float) ($row['case_count'] ?: 0);

        return ($row['is_container'] ?? '') === '1'
            ? $count * (float) ($row['quantity_per_case'] ?: 0)
            : $count;
    }

    /** @return array{lines: int, units: float, cost: float} */
    public function getTotalsProperty(): array
    {
        $lines = $this->filledRows();

        $units = array_sum(array_map(fn ($r) => $this->unitsFor($r), $lines));

        $cost = array_sum(array_map(
            fn ($r) => $this->unitsFor($r) * (float) ($r['unit_cost'] ?: 0),
            $lines,
        ));

        return ['lines' => count($lines), 'units' => $units, 'cost' => $cost];
    }

    public function save(): void
    {
        $rows = $this->filledRows();

        if ($rows === [] && $this->deletedIds === []) {
            Notification::make()
                ->title('Nothing to save')
                ->body('Give at least one line an item name.')
                ->warning()
                ->send();

            return;
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($rows, &$created, &$updated) {
            if ($this->deletedIds !== []) {
                $this->record->lines()->whereIn('id', $this->deletedIds)->delete();
            }

            // Renumbered from the table's order, so dragging or deleting leaves
            // the numbering matching what is on screen rather than developing
            // gaps nobody can explain.
            $number = 1;

            foreach ($rows as $row) {
                $attributes = [
                    'line_number'       => $number++,
                    'description'       => trim($row['description']),
                    // '' means "not sure yet", which is a real answer while
                    // reading a slip and must not become false.
                    'is_container'      => $row['is_container'] === '' ? null : (bool) $row['is_container'],
                    // Blank means "something new" — scanning links or creates
                    // it when the pallet lands, which is the normal path.
                    'inventory_item_id' => ($row['inventory_item_id'] ?? '') === ''
                        ? null
                        : (int) $row['inventory_item_id'],
                    'case_count'        => (float) ($row['case_count'] ?: 1),
                    // Forced to 1 for anything that is not a case, so the stored
                    // line means what the screen said rather than carrying a
                    // multiplier from a field it stopped showing.
                    'quantity_per_case' => $row['is_container'] === '1'
                        ? (float) ($row['quantity_per_case'] ?: 1)
                        : 1,
                    'unit_cost'         => $row['unit_cost'] === null || $row['unit_cost'] === ''
                        ? null
                        : (float) $row['unit_cost'],
                ];

                $existing = ($row['id'] ?? null)
                    ? $this->record->lines()->whereKey($row['id'])->first()
                    : null;

                if ($existing) {
                    // The location is only forced onto new lines: a line already
                    // routed somewhere deliberately should not be swept along by
                    // a batch default set for the rest.
                    $existing->update($attributes);
                    $updated++;

                    $this->stubCases($existing);

                    continue;
                }

                $line = $this->record->lines()->create($attributes + [
                    'inventory_location_id' => $this->locationId ?: null,
                ]);
                $created++;

                $this->stubCases($line);
            }
        });

        $this->deletedIds = [];

        Notification::make()
            ->title('Manifest saved')
            ->body(trim(implode(', ', array_filter([
                $created ? "{$created} added" : null,
                $updated ? "{$updated} updated" : null,
            ]))) ?: 'No changes')
            ->success()
            ->send();

        $this->redirect(PalletResource::getUrl('view', ['record' => $this->record]));
    }

    /**
     * Give a saved line the case stubs a scanner confirms against.
     *
     * A staged line is only useful if it can be scanned the moment the pallet
     * lands, and a scan confirms one case at a time — which needs the cases to
     * exist. Without this a line typed here could only be received in bulk,
     * which loses the part-delivery count the receiving screen is built around.
     *
     * The generator only creates the shortfall, so raising a line's case count
     * later tops it up and saving an unchanged line does nothing.
     */
    private function stubCases(PalletLine $line): void
    {
        app(\App\Services\ReceivingService::class)->generateExpectedCases($line->refresh());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
