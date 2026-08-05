<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryItem;
use App\Support\AdminModules;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;

class InventorySearch extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';
    protected static ?string $title = 'Advanced Inventory Search';

    public string $searchQuery = '';
    public string $sortBy = 'name';
    public string $sortDirection = 'asc';

    // Filters
    public ?string $categoryFilter = null;
    public ?string $vendorFilter = null;
    public bool $lowStockOnly = false;
    public bool $noStockOnly = false;
    public bool $activeOnly = true;
    public ?string $savedFilterName = null;

    // Saved filters
    public array $savedFilters = [];

    public function mount(): void
    {
        $this->loadSavedFilters();
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-magnifying-glass';
    }

    public static function getNavigationGroup(): string|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 4;
    }

    public static function getNavigationLabel(): string
    {
        return 'Inventory Search';
    }

    public function getSubheading(): ?string
    {
        return 'Advanced search with filters and saved search profiles.';
    }

    #[Computed]
    public function results(): Collection
    {
        $query = InventoryItem::query();

        // Text search
        if ($this->searchQuery) {
            $query->where(function ($q) {
                $q->whereRaw("LOWER(name) LIKE ?", ['%' . strtolower($this->searchQuery) . '%'])
                    ->orWhereRaw("LOWER(sku) LIKE ?", ['%' . strtolower($this->searchQuery) . '%'])
                    ->orWhereRaw("LOWER(barcode) LIKE ?", ['%' . strtolower($this->searchQuery) . '%'])
                    ->orWhereRaw("LOWER(upc) LIKE ?", ['%' . strtolower($this->searchQuery) . '%']);
            });
        }

        // Status
        if ($this->activeOnly) {
            $query->where('is_active', true);
        }

        // Category
        if ($this->categoryFilter) {
            $query->where('category', $this->categoryFilter);
        }

        // Vendor (from latest lot)
        if ($this->vendorFilter) {
            $query->whereHas('lots', function ($q) {
                $q->whereHas('receivingSession.vendor', function ($vq) {
                    $vq->where('name', $this->vendorFilter);
                });
            });
        }

        // Stock levels
        if ($this->lowStockOnly) {
            $query->whereNotNull('reorder_level')
                ->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stock WHERE inventory_item_id = inventory_items.id) <= reorder_level');
        }

        if ($this->noStockOnly) {
            $query->whereRaw('(SELECT COALESCE(SUM(quantity), 0) FROM inventory_stock WHERE inventory_item_id = inventory_items.id) = 0');
        }

        // Eager load relationships
        $query->with(['stock', 'lots', 'preferredVendor']);

        // Sorting
        $query->orderBy($this->sortBy, $this->sortDirection);

        return $query->limit(500)->get();
    }

    public function saveFilter(): void
    {
        if (!$this->savedFilterName) {
            \Filament\Notifications\Notification::make()
                ->title('Filter name required')
                ->body('Please enter a name for this saved filter.')
                ->warning()
                ->send();
            return;
        }

        $filters = auth()->user()->inventory_saved_filters ?? [];

        $filters[$this->savedFilterName] = [
            'searchQuery' => $this->searchQuery,
            'categoryFilter' => $this->categoryFilter,
            'vendorFilter' => $this->vendorFilter,
            'lowStockOnly' => $this->lowStockOnly,
            'noStockOnly' => $this->noStockOnly,
            'activeOnly' => $this->activeOnly,
            'sortBy' => $this->sortBy,
            'sortDirection' => $this->sortDirection,
            'created_at' => now()->toIso8601String(),
        ];

        auth()->user()->update(['inventory_saved_filters' => $filters]);
        $this->loadSavedFilters();
        $this->savedFilterName = null;

        \Filament\Notifications\Notification::make()
            ->title('Filter saved')
            ->body('Search filter "' . array_key_last($filters) . '" has been saved.')
            ->success()
            ->send();
    }

    public function loadFilter(string $name): void
    {
        $filters = auth()->user()->inventory_saved_filters ?? [];

        if (!isset($filters[$name])) {
            return;
        }

        $filter = $filters[$name];
        $this->searchQuery = $filter['searchQuery'] ?? '';
        $this->categoryFilter = $filter['categoryFilter'] ?? null;
        $this->vendorFilter = $filter['vendorFilter'] ?? null;
        $this->lowStockOnly = $filter['lowStockOnly'] ?? false;
        $this->noStockOnly = $filter['noStockOnly'] ?? false;
        $this->activeOnly = $filter['activeOnly'] ?? true;
        $this->sortBy = $filter['sortBy'] ?? 'name';
        $this->sortDirection = $filter['sortDirection'] ?? 'asc';
    }

    public function deleteFilter(string $name): void
    {
        $filters = auth()->user()->inventory_saved_filters ?? [];
        unset($filters[$name]);
        auth()->user()->update(['inventory_saved_filters' => $filters]);
        $this->loadSavedFilters();

        \Filament\Notifications\Notification::make()
            ->title('Filter deleted')
            ->body('Search filter has been removed.')
            ->info()
            ->send();
    }

    public function clearFilters(): void
    {
        $this->searchQuery = '';
        $this->categoryFilter = null;
        $this->vendorFilter = null;
        $this->lowStockOnly = false;
        $this->noStockOnly = false;
        $this->activeOnly = true;
        $this->sortBy = 'name';
        $this->sortDirection = 'asc';
    }

    private function loadSavedFilters(): void
    {
        $this->savedFilters = array_keys(auth()->user()->inventory_saved_filters ?? []);
    }
}
