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

class InventoryPutaway extends Page
{
    use HasModuleAccess;

    protected static string $moduleSlug = 'inventory';

    protected static ?string $title = 'Container Putaway';

    protected static ?string $slug = 'inventory-putaway';

    public ?int $container_id = null;

    public ?int $destination_location_id = null;

    public string $reason = '';

    public function getView(): string
    {
        return 'filament.pages.inventory-putaway';
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return AdminModules::navigationGroupFor('inventory');
    }

    public static function getNavigationSort(): ?int
    {
        return 3;
    }

    public static function getNavigationLabel(): string
    {
        return 'Container Putaway';
    }

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-map';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('receiving')
                ->label('Receiving')
                ->icon('heroicon-o-truck')
                ->color('gray')
                ->url(InventoryReceiving::getUrl()),
            Action::make('breakdown')
                ->label('Pallet Breakdown')
                ->icon('heroicon-o-squares-2x2')
                ->color('gray')
                ->url(InventoryBreakdown::getUrl()),
        ];
    }

    public function updatedContainerId(?int $state): void
    {
        if (! InventoryContainer::schemaReady()) {
            return;
        }

        $container = $state ? InventoryContainer::find($state) : null;

        if ($container && (! $this->destination_location_id || $this->destination_location_id === $container->inventory_location_id)) {
            $this->destination_location_id = InventoryLocation::query()
                ->where('status', 'active')
                ->where('type', '!=', 'receiving')
                ->where('id', '!=', $container->inventory_location_id)
                ->value('id');
        }
    }

    /**
     * @return array<int, string>
     */
    public function movableContainerOptions(): array
    {
        if (! InventoryContainer::schemaReady()) {
            return [];
        }

        return InventoryContainer::query()
            ->with(['item:id,name,sku', 'location:id,name'])
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->orderByDesc('id')
            ->limit(120)
            ->get()
            ->mapWithKeys(fn (InventoryContainer $container): array => [
                $container->id => sprintf(
                    '%s · %s units · %s',
                    $container->label,
                    number_format((float) $container->quantity, 0),
                    $container->location?->name ?: 'No location'
                ),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function destinationLocationOptions(): array
    {
        return InventoryLocation::query()
            ->where('status', 'active')
            ->where('type', '!=', 'receiving')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function selectedContainer(): ?InventoryContainer
    {
        if (! InventoryContainer::schemaReady()) {
            return null;
        }

        if (! $this->container_id) {
            return null;
        }

        return InventoryContainer::with(['item:id,name,sku', 'location:id,name'])->find($this->container_id);
    }

    /**
     * @return \Illuminate\Support\Collection<int, InventoryContainer>
     */
    public function recentlyMoved()
    {
        if (! InventoryContainer::schemaReady()) {
            return Collection::make();
        }

        return InventoryContainer::query()
            ->with(['item:id,name,sku', 'location:id,name'])
            ->where('status', 'active')
            ->latest('updated_at')
            ->limit(8)
            ->get();
    }

    public function moveContainer(): void
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
            'container_id' => ['required', 'exists:inventory_containers,id'],
            'destination_location_id' => ['required', 'exists:inventory_locations,id'],
            'reason' => ['nullable', 'string'],
        ]);

        $container = InventoryContainer::with(['item', 'location'])->findOrFail($validated['container_id']);
        $destination = InventoryLocation::findOrFail($validated['destination_location_id']);

        if ($container->inventory_location_id === $destination->id) {
            $this->addError('destination_location_id', 'Choose a different destination location.');

            return;
        }

        app(InventoryService::class)->moveContainer(
            $container,
            $destination,
            $validated['reason'] ?: null,
        );

        $this->reason = '';

        Notification::make()
            ->title('Container moved')
            ->body($container->label . ' moved to ' . $destination->name . '.')
            ->success()
            ->send();
    }
}
