<?php

namespace App\Filament\Pages;

use App\Models\InventoryItem;
use App\Models\Show;
use App\Models\Streamer;
use App\Models\StreamerLogEntry;
use App\Models\User;
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
use Filament\Panel;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class EndOfStreamForm extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $moduleSlug = 'streams';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-camera';

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

    public function getView(): string
    {
        return 'filament.pages.end-of-stream-form';
    }

    public function mount(?string $showId = null): void
    {
        if ($showId) {
            $this->show = Show::findOrFail($showId);
        }
    }

    public function selectShow(string $showId): void
    {
        if (empty($showId)) {
            $this->show = null;
        } else {
            $this->show = Show::findOrFail($showId);
        }
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
            // Get or create streamer log entry
            $logEntry = $this->show->streamerLogEntry()
                ->firstOrCreate(['streamer_id' => $streamer->id], ['status' => 'pending']);

            // Add/update items to the show
            foreach ($this->selectedItems as $itemId) {
                $quantity = $this->itemQuantities[$itemId] ?? 1;
                $item = InventoryItem::find($itemId);

                // Create or update order record
                WhatnotShowOrder::updateOrCreate(
                    [
                        'show_id' => $this->show->id,
                        'inventory_item_id' => $itemId,
                    ],
                    [
                        'item_name' => $item?->name,
                        'quantity' => $quantity,
                        'show_date' => $this->show->show_date,
                        'status' => 'completed',
                    ]
                );
            }

            // Submit the report (sets submitted_at timestamp)
            $logEntry->submitReport();

            Notification::make()
                ->title('✓ Report submitted')
                ->body(count($this->selectedItems) . ' item(s) logged. You have 2 hours to make changes.')
                ->success()
                ->send();

            // Notify admins of the submission
            $streamerName = $streamer->name ?? 'Unknown Streamer';
            $showTitle = $this->show->title ?? ('Show #' . $this->show->id);
            $itemCount = $logEntry->items_count ?? count($this->selectedItems);
            User::role('admin')->each(function (User $admin) use ($logEntry, $streamerName, $showTitle, $itemCount) {
                Notification::make()
                    ->title('Streamer Submitted Items')
                    ->body("{$streamerName} submitted {$itemCount} items for {$showTitle}")
                    ->actions([
                        \Filament\Notifications\Actions\Action::make('review')
                            ->label('Review')
                            ->url(route('filament.admin.resources.streamer-logs.edit', $logEntry)),
                    ])
                    ->sendToDatabase($admin);
            });

            // Redirect to streamer log to review
            $this->redirect(
                route('filament.admin.resources.streamer-logs.edit', $logEntry),
                navigate: true
            );
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

    public static function getSlug(?Panel $panel = null): string
    {
        return 'end-of-stream';
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Show to streamers who can access the form
        return auth()->user()?->isStreamer() ?? false;
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
