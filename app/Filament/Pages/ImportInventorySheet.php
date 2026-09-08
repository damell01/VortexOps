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
        $this->reset(['rows', 'summary', 'error', 'result', 'sheets', 'sheet', 'storedPath', 'fileName', 'filter']);
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

        $this->sheet = in_array(ProductSheetImporter::DEFAULT_SHEET, $this->sheets, true)
            ? ProductSheetImporter::DEFAULT_SHEET
            : ($this->sheets[0] ?? null);

        $this->analyse();
    }

    public function updatedSheet(): void { $this->filter = 'all'; $this->analyse(); }
    public function updatedOverwrite(): void { $this->filter = 'all'; $this->analyse(); }

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
            $sourceRows = app(ProductSheetImporter::class)->read($path, $this->sheet);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            return;
        }

        if ($sourceRows === []) {
            $this->error = 'That worksheet has no rows with a product name on them.';
            return;
        }

        $plan = app(ProductSheetImporter::class)->plan($sourceRows, $this->overwrite);
        $sourceByLine = collect($sourceRows)->keyBy('line');

        $this->rows = collect($plan['rows'])->map(function (array $planned) use ($sourceByLine): array {
            $source = $sourceByLine->get($planned['line'], []);
            $cost = isset($source['cost']) && $source['cost'] !== null ? (float) $source['cost'] : null;
            $target = isset($source['sale_price']) && $source['sale_price'] !== null ? (float) $source['sale_price'] : null;
            $margin = $cost !== null && $target !== null ? round($target - $cost, 2) : null;
            $marginPct = $margin !== null && $target > 0 ? round(($margin / $target) * 100, 1) : null;

            return $planned + [
                'sheet_cost' => $cost,
                'sheet_target' => $target,
                'sheet_margin' => $margin,
                'sheet_margin_pct' => $marginPct,
            ];
        })->values()->all();

        $this->summary = $plan['summary'];
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
            return $this->filter === 'warnings'
                ? ! empty($row['warnings'])
                : ($row['action'] ?? null) === $this->filter;
        }));
    }

    public function setFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'create', 'update', 'unchanged', 'warnings'], true)) return;
        $this->filter = $filter;
        unset($this->visibleRows, $this->hiddenRowCount);
    }

    public function import(): void
    {
        if ($this->rows === [] || ! $this->storedPath || ! $this->sheet) return;

        $importer = app(ProductSheetImporter::class);
        try {
            $rows = $importer->read($this->diskPath(), $this->sheet);
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
        $this->reset(['upload', 'storedPath', 'fileName', 'sheets', 'sheet', 'rows', 'summary', 'error', 'result', 'filter', 'overwrite']);
    }
}
