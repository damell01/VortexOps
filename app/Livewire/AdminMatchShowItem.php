<?php

namespace App\Livewire;

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\StreamerLogItem;
use Livewire\Component;

class AdminMatchShowItem extends Component
{
    public StreamerLogItem $line;
    public string $search = '';
    public bool $open = false;

    public function mount(StreamerLogItem $line): void
    {
        abort_unless($this->canManage(), 403);
        $this->line = $line;
        $this->search = $line->item_name;
    }

    private function canManage(): bool
    {
        $user = auth()->user();
        return ($user?->isAdmin() || $user?->isOwner()) ?? false;
    }

    public function toggle(): void
    {
        abort_unless($this->canManage(), 403);
        $this->open = ! $this->open;
    }

    public function matchItem(int $inventoryItemId): void
    {
        abort_unless($this->canManage(), 403);

        $item = InventoryItem::findOrFail($inventoryItemId);
        $entry = $this->line->logEntry()->with('streamer.inventoryLocations')->firstOrFail();

        $locationId = null;
        if ($entry->streamer) {
            $locationIds = $entry->streamer->inventoryLocations->pluck('id');
            $locationId = InventoryStock::query()
                ->where('inventory_item_id', $item->id)
                ->whereIn('inventory_location_id', $locationIds)
                ->where('quantity', '>', 0)
                ->orderByDesc('quantity')
                ->value('inventory_location_id');
        }

        $this->line->update([
            'inventory_item_id' => $item->id,
            'unit_cost' => $this->line->unit_cost ?? $item->average_cost,
            'inventory_location_id' => $locationId,
            'notes' => trim(implode("\n", array_filter([
                $this->line->notes,
                'Admin matched reported item to catalog product: ' . $item->name,
            ]))),
        ]);

        $entry->reconcileWorkflowAfterFix();

        session()->flash('show_report_message', "Matched \"{$this->line->item_name}\" to {$item->name}.");
        $this->redirect(\App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $entry->show_id]));
    }

    public function render()
    {
        $needle = trim($this->search);

        $results = $this->open
            ? InventoryItem::query()
                ->where('is_active', true)
                ->when($needle !== '', fn ($q) => $q->where(function ($sub) use ($needle) {
                    $sub->where('name', 'like', "%{$needle}%")
                        ->orWhere('sku', 'like', "%{$needle}%")
                        ->orWhere('barcode', 'like', "%{$needle}%")
                        ->orWhere('brand', 'like', "%{$needle}%");
                }))
                ->withSum('stock', 'quantity')
                ->orderBy('name')
                ->limit(12)
                ->get()
            : collect();

        return view('livewire.admin-match-show-item', ['results' => $results]);
    }
}
