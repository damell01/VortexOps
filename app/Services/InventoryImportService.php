<?php

namespace App\Services;

use App\Models\InventoryItem;
use App\Models\ProductIdentity;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

class InventoryImportService
{
    /** @var array<string,string> */
    private const HEADER_ALIASES = [
        'item' => 'name', 'item_name' => 'name', 'product' => 'name', 'product_name' => 'name', 'name' => 'name',
        'sku' => 'sku', 'item_sku' => 'sku',
        'barcode' => 'barcode', 'bar_code' => 'barcode',
        'upc' => 'upc', 'upc_code' => 'upc',
        'brand' => 'brand', 'sport' => 'sport', 'year' => 'year', 'set' => 'set_name', 'set_name' => 'set_name',
        'product_type' => 'product_type', 'type' => 'product_type', 'sold_as' => 'sold_as',
        'configuration' => 'configuration', 'manufacturer' => 'manufacturer', 'category' => 'category',
        'description' => 'description', 'unit_cost' => 'unit_cost', 'cost' => 'unit_cost',
        'average_cost' => 'average_cost', 'avg_cost' => 'average_cost', 'sale_price' => 'sale_price', 'price' => 'sale_price',
        'reorder_level' => 'reorder_level', 'reorder' => 'reorder_level', 'notes' => 'notes',
    ];

    /** @var array<int,string> */
    private const IMPORTABLE_FIELDS = [
        'name', 'sku', 'barcode', 'upc', 'brand', 'sport', 'year', 'set_name', 'product_type',
        'sold_as', 'configuration', 'manufacturer', 'category', 'description', 'unit_cost',
        'average_cost', 'sale_price', 'reorder_level', 'notes',
    ];

    public function preview(string $path): array
    {
        $workbook = IOFactory::load($path);
        $sheet = $workbook->getActiveSheet()->toArray(null, true, true, false);
        $workbook->disconnectWorksheets();
        unset($workbook);

        if (count($sheet) < 2) {
            return ['headers' => [], 'rows' => [], 'summary' => $this->emptySummary(), 'warnings' => ['The spreadsheet does not contain any data rows.']];
        }

        $headers = array_map(fn ($value) => $this->normalizeHeader((string) $value), array_shift($sheet));
        $mappedHeaders = array_map(fn ($header) => self::HEADER_ALIASES[$header] ?? null, $headers);
        $recognized = array_values(array_filter(array_unique($mappedHeaders)));

        if (! in_array('name', $recognized, true) && ! in_array('sku', $recognized, true) && ! in_array('barcode', $recognized, true) && ! in_array('upc', $recognized, true)) {
            return ['headers' => $headers, 'rows' => [], 'summary' => $this->emptySummary(), 'warnings' => ['No recognizable item identifier column was found. Include Item Name, SKU, Barcode, or UPC.']];
        }

        $reviewRows = [];
        foreach ($sheet as $index => $rawRow) {
            $mapped = $this->mapRow($mappedHeaders, $rawRow);
            if ($this->rowIsEmpty($mapped)) {
                continue;
            }

            $reviewRows[] = $this->classifyRow($mapped, $index + 2);
        }

        $reviewRows = $this->markSheetDuplicates($reviewRows);

        return [
            'headers' => $recognized,
            'rows' => $reviewRows,
            'summary' => $this->summarize($reviewRows),
            'warnings' => [],
        ];
    }

