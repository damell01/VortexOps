<?php

namespace App\Filament\Pages;

use BackedEnum;
use App\Filament\Concerns\HasModuleAccess;
use App\Models\InventoryContainer;
use App\Models\InventoryLocation;
use App\Services\InventoryService;
use App\Support\AdminModules;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class InventoryBreakdown extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Pallet Breakdown';

    protected static ?string $slug = 'inventory-breakdown';

    public ?int $parent_container_id = null;

    public ?int $destination_location_id = null;

    public string $child_type = 'case';

    public string $child_count = '1';

    public string $units_per_child = '1';

    public bool $create_remainder_case = true;

    public string $label_prefix = '';

    public string $notes = '';

    public function getView(): string
    {
        return 'filament.pages.inventory-breakdown';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationLabel(): string
    {
        return 'Pallet Breakdown';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-squares-2x2';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receiving')
                ->label('Receiving')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(InventoryReceiving::getUrl()),
            Action::make('putaway')
                ->label('Container Putaway')
                ->icon('heroicon-o-map')
                ->color('gray')
                ->url(InventoryPutaway::getUrl()),
        ];
    }

    public function updatedParentContainerId(?int $state): void
    {
        $container = $state ? InventoryContainer::with('location')->find($state) : null;

        $this->destination_location_id = $container?->inventory_location_id;
        $this->label_prefix = $container?->label ? $container->label . '-CASE' : '';
    }

    /**
     * @return array<int, string>
     */
    public function parentContainerOptions(): array
    {
        if (! InventoryContainer::schemaReady()) {
            return [];
        }

        return InventoryContainer::query()
            ->with(['item:id,name,sku', 'location:id,name'])
            ->whereIn('container_type', ['pallet', 'case', 'box'])
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->mapWithKeys(fn (InventoryContainer $container): array => [
                $container->id => sprintf(
                    '%s%s · %s units · %s',
                    $container->label,
                    $container->item?->sku ? ' (' . $container->item->sku . ')' : '',
                    number_format((float) $container->quantity, 0),
                    $container->location?->name ?: 'No location'
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function locationOptions(): array
    {
        return InventoryLocation::query()
            ->where('status', 'active')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function selectedParent(): ?InventoryContainer
    {
        if (! InventoryContainer::schemaReady()) {
            return null;
        }

        if (! $this->parent_container_id) {
            return null;
        }

        return InventoryContainer::with(['item:id,name,sku', 'location:id,name'])->find($this->parent_container_id);
    }

    public function breakdownSummary(): array
    {
        $parent = $this->selectedParent();
        $count = max(0, (int) $this->child_count);
        $units = max(0, (float) $this->units_per_child);
        $planned = $count * $units;
        $remainder = max(0, (float) ($parent?->quantity ?? 0) - $planned);

        return [
            'planned' => $planned,
            'remainder' => $remainder,
            'will_create' => $count + (($this->create_remainder_case && $remainder > 0) ? 1 : 0),
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, InventoryContainer>
     */
    public function recentlyBrokenDown()
    {
        if (! InventoryContainer::schemaReady()) {
            return Collection::make();
        }

        return InventoryContainer::query()
            ->with(['parentContainer:id,label', 'item:id,name,sku', 'location:id,name'])
            ->whereNotNull('parent_container_id')
            ->latest('id')
            ->limit(8)
            ->get();
    }

    public function runBreakdown(): void
    {
        if (! InventoryContainer::schemaReady()) {
            Notification::make()
                ->title('Inventory container tables are not ready yet')
                ->body('Run the latest migrations on the server before using Receiving, Breakdown, or Putaway.')
                ->danger()
                ->send();

            return;
        }

        $validated = $this->validate([
            'parent_container_id' => ['required', 'exists:inventory_containers,id'],
            'destination_location_id' => ['required', 'exists:inventory_locations,id'],
            'child_type' => ['required', 'in:case,box,unit,other'],
            'child_count' => ['required', 'integer', 'min:1'],
            'units_per_child' => ['required', 'numeric', 'gt:0'],
            'label_prefix' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $parent = InventoryContainer::with(['item', 'location'])->findOrFail($validated['parent_container_id']);
        $destination = InventoryLocation::findOrFail($validated['destination_location_id']);

        $childCount = (int) $validated['child_count'];
        $unitsPerChild = (float) $validated['units_per_child'];
        $plannedTotal = $childCount * $unitsPerChild;
        $available = (float) $parent->quantity;

        if ($plannedTotal > $available) {
            $this->addError('units_per_child', "Planned split exceeds available quantity ({$available}).");

            return;
        }

        $prefix = trim($validated['label_prefix'] ?: ($parent->label . '-CASE'));
        $children = [];

        for ($i = 1; $i <= $childCount; $i++) {
            $children[] = [
                'label' => sprintf('%s-%02d', $prefix, $i),
                'quantity' => $unitsPerChild,
                'notes' => $validated['notes'] ?: null,
                'scanner_ready' => true,
            ];
        }

        $remainder = round($available - $plannedTotal, 2);

        if ($this->create_remainder_case && $remainder > 0) {
            $children[] = [
                'label' => sprintf('%s-%02d', $prefix, count($children) + 1),
                'quantity' => $remainder,
                'notes' => 'Remainder split from ' . $parent->label,
                'scanner_ready' => true,
            ];
        }

        $created = app(InventoryService::class)->breakdownContainer(
            $parent,
            $validated['child_type'],
            $children,
            $destination,
        );

        Notification::make()
            ->title('Breakdown complete')
            ->body('Created ' . count($created) . ' child containers from ' . $parent->label . '.')
            ->success()
            ->send();
    }
}
