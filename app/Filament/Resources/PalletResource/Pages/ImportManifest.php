<?php

namespace App\Filament\Resources\PalletResource\Pages;

use App\Filament\Resources\PalletResource;
use App\Models\InventoryItem;
use App\Models\Pallet;
use App\Models\PalletLine;
use App\Models\Setting;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;

class ImportManifest extends Page
{
    use WithFileUploads;

    protected static string $resource = PalletResource::class;

    protected static ?string $title = 'Import Manifest';

    public Pallet $record;

    /** @var 'upload'|'mapping'|'preview'|'done' */
    public string $stage = 'upload';

    public $manifestFile = null;

    /** @var list<string> CSV header row */
    public array $headers = [];

    /** @var array<string, string> csvColumn => target field (or '') */
    public array $columnMapping = [];

    public bool $saveMapping = false;

    /** @var list<array<string,mixed>> first 5 preview rows with _matched key */
    public array $previewRows = [];

    /** @var list<array<string,string|null>> all data rows (set during import) */
    public array $allRows = [];

    public int $created   = 0;
    public int $matched   = 0;
    public int $unmatched = 0;

    /** @var string|null error shown on mapping stage */
    public ?string $mappingError = null;

    public function getView(): string
    {
        return 'filament.pages.import-manifest';
    }

    public function mount(Pallet $record): void
    {
        $this->record = $record->load('vendor');
    }

    // ── Stage 1: file uploaded ────────────────────────────────────────────────

    public function updatedManifestFile(): void
    {
        if (! $this->manifestFile) {
            return;
        }

        $path    = $this->manifestFile->getRealPath();
        $handle  = fopen($path, 'r');
        if (! $handle) {
            return;
        }

        $firstRow = fgetcsv($handle);

        // Peek at a few data rows for the preview later
        $dataRows = [];
        for ($i = 0; $i < 10 && ($row = fgetcsv($handle)) !== false; $i++) {
            $dataRows[] = $row;
        }
        fclose($handle);

        if (! $firstRow) {
            return;
        }

        $this->headers = array_map('trim', $firstRow);

        // Load saved mapping for this vendor (if any)
        $saved = $this->loadSavedMapping();

        $this->columnMapping = [];
        foreach ($this->headers as $header) {
            $this->columnMapping[$header] = $saved[$header] ?? $this->guessField($header);
        }

        $this->mappingError = null;
        $this->stage        = 'mapping';
    }

    // ── Stage 2: confirm mapping → build preview ──────────────────────────────

    public function goToPreview(): void
    {
        if (! in_array('description', $this->columnMapping, true)) {
            $this->mappingError = 'You must map at least one column to "Description / Item Name" before previewing.';
            return;
        }

        $this->mappingError = null;

        if ($this->saveMapping) {
            $this->persistMapping();
        }

        // Re-read the file
        $path   = $this->manifestFile->getRealPath();
        $handle = fopen($path, 'r');
        fgetcsv($handle); // skip header

        $this->previewRows = [];
        $this->allRows     = [];

        while (($raw = fgetcsv($handle)) !== false) {
            $row = $this->applyMapping($raw);
            if (($row['description'] ?? '') === '') {
                continue;
            }
            $item          = $this->findMatch($row);
            $row['_matched'] = $item?->name;
            $this->allRows[] = $row;
        }
        fclose($handle);

        $this->previewRows = array_slice($this->allRows, 0, 5);
        $this->stage       = 'preview';
    }

    // ── Stage 3: run the import ───────────────────────────────────────────────

