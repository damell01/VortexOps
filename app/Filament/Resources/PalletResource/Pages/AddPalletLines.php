<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryLocation;
use App\Models\PalletLine;
use App\Models\Product;
use App\Support\ProductSearch;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;

class AddPalletLines extends Page
{
    use InteractsWithRecord;

    protected static string $resource = PalletResource::class;
    protected static ?string $title = 'Manifest Lines';

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<int, int> */
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

        $this->rows = $this->record->lines()
            ->orderBy('line_number')
            ->get()
            ->map(fn (PalletLine $line) => [
                'id' => $line->id,
                'description' => (string) $line->description,
                'inventory_item_id' => (string) ($line->inventory_item_id ?? ''),
                'is_container' => $line->is_container === null ? '' : (string) (int) $line->is_container,
                'case_count' => $line->case_count,
                'quantity_per_case' => $line->quantity_per_case,
                'unit_cost' => $line->unit_cost,
            ])
            ->all();

        $blanks = max(1, 3 - count($this->rows));
        for ($i = 0; $i < $blanks; $i++) {
            $this->rows[] = $this->blankRow();
        }
    }

    public function getSubheading(): ?string
    {
        return $this->record->displayName()
            . ' — search inventory to restock something you already carry, or type a new item.';
    }

    /** @return array<string, mixed> */
    private function blankRow(): array
    {
        return [
            'id' => null,
            'description' => '',
            'inventory_item_id' => '',
            'is_container' => '',
            'case_count' => 1,
            'quantity_per_case' => 1,
            'unit_cost' => null,
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

        if ($this->rows === []) {
            $this->rows = [$this->blankRow()];
        }
    }

    /**
     * Async search endpoint for the manifest combobox. Nothing is preloaded,
     * so a catalog with thousands of products still sends only a dozen rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function searchProducts(string $term): array
    {
        return ProductSearch::search($term);
    }

    /**
     * Catalog picker endpoint: an empty term lists the catalog alphabetically
     * instead of nothing, so the picker shows something to scroll the moment
     * it opens rather than an empty box waiting for the first keystroke.
     *
     * @return array<int, array<string, mixed>>
     */
    public function browseProducts(string $term = ''): array
    {
        return ProductSearch::browse($term);
    }

    /**
     * Link a manifest row to an existing product and fill the fields we already
     * know. This is the important restock path: selecting "Test 2" means the
     * receiver should never have to type "Test 2" again in the item box.
     */
    public function selectProduct(int $index, int $productId): void
    {
        if (! isset($this->rows[$index])) {
            return;
        }

        $product = Product::query()
            ->where('is_active', true)
            ->findOrFail($productId);

        $this->rows[$index]['inventory_item_id'] = (string) $product->id;
        $this->rows[$index]['description'] = (string) $product->name;
        $this->rows[$index]['is_container'] = (string) (int) $product->is_container;

        if (($this->rows[$index]['unit_cost'] ?? null) === null || $this->rows[$index]['unit_cost'] === '') {
            $cost = $product->effectiveCost();
            if ($cost > 0) {
                $this->rows[$index]['unit_cost'] = $cost;
            }
        }
    }

    /** Unlink without erasing the description somebody may want to keep/edit. */
    public function unlinkProduct(int $index): void
    {
        if (isset($this->rows[$index])) {
            $this->rows[$index]['inventory_item_id'] = '';
        }
    }

    /** @return array<int, array{id:int,name:string,sku:?string}> */
    public function getLinkedProductsProperty(): array
    {
        $ids = collect($this->rows)
            ->pluck('inventory_item_id')
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        return Product::query()
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'sku'])
            ->keyBy('id')
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function filledRows(): array
    {
        return array_values(array_filter(
            $this->rows,
            fn ($row) => trim((string) ($row['description'] ?? '')) !== '',
        ));
    }

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
        $units = array_sum(array_map(fn ($row) => $this->unitsFor($row), $lines));
        $cost = array_sum(array_map(
            fn ($row) => $this->unitsFor($row) * (float) ($row['unit_cost'] ?: 0),
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

            $number = 1;
            foreach ($rows as $row) {
                $attributes = [
                    'line_number' => $number++,
                    'description' => trim($row['description']),
                    'is_container' => $row['is_container'] === '' ? null : (bool) $row['is_container'],
                    'inventory_item_id' => ($row['inventory_item_id'] ?? '') === ''
                        ? null
                        : (int) $row['inventory_item_id'],
                    'case_count' => (float) ($row['case_count'] ?: 1),
                    'quantity_per_case' => $row['is_container'] === '1'
                        ? (float) ($row['quantity_per_case'] ?: 1)
                        : 1,
                    'unit_cost' => $row['unit_cost'] === null || $row['unit_cost'] === ''
                        ? null
                        : (float) $row['unit_cost'],
                ];

                $existing = ($row['id'] ?? null)
                    ? $this->record->lines()->whereKey($row['id'])->first()
                    : null;

                if ($existing) {
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

    private function stubCases(PalletLine $line): void
    {
        app(\App\Services\ReceivingService::class)->generateExpectedCases($line->refresh());
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