    public function import(array $reviewRows, bool $updateExisting = false): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'conflicts' => 0, 'errors' => []];

        foreach ($reviewRows as $row) {
            $status = $row['status'] ?? 'invalid';
            $data = $row['data'] ?? [];

            if ($status === 'conflict' || $status === 'invalid') {
                $result['conflicts']++;
                continue;
            }

            if ($status === 'existing' && ! $updateExisting) {
                $result['skipped']++;
                continue;
            }

            try {
                DB::transaction(function () use ($status, $data, $row, $updateExisting, &$result): void {
                    if ($status === 'new') {
                        $item = InventoryItem::create($this->cleanCreateData($data));
                        $this->syncIdentities($item, $data);
                        $result['created']++;
                        return;
                    }

                    if ($status === 'existing' && $updateExisting) {
                        $item = InventoryItem::find($row['existing_id'] ?? null);
                        if (! $item) {
                            throw new \RuntimeException('The matched inventory item no longer exists.');
                        }

                        $item->update($this->cleanUpdateData($data));
                        $this->syncIdentities($item, $data);
                        $result['updated']++;
                    }
                });
            } catch (\Throwable $e) {
                $result['errors'][] = 'Row ' . ($row['sheet_row'] ?? '?') . ': ' . $e->getMessage();
            }
        }

        return $result;
    }

    private function classifyRow(array $data, int $sheetRow): array
    {
        $matches = collect();

        foreach (['sku', 'barcode', 'upc'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') {
                continue;
            }

            if ($field === 'sku') {
                $match = InventoryItem::query()->where('sku', $value)->first();
            } else {
                $match = InventoryItem::query()
                    ->where(fn ($query) => $query->where('barcode', $value)->orWhere('upc', $value))
                    ->first();

                $match ??= ProductIdentity::query()
                    ->whereIn('type', ProductIdentity::SCANNABLE_TYPES)
                    ->where('value', $value)
                    ->with('product')
                    ->first()?->product;
            }

            if ($match) {
                $matches->put($match->id, $match);
            }
        }

        if ($matches->count() > 1) {
            return [
                'sheet_row' => $sheetRow,
                'status' => 'conflict',
                'reason' => 'Identifiers on this row match more than one existing inventory item.',
                'data' => $data,
                'existing_id' => null,
                'existing_name' => null,
                'changes' => [],
            ];
        }

        $match = $matches->first();

        if (! $match && filled($data['name'] ?? null)) {
            $match = InventoryItem::query()
                ->whereRaw('LOWER(name) = ?', [Str::lower(trim((string) $data['name']))])
                ->first();
        }

        if ($match) {
            return [
                'sheet_row' => $sheetRow,
                'status' => 'existing',
                'reason' => $this->matchReason($match, $data),
                'data' => $data,
                'existing_id' => $match->id,
                'existing_name' => $match->name,
                'changes' => $this->changesFor($match, $data),
            ];
        }

        if (blank($data['name'] ?? null)) {
            return [
                'sheet_row' => $sheetRow,
                'status' => 'invalid',
                'reason' => 'New items need an item name.',
                'data' => $data,
                'existing_id' => null,
                'existing_name' => null,
                'changes' => [],
            ];
        }

        return [
            'sheet_row' => $sheetRow,
            'status' => 'new',
            'reason' => 'No existing SKU, barcode, UPC, or exact item name was found.',
            'data' => $data,
            'existing_id' => null,
            'existing_name' => null,
            'changes' => [],
        ];
    }

    private function markSheetDuplicates(array $rows): array
    {
        $seen = [];

        foreach ($rows as $index => $row) {
            if (($row['status'] ?? null) !== 'new') {
                continue;
            }

            $keys = [];
            foreach (['sku', 'barcode', 'upc'] as $field) {
                $value = trim((string) ($row['data'][$field] ?? ''));
                if ($value !== '') {
                    $keys[] = $field . ':' . Str::lower($value);
                }
            }

            if ($keys === [] && filled($row['data']['name'] ?? null)) {
                $keys[] = 'name:' . Str::lower(trim((string) $row['data']['name']));
            }

            foreach ($keys as $key) {
                if (isset($seen[$key])) {
                    $rows[$index]['status'] = 'conflict';
                    $rows[$index]['reason'] = 'Duplicate identifier appears elsewhere in this spreadsheet (row ' . $seen[$key] . ').';
                    $firstIndex = collect($rows)->search(fn ($candidate) => ($candidate['sheet_row'] ?? null) === $seen[$key]);
                    if ($firstIndex !== false && ($rows[$firstIndex]['status'] ?? null) === 'new') {
                        $rows[$firstIndex]['status'] = 'conflict';
                        $rows[$firstIndex]['reason'] = 'Duplicate identifier appears elsewhere in this spreadsheet (row ' . $row['sheet_row'] . ').';
                    }
                } else {
                    $seen[$key] = $row['sheet_row'];
                }
            }
        }

        return $rows;
    }

    private function matchReason(InventoryItem $item, array $data): string
    {
        if (filled($data['sku'] ?? null) && $item->sku === $data['sku']) return 'Matched existing SKU.';
        if (filled($data['barcode'] ?? null) && in_array($data['barcode'], [$item->barcode, $item->upc], true)) return 'Matched existing barcode.';
        if (filled($data['upc'] ?? null) && in_array($data['upc'], [$item->barcode, $item->upc], true)) return 'Matched existing UPC.';
        return 'Matched exact item name.';
    }

    private function changesFor(InventoryItem $item, array $data): array
    {
        $changes = [];
        foreach (self::IMPORTABLE_FIELDS as $field) {
            if (! array_key_exists($field, $data) || $data[$field] === null || $data[$field] === '') continue;
            if (in_array($field, ['sku', 'barcode', 'upc'], true)) continue;
            $current = (string) ($item->{$field} ?? '');
            $incoming = (string) $data[$field];
            if ($current !== $incoming) {
                $changes[$field] = ['from' => $current, 'to' => $incoming];
            }
        }
        return $changes;
    }

    private function cleanCreateData(array $data): array
    {
        $clean = Arr::only($data, self::IMPORTABLE_FIELDS);
        $clean = array_filter($clean, fn ($value) => $value !== null && $value !== '');
        $clean['is_active'] = true;
        return $clean;
    }

    private function cleanUpdateData(array $data): array
    {
        $clean = Arr::only($data, array_diff(self::IMPORTABLE_FIELDS, ['sku', 'barcode', 'upc']));
        return array_filter($clean, fn ($value) => $value !== null && $value !== '');
    }

    private function syncIdentities(InventoryItem $item, array $data): void
    {
        foreach (['barcode' => ProductIdentity::TYPE_BARCODE, 'upc' => ProductIdentity::TYPE_UPC] as $field => $type) {
            $value = trim((string) ($data[$field] ?? ''));
            if ($value === '') continue;

            $otherOwner = ProductIdentity::query()
                ->whereIn('type', ProductIdentity::SCANNABLE_TYPES)
                ->where('value', $value)
                ->where('product_id', '!=', $item->id)
                ->exists();

            if ($otherOwner) continue;

            ProductIdentity::firstOrCreate([
                'product_id' => $item->id,
                'vendor_id' => null,
                'type' => $type,
                'value' => $value,
            ]);
        }
    }

    private function mapRow(array $mappedHeaders, array $rawRow): array
    {
        $mapped = [];
        foreach ($mappedHeaders as $index => $field) {
            if (! $field || ! in_array($field, self::IMPORTABLE_FIELDS, true)) continue;
            $value = $rawRow[$index] ?? null;
            if (is_string($value)) $value = trim($value);
            if ($value === '') $value = null;
            $mapped[$field] = $value;
        }
        return $mapped;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->trim()->lower()->replace(['/', '-', '.'], ' ')->replaceMatches('/\s+/', '_')->toString();
    }

    private function rowIsEmpty(array $row): bool
    {
        return collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isEmpty();
    }

    private function summarize(array $rows): array
    {
        $collection = collect($rows);
        return [
            'total' => $collection->count(),
            'new' => $collection->where('status', 'new')->count(),
            'existing' => $collection->where('status', 'existing')->count(),
            'conflict' => $collection->whereIn('status', ['conflict', 'invalid'])->count(),
            'updates' => $collection->where('status', 'existing')->filter(fn ($row) => ! empty($row['changes']))->count(),
        ];
    }

    private function emptySummary(): array
    {
        return ['total' => 0, 'new' => 0, 'existing' => 0, 'conflict' => 0, 'updates' => 0];
    }
}