    public function import(): void
    {
        $pallet   = $this->record;
        $nextLine = ($pallet->lines()->max('line_number') ?? 0) + 1;

        $created  = 0;
        $matched  = 0;

        DB::transaction(function () use ($pallet, &$nextLine, &$created, &$matched) {
            foreach ($this->allRows as $row) {
                $description = $row['description'] ?? '';
                if ($description === '') {
                    continue;
                }

                $item = $this->findMatch($row);

                PalletLine::create([
                    'pallet_id'         => $pallet->id,
                    'line_number'       => $nextLine++,
                    'description'       => $description,
                    'case_count'        => max(1, (int) ($row['case_count'] ?? 1)),
                    'unit_cost'         => (float) str_replace(['$', ','], '', $row['unit_cost'] ?? '0') ?: ($item?->unit_cost ?? 0),
                    'inventory_item_id' => $item?->id,
                    'notes'             => ($row['notes'] ?? '') ?: null,
                ]);

                $created++;
                if ($item) {
                    $matched++;
                }
            }
        });

        $this->created   = $created;
        $this->matched   = $matched;
        $this->unmatched = $created - $matched;
        $this->stage     = 'done';
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** @param list<string> $raw CSV data row */
    private function applyMapping(array $raw): array
    {
        $out = [];
        foreach ($this->headers as $idx => $header) {
            $field = $this->columnMapping[$header] ?? '';
            if ($field !== '' && isset($raw[$idx])) {
                $out[$field] = trim($raw[$idx]);
            }
        }
        return $out;
    }

    /** @param array<string,string> $row mapped row */
    private function findMatch(array $row): ?InventoryItem
    {
        if (! empty($row['barcode'])) {
            $item = InventoryItem::where('barcode', $row['barcode'])->first();
            if ($item) {
                return $item;
            }
        }
        if (! empty($row['sku'])) {
            $item = InventoryItem::where('sku', $row['sku'])->first();
            if ($item) {
                return $item;
            }
        }
        if (! empty($row['description'])) {
            return InventoryItem::where('name', $row['description'])->first();
        }
        return null;
    }

    private function guessField(string $header): string
    {
        $lower = strtolower(trim($header));
        $guesses = [
            'description'  => 'description',
            'item'         => 'description',
            'name'         => 'description',
            'product'      => 'description',
            'product name' => 'description',
            'item name'    => 'description',
            'title'        => 'description',
            'sku'          => 'sku',
            'item #'       => 'sku',
            'item#'        => 'sku',
            'item no'      => 'sku',
            'item no.'     => 'sku',
            'part #'       => 'sku',
            'part no'      => 'sku',
            'barcode'      => 'barcode',
            'upc'          => 'barcode',
            'ean'          => 'barcode',
            'gtin'         => 'barcode',
            'upc/ean'      => 'barcode',
            'case_count'   => 'case_count',
            'cases'        => 'case_count',
            'qty'          => 'case_count',
            'quantity'     => 'case_count',
            'count'        => 'case_count',
            'unit_cost'    => 'unit_cost',
            'cost'         => 'unit_cost',
            'price'        => 'unit_cost',
            'unit cost'    => 'unit_cost',
            'unit price'   => 'unit_cost',
            'msrp'         => 'unit_cost',
            'notes'        => 'notes',
            'note'         => 'notes',
            'comments'     => 'notes',
            'comment'      => 'notes',
            'remarks'      => 'notes',
        ];
        return $guesses[$lower] ?? '';
    }

    private function settingKey(): string
    {
        return 'manifest_mapping_vendor_' . ($this->record->vendor_id ?? '0');
    }

    /** @return array<string,string> */
    private function loadSavedMapping(): array
    {
        $raw = Setting::get($this->settingKey());
        if (! $raw) {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function persistMapping(): void
    {
        Setting::set($this->settingKey(), json_encode($this->columnMapping));
    }

    /** @return array{field:string, label:string}[] */
    public function targetFieldOptions(): array
    {
        return [
            ''            => '— ignore this column —',
            'description' => 'Description / Item Name',
            'sku'         => 'SKU',
            'barcode'     => 'Barcode / UPC',
            'case_count'  => 'Case Count',
            'unit_cost'   => 'Unit Cost ($)',
            'notes'       => 'Notes',
        ];
    }
}
