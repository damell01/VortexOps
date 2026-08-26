<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Services\ProductSheetImporter;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

/**
 * Importing a product sheet, with the review step in front of it.
 *
 * The console command could already do this, which is not the same as anyone
 * being able to do it: it needs a shell, a file path on the server, and the
 * nerve to run a thing that writes to the catalogue with no way to look first.
 *
 * So the shape here is upload → look at every row → then decide. Nothing is
 * written until the button at the bottom, and what that button writes is the
 * plan you were shown, because both come from ProductSheetImporter.
 */
class ImportInventorySheet extends Page
{
    use HasModuleAccess;
    use WithFileUploads;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Import Inventory Sheet';

    /** The upload itself, while it is being handed over. */
    public $upload = null;

    /** Where it was put once accepted — an upload does not survive the next request. */
    public ?string $storedPath = null;

    public ?string $fileName = null;

    /** @var array<int, string> */
    public array $sheets = [];

    public ?string $sheet = null;

    /** Replace costs and targets that already have a value. Off, deliberately. */
    public bool $overwrite = false;

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    /** @var array<string, int> */
    public array $summary = [];

    public string $filter = 'all';

    public ?string $error = null;

    /** @var array<string, int>|null Set once an import has actually run. */
    public ?array $result = null;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-up-tray';
    }

    public static function getNavigationLabel(): string
    {
        return 'Import Sheet';
    }

    public static function getNavigationSort(): ?int
    {
        return 46;
    }

    public function getView(): string
    {
        return 'filament.pages.import-inventory-sheet';
    }

    public function getSubheading(): ?string
    {
        return 'Read a product sheet, see exactly what it would do, then decide.';
    }

    /**
     * Writing to the catalogue in bulk is an admin job.
     *
     * A role explicitly granted this page on Roles & Permissions still gets it
     * — that check runs before this one in HasModuleAccess.
     */
    protected static function passesModuleAccessCheck(): bool
    {
        return (bool) (auth()->user()?->isAdmin() || auth()->user()?->isOwner());
    }

    /** The file lands, and the preview is built straight away. */
    public function updatedUpload(): void
    {
        $this->reset(['rows', 'summary', 'error', 'result', 'sheets', 'sheet', 'storedPath', 'fileName']);

        $this->validate([
            'upload' => ['required', 'file', 'max:20480', 'mimes:xlsx,xls,csv,txt'],
        ], [], ['upload' => 'sheet']);

        // Kept out of the temporary upload directory: the preview has to still
        // be readable on the request where the import is confirmed.
        $this->storedPath = $this->upload->store('imports', 'local');
        $this->fileName   = $this->upload->getClientOriginalName();

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

    public function updatedSheet(): void
    {
        $this->analyse();
    }

    public function updatedOverwrite(): void
    {
        $this->analyse();
    }

    /** Read the chosen worksheet and work out what an import would do. */
    public function analyse(): void
    {
        // Deliberately not clearing $result: import() re-plans when it is done,
        // and wiping the result there would take the confirmation off the
        // screen at the exact moment someone wants to read it.
        $this->reset(['rows', 'summary', 'error']);

        if (! $this->storedPath || ! $this->sheet) {
            return;
        }

        $path = $this->diskPath();

        if (! is_file($path)) {
            $this->error = 'The uploaded file is no longer on the server. Upload it again.';

            return;
        }

        try {
            $rows = app(ProductSheetImporter::class)->read($path, $this->sheet);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        if ($rows === []) {
            $this->error = 'That worksheet has no rows with a product name on them.';

            return;
        }

        $plan = app(ProductSheetImporter::class)->plan($rows, $this->overwrite);

        $this->rows    = $plan['rows'];
        $this->summary = $plan['summary'];
    }

    /** @return array<int, array<string, mixed>> */
    public function getVisibleRowsProperty(): array
    {
        $rows = $this->filter === 'all'
            ? $this->rows
            : array_values(array_filter(
                $this->rows,
                fn (array $row) => $this->filter === 'warnings'
                    ? $row['warnings'] !== []
                    : $row['action'] === $this->filter,
            ));

        return array_slice($rows, 0, ProductSheetImporter::PREVIEW_LIMIT);
    }

    public function getHiddenRowCountProperty(): int
    {
        $shown = count($this->visibleRows);
        $total = $this->filter === 'all'
            ? count($this->rows)
            : count(array_filter(
                $this->rows,
                fn (array $row) => $this->filter === 'warnings'
                    ? $row['warnings'] !== []
                    : $row['action'] === $this->filter,
            ));

        return max($total - $shown, 0);
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
    }

    /** Write the plan that is on screen. */
    public function import(): void
    {
        if ($this->rows === [] || ! $this->storedPath || ! $this->sheet) {
            return;
        }

        $importer = app(ProductSheetImporter::class);
        $path     = $this->diskPath();

        try {
            // Re-read rather than trusting what is in the browser's copy of the
            // component. The file is the source; the table was only a view of it.
            $rows = $importer->read($path, $this->sheet);
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->result = $importer->apply($rows, $this->overwrite);

        Notification::make()
            ->title('Import finished')
            ->body(sprintf(
                '%d created, %d updated, %d already matched.',
                $this->result['created'],
                $this->result['updated'],
                $this->result['unchanged'],
            ))
            ->success()
            ->send();

        // The plan is now history — re-planning shows the same file against the
        // catalogue as it is now, which is what a second look should show.
        $this->analyse();
    }

    /** Where the stored upload actually lives — the local disk's root moved in Laravel 11. */
    private function diskPath(): string
    {
        return Storage::disk('local')->path((string) $this->storedPath);
    }

    public function startOver(): void
    {
        if ($this->storedPath) {
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->reset(['upload', 'storedPath', 'fileName', 'sheets', 'sheet', 'rows', 'summary', 'error', 'result', 'filter', 'overwrite']);
    }
}
