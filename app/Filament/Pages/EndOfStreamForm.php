<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\WhatnotShowOrder;
use App\Support\AdminModules;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EndOfStreamForm extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $moduleSlug = 'streams';

    protected static ?string $navigationIcon = 'heroicon-o-camera';

    protected static ?string $title = 'End of Stream';

    public ?Show $show = null;
    public string $search = '';
    public array $selectedItems = [];
    public array $itemQuantities = [];

    public function getTitle(): string | Htmlable
    {
        return $this->show ? "End of Stream: {$this->show->title}" : 'End of Stream';
    }

    public function getSubheading(): ?string
    {
        return 'Select the show and the items you sold. We\'ll handle the rest.';
    }

    public function mount(?string $showId = null): void
    {
        if ($showId) {
            $this->show = Show::findOrFail($showId);
        }
    }

    public function selectShow(string $showId): void
    {
        $this->show = Show::findOrFail($showId);
        $this->search = '';
        $this->selectedItems = [];
        $this->itemQuantities = [];
    }

    public function getShowsProperty()
    {
        $user = auth()->user();
        $streamer = $user?->streamer;

        if (!$streamer) {
            return [];
        }

        return Show::whereHas('streamers', fn ($q) => $q->where('streamers.id', $streamer->id))
            ->where('status', '!=', 'closed')
            ->orderBy('show_date', 'desc')
            ->limit(20)
            ->get();
    }

    public function getInventoryProperty()
    {
        $query = InventoryItem::query()
            ->where('is_active', true)
            ->with(['stock' => fn ($q) => $q->where('quantity', '>', 0)]);

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('name', 'like', "%{$this->search}%")
                    ->orWhere('sku', 'like', "%{$this->search}%")
                    ->orWhere('brand', 'like', "%{$this->search}%");
            });
        }

        return $query->orderBy('name')->limit(50)->get();
    }

    public function toggleItem(int $itemId): void
    {
        if (in_array($itemId, $this->selectedItems)) {
            $this->selectedItems = array_filter($this->selectedItems, fn ($id) => $id !== $itemId);
            unset($this->itemQuantities[$itemId]);
        } else {
            $this->selectedItems[] = $itemId;
            $this->itemQuantities[$itemId] = 1;
        }
    }

    public function updateQuantity(int $itemId, int $quantity): void
    {
        if ($quantity > 0) {
            $this->itemQuantities[$itemId] = $quantity;
        }
    }

    public function submit(): void
    {
        if (!$this->show) {
            Notification::make()
                ->title('No show selected')
                ->body('Please select a show first.')
                ->danger()
                ->send();
            return;
        }

        if (empty($this->selectedItems)) {
            Notification::make()
                ->title('No items selected')
                ->body('Please select at least one item from inventory.')
                ->warning()
                ->send();
            return;
        }

        $streamer = auth()->user()?->streamer;
        if (!$streamer) {
            Notification::make()
                ->title('Streamer not found')
                ->danger()
                ->send();
            return;
        }

        try {
            foreach ($this->selectedItems as $itemId) {
                $quantity = $this->itemQuantities[$itemId] ?? 1;

                // Create or update order record
                WhatnotShowOrder::updateOrCreate(
                    [
                        'show_id' => $this->show->id,
                        'inventory_item_id' => $itemId,
                        'status' => 'completed',
                    ],
                    [
                        'item_name' => InventoryItem::find($itemId)?->name,
                        'quantity' => $quantity,
                        'show_date' => $this->show->show_date,
                    ]
                );
            }

            Notification::make()
                ->title('✓ Items logged')
                ->body(count($this->selectedItems) . ' item(s) added to your show.')
                ->success()
                ->send();

            // Redirect to streamer log to review/finalize
            $this->redirect(route('filament.admin.resources.streamer-logs.index'), navigate: true);
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving items')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return AdminModules::isEnabled('streams') && $user?->isStreamer();
    }

    public static function getSlug(): string
    {
        return 'end-of-stream';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationLabel(): string
    {
        return 'End of Stream';
    }

    public static function getNavigationGroup(): string | null
    {
        return 'Streams';
    }

    public static function getNavigationSort(): ?int
    {
        return 38;
    }
}
