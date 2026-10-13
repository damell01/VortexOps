<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Services\ProductSheetImporter;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\WithFileUploads;

class ImportInventorySheet extends Page
{
    use HasModuleAccess;
    use WithFileUploads;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Import Inventory Sheet';

    public $upload = null;
    public ?string $storedPath = null;
    public ?string $fileName = null;
    public array $sheets = [];
    public ?string $sheet = null;
    public bool $overwrite = false;
    public array $rows = [];
    public array $summary = [];
    public string $filter = 'all';
    public ?string $error = null;
    public ?array $result = null;

    /** line => edited cells, typed on the preview before importing */
    public array $edits = [];

    /** line => 'import' | 'skip' | 'new' | 'match:{productId}' */
    public array $decisions = [];

    /** Possible duplicates start as "don't import" once per file and sheet. */
    public bool $defaultsApplied = false;

    public ?int $editingLine = null;
    public array $editForm = [];

    public static function getNavigationGroup(): string|\UnitEnum|null { return AdminModules::navigationGroupFor('inventory'); }
    public static function getNavigationIcon(): string|\BackedEnum|null { return 'heroicon-o-arrow-up-tray'; }
    public static function getNavigationLabel(): string { return 'Import Sheet'; }
    public static function getNavigationSort(): ?int { return 46; }
    public static function shouldRegisterNavigation(): bool { return false; }
    public function getView(): string { return 'filament.pages.import-inventory-sheet'; }

