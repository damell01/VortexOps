<?php

namespace App\Filament\Pages;

use App\Services\InventoryImportService;
use App\Support\AdminModules;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class InventoryImporter extends Page
{
    use WithFileUploads;

    protected static ?string $title = 'Inventory Importer';

    public $file = null;

    public array $reviewRows = [];
    public array $summary = ['total' => 0, 'new' => 0, 'existing' => 0, 'conflict' => 0, 'updates' => 0];
    public array $recognizedHeaders = [];
    public array $warnings = [];
    public bool $updateExisting = false;
    public bool $showNew = true;
    public bool $showExisting = true;
    public bool $showConflicts = true;
    public ?array $lastImportResult = null;

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
        return 'Import Inventory';
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function getView(): string
    {
        return 'filament.pages.inventory-importer';
    }

    public function getSubheading(): ?string
    {
        return 'Upload the latest inventory spreadsheet, review what is new versus already in VortexOps, then import only what you approve.';
    }

    public function buildReview(): void
    {
        $this->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:15360'],
        ]);

        $this->lastImportResult = null;

        try {
            $preview = app(InventoryImportService::class)->preview($this->file->getRealPath());
            $this->reviewRows = $preview['rows'];
            $this->summary = $preview['summary'];
            $this->recognizedHeaders = $preview['headers'];
            $this->warnings = $preview['warnings'];

            if ($this->reviewRows === []) {
                Notification::make()
                    ->title('Nothing ready to review')
                    ->body($this->warnings[0] ?? 'No inventory rows were found in the spreadsheet.')
                    ->warning()
                    ->send();
                return;
            }

            Notification::make()
                ->title('Spreadsheet reviewed')
                ->body("{$this->summary['new']} new · {$this->summary['existing']} existing · {$this->summary['conflict']} need attention")
                ->success()
                ->send();
        } catch (\Throwable $e) {
            report($e);
            $this->reviewRows = [];
            $this->warnings = [$e->getMessage()];

            Notification::make()
                ->title('Could not read spreadsheet')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function importReviewed(): void
    {
        if ($this->reviewRows === []) {
            return;
        }

        $result = app(InventoryImportService::class)->import($this->reviewRows, $this->updateExisting);
        $this->lastImportResult = $result;

        Notification::make()
            ->title('Inventory import complete')
            ->body("{$result['created']} created · {$result['updated']} updated · {$result['skipped']} existing skipped" . ($result['conflicts'] ? " · {$result['conflicts']} conflicts not imported" : ''))
            ->success()
            ->send();

        // Re-run the review against the same uploaded file so the screen reflects
        // the database after import. Newly-created rows should now read Existing.
        try {
            $preview = app(InventoryImportService::class)->preview($this->file->getRealPath());
            $this->reviewRows = $preview['rows'];
            $this->summary = $preview['summary'];
            $this->warnings = array_merge($preview['warnings'], $result['errors']);
        } catch (\Throwable $e) {
            $this->warnings[] = $e->getMessage();
        }
    }

    public function resetImporter(): void
    {
        $this->reset([
            'file', 'reviewRows', 'recognizedHeaders', 'warnings', 'lastImportResult',
        ]);
        $this->summary = ['total' => 0, 'new' => 0, 'existing' => 0, 'conflict' => 0, 'updates' => 0];
        $this->updateExisting = false;
    }

    public function filteredRows(): array
    {
        return array_values(array_filter($this->reviewRows, function (array $row): bool {
            return match ($row['status'] ?? '') {
                'new' => $this->showNew,
                'existing' => $this->showExisting,
                'conflict', 'invalid' => $this->showConflicts,
                default => true,
            };
        }));
    }
}