    public function getSubheading(): ?string
    {
        return 'Read a product sheet, preview cost, target and margin, then decide. Completely empty rows are ignored.';
    }

    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    public function updatedUpload(): void
    {
        $this->reset(['rows', 'summary', 'error', 'result', 'sheets', 'sheet', 'storedPath', 'fileName', 'filter', 'edits', 'decisions', 'defaultsApplied', 'editingLine', 'editForm']);
        $this->validate(['upload' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv,txt']], [], ['upload' => 'sheet']);

        $this->storedPath = $this->upload->store('imports', 'local');
        $this->fileName = $this->upload->getClientOriginalName();
        $importer = app(ProductSheetImporter::class);

        try {
            $this->sheets = $importer->sheetNames($this->diskPath());
        } catch (\Throwable $e) {
            $this->error = 'That file could not be opened: ' . $e->getMessage();
            return;
        }

        $this->sheet = $importer->guessSheet($this->diskPath(), $this->sheets);

        $this->analyse();
    }

    public function updatedSheet(): void { $this->reset(['edits', 'decisions', 'defaultsApplied', 'editingLine', 'editForm']); $this->filter = 'all'; $this->analyse(); }
    public function updatedOverwrite(): void { $this->filter = 'all'; $this->analyse(); }

    /** The sheet's rows with the edits made on this screen laid over them. */
    private function sourceRows(): array
    {
        $rows = app(ProductSheetImporter::class)->read($this->diskPath(), $this->sheet);

        return array_map(function (array $row): array {
            $edit = $this->edits[$row['line']] ?? null;
            if (! $edit) {
                return $row;
            }
            foreach (['name', 'sku', 'type', 'sold_as'] as $key) {
                if (array_key_exists($key, $edit)) {
                    $row[$key] = filled($edit[$key]) ? trim((string) $edit[$key]) : ($key === 'name' ? $row['name'] : null);
                }
            }
            foreach (['cost' => 'Cost', 'sale_price' => 'Sale price / Target'] as $key => $label) {
                if (array_key_exists($key, $edit)) {
                    $row[$key] = filled($edit[$key]) ? round((float) $edit[$key], 2) : null;
                    $row['warnings'] = array_values(array_filter($row['warnings'], fn ($w) => ! str_starts_with($w, $label.' ')));
                }
            }
            $row['edited'] = true;

            return $row;
        }, $rows);
    }

    /** Rows with each choice applied: skipped ones removed, the rest told what to match. */
    private function prepared(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $decision = $this->decisions[$row['line']] ?? 'import';
            if ($decision === 'skip') {
                continue;
            }
            if ($decision === 'new') {
                $row['force_new'] = true;
            } elseif (str_starts_with($decision, 'match:')) {
                $row['product_id'] = (int) substr($decision, 6);
            }
            $out[] = $row;
        }

        return $out;
    }

    public function analyse(): void
    {
        $this->reset(['rows', 'summary', 'error']);
        if (! $this->storedPath || ! $this->sheet) return;

        $path = $this->diskPath();
        if (! is_file($path)) {
            $this->error = 'The uploaded file is no longer on the server. Upload it again.';
            return;
        }

        try {
            $sourceRows = $this->sourceRows();
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return;
        }

        if ($sourceRows === []) {
            $this->error = 'That worksheet has no rows with a product name on them.';
            return;
        }

        $importer = app(ProductSheetImporter::class);

        // First look at this sheet: anything that might already be in the
        // catalogue under a slightly different name, and the earlier of two
        // rows for the same product, starts as "don't import".
        if (! $this->defaultsApplied) {
            foreach ($importer->plan($sourceRows, $this->overwrite)['rows'] as $planned) {
                if ($planned['similar'] !== [] && ! isset($this->decisions[$planned['line']])) {
                    $this->decisions[$planned['line']] = 'skip';
                }
                foreach ($planned['warnings'] as $warning) {
                    if (preg_match('/appears on line (\d+) as well/', $warning, $m) && ! isset($this->decisions[(int) $m[1]])) {
                        $this->decisions[(int) $m[1]] = 'skip';
                    }
                }
            }
            $this->defaultsApplied = true;
        }

        $plan = $importer->plan($this->prepared($sourceRows), $this->overwrite);
        $plannedByLine = collect($plan['rows'])->keyBy('line');
        $sheetCopies = collect($sourceRows)->groupBy(fn ($r) => \Illuminate\Support\Str::lower(trim($r['sku'] ?: $r['name'])))
            ->filter(fn ($g) => $g->count() > 1)->flatten(1)->pluck('line')->all();

        $this->rows = collect($sourceRows)->map(function (array $source) use ($plannedByLine, $importer, $sheetCopies, $sourceRows): array {
            $decision = $this->decisions[$source['line']] ?? 'import';
            $planned = $plannedByLine->get($source['line']) ?? [
                'line' => $source['line'], 'name' => $source['name'], 'sku' => $source['sku'],
                'action' => 'skip', 'matched_by' => null, 'match' => null, 'changes' => [],
                'warnings' => $source['warnings'], 'similar' => $importer->similarProducts($source['name']),
            ];
            if ($decision === 'skip') {
                $planned['action'] = 'skip';
            }
            $others = collect($sourceRows)->filter(fn ($r) => $r['line'] !== $source['line']
                && \Illuminate\Support\Str::lower(trim($r['sku'] ?: $r['name'])) === \Illuminate\Support\Str::lower(trim($source['sku'] ?: $source['name'])))->pluck('line');
            if ($others->isNotEmpty() && ! collect($planned['warnings'])->contains(fn ($w) => str_contains($w, 'as well'))) {
                $planned['warnings'][] = 'The same product appears on line '.$others->join(', ').' as well — import only one of them.';
            }
            $cost = $source['cost'] ?? null;
            $target = $source['sale_price'] ?? null;
            $margin = $cost !== null && $target !== null ? round($target - $cost, 2) : null;

            return $planned + [
                'decision' => $decision,
                'edited' => ! empty($source['edited']),
                'type' => $source['type'] ?? null,
                'sold_as' => $source['sold_as'] ?? null,
                'duplicate' => $planned['similar'] !== [] || in_array($source['line'], $sheetCopies, true),
                'sheet_cost' => $cost,
                'sheet_target' => $target,
                'sheet_margin' => $margin,
                'sheet_margin_pct' => $margin !== null && $target > 0 ? round(($margin / $target) * 100, 1) : null,
            ];
        })->values()->all();

        $this->summary = $plan['summary'] + [
            'skipped' => collect($this->rows)->where('action', 'skip')->count(),
            'duplicates' => collect($this->rows)->where('duplicate', true)->count(),
        ];
        $this->summary['total'] = count($this->rows);
        unset($this->visibleRows, $this->hiddenRowCount);
    }

    /** Import, don't import, make a new item, or update a chosen existing one. */
    public function decide(int $line, string $decision): void
    {
        $valid = in_array($decision, ['import', 'skip', 'new'], true)
            || (preg_match('/^match:(\d+)$/', $decision, $m) && \App\Models\Product::whereKey((int) $m[1])->exists());
        if (! $valid) return;

        $this->decisions[$line] = $decision;
        $this->analyse();
    }

    public function startEdit(int $line): void
    {
        $row = collect($this->rows)->firstWhere('line', $line);
        if (! $row) return;

        $this->editingLine = $line;
        $this->editForm = [
            'name' => $row['name'],
            'sku' => $row['sku'],
            'type' => $row['type'],
            'sold_as' => $row['sold_as'],
            'cost' => $row['sheet_cost'],
            'sale_price' => $row['sheet_target'],
        ];
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingLine', 'editForm']);
    }

    public function saveEdit(): void
    {
        if ($this->editingLine === null) return;

        $data = validator($this->editForm, [
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', 'string', 'max:50'],
            'sold_as' => ['nullable', 'string', 'max:50'],
            'cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
        ], [], ['sale_price' => 'target'])->validate();

        $this->edits[$this->editingLine] = $data;
        // Editing a row is deciding to import it.
        if (($this->decisions[$this->editingLine] ?? 'import') === 'skip') {
            $this->decisions[$this->editingLine] = 'import';
        }
        $this->cancelEdit();
        $this->analyse();
    }

    public function undoEdit(int $line): void
    {
        unset($this->edits[$line]);
        $this->analyse();
    }

    #[Computed]
    public function visibleRows(): array
    {
        return array_slice($this->filteredRows(), 0, ProductSheetImporter::PREVIEW_LIMIT);
    }

    #[Computed]
    public function hiddenRowCount(): int
    {
        return max(count($this->filteredRows()) - count($this->visibleRows), 0);
    }

    private function filteredRows(): array
    {
        if ($this->filter === 'all') return $this->rows;

        return array_values(array_filter($this->rows, function (array $row): bool {
            return match ($this->filter) {
                'warnings' => ! empty($row['warnings']),
                'duplicates' => ! empty($row['duplicate']),
                default => ($row['action'] ?? null) === $this->filter,
            };
        }));
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'create', 'update', 'unchanged', 'warnings', 'skip', 'duplicates'], true)) return;
        $this->filter = $filter;
        unset($this->visibleRows, $this->hiddenRowCount);
    }

    public function import(): void
    {
        if ($this->rows === [] || ! $this->storedPath || ! $this->sheet) return;

        $importer = app(ProductSheetImporter::class);
        try {
            $rows = $this->prepared($this->sourceRows());
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return;
        }

        $this->result = $importer->apply($rows, $this->overwrite);
        Notification::make()->title('Import finished')->body(sprintf(
            '%d created, %d updated, %d already matched.',
            $this->result['created'], $this->result['updated'], $this->result['unchanged'],
        ))->success()->send();

        $this->filter = 'all';
        $this->analyse();
    }

    private function diskPath(): string
    {
        return Storage::disk('local')->path((string) $this->storedPath);
    }

    public function startOver(): void
    {
        if ($this->storedPath) Storage::disk('local')->delete($this->storedPath);
        $this->reset(['upload', 'storedPath', 'fileName', 'sheets', 'sheet', 'rows', 'summary', 'error', 'result', 'filter', 'overwrite', 'edits', 'decisions', 'defaultsApplied', 'editingLine', 'editForm']);
    }
}
