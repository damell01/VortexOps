<?php

namespace App\Filament\Resources\InventoryItemResource\Pages;

use App\Filament\Pages\InventoryScanner;
use App\Filament\Resources\InventoryItemResource;
use App\Filament\Resources\PalletResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListInventoryItems extends ListRecords
{
    protected static string $resource = InventoryItemResource::class;

    /** Per-request memo — the view reads the stats once, but renders are cheap to repeat. */
    protected ?array $statsMemo = null;

    public function getView(): string
    {
        return 'filament.resources.inventory-item-resource.pages.list-inventory-items';
    }

    public function getTitle(): string
    {
        return 'Inventory';
    }

    public function getSubheading(): ?string
    {
        return 'Find stock, scan items, receive shipments, and make inventory changes from one place.';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getStats(): array
    {
        if ($this->statsMemo !== null) {
            return $this->statsMemo;
        }

        $items = InventoryItemResource::getEloquentQuery()
            ->get(['products.id', 'products.reorder_level']);

        $total = $items->count();
        $out = 0;
        $low = 0;

        foreach ($items as $item) {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);
            $reorder = $item->reorder_level;

            if ($onHand <= 0) {
                $out++;
            } elseif ($reorder !== null && $onHand <= (float) $reorder) {
                $low++;
            }
        }

        $inStock = $total - $out - $low;
        $share = fn (int $n) => $total > 0 ? round(($n / $total) * 100, 1) . '% of total' : '—';

        return $this->statsMemo = [
            ['label' => 'Total Items', 'value' => number_format($total), 'sub' => 'All items', 'icon' => 'heroicon-o-cube', 'tone' => 'purple'],
            ['label' => 'In Stock', 'value' => number_format($inStock), 'sub' => $share($inStock), 'icon' => 'heroicon-o-check-circle', 'tone' => 'green'],
            ['label' => 'Low Stock', 'value' => number_format($low), 'sub' => $share($low), 'icon' => 'heroicon-o-exclamation-circle', 'tone' => 'amber'],
            ['label' => 'Out of Stock', 'value' => number_format($out), 'sub' => $share($out), 'icon' => 'heroicon-o-x-circle', 'tone' => 'red'],
        ];
    }

    /**
     * Which item a camera scan is currently being captured for.
     *
     * Null the rest of the time, which is also what stops a scan meant for
     * something else on the page from being written to a product.
     */
    public ?int $barcodeScanTargetId = null;

    public ?string $barcodeScanTargetName = null;

    /**
     * Open the camera with a product in mind.
     *
     * Putting a code on an item used to mean opening it, finding the field,
     * scanning into it and saving — four screens to record one number that a
     * phone can read in under a second.
     */
    public function startBarcodeScan(int $productId): void
    {
        $product = Product::find($productId);

        if (! $product) {
            return;
        }

        $this->barcodeScanTargetId   = $product->getKey();
        $this->barcodeScanTargetName = $product->name;

        // Livewire re-emits this on window, which is where the camera
        // component is listening.
        $this->dispatch('open-camera-scanner', title: 'Scan barcode', helper: $product->name);
    }

    /**
     * Write a scanned code onto the item the scan was started for.
     */
    public function saveScannedBarcode(string $barcode): void
    {
        $barcode = trim($barcode);
        $product = $this->barcodeScanTargetId ? Product::find($this->barcodeScanTargetId) : null;

        // Cleared first: whatever happens below, this scan is spent, and a
        // target left set would catch the next unrelated scan on the page.
        $this->barcodeScanTargetId   = null;
        $name = $this->barcodeScanTargetName;
        $this->barcodeScanTargetName = null;

        if ($barcode === '' || ! $product) {
            return;
        }

        // The column is uniquely indexed, so without this the save throws a
        // constraint violation and the page 500s on a mis-scan.
        $clash = Product::where('barcode', $barcode)
            ->whereKeyNot($product->getKey())
            ->first();

        if ($clash) {
            Notification::make()
                ->title('That barcode is already in use')
                ->body($barcode . ' is on "' . $clash->name . '". Nothing was changed.')
                ->danger()
                ->send();

            return;
        }

        $previous = $product->barcode;
        $product->forceFill(['barcode' => $barcode])->save();

        Notification::make()
            ->title('Barcode saved')
            ->body(filled($previous)
                ? $name . ' — replaced ' . $previous . ' with ' . $barcode
                : $name . ' — ' . $barcode)
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $canExport = fn () => $user?->isAdmin() || $user?->isOwner();
        $canReceive = fn () => $user?->isAdmin() || $user?->isOwner();
        $canCreate = fn () => ($user?->isAdmin() ?? false)
            || ($user?->isOwner() ?? false)
            || ($user?->isStreamer() ?? false);

        return [
            Action::make('scan')
                ->label('Quick Scan')
                ->icon('heroicon-o-qr-code')
                ->color('primary')
                ->url(fn () => InventoryScanner::getUrl())
                ->visible($canReceive),

            Action::make('receive')
                ->label('Receive Shipment')
                ->icon('heroicon-o-inbox-arrow-down')
                ->color('success')
                ->url(fn () => PalletResource::getUrl('index'))
                ->visible($canReceive),

            Action::make('quick-add')
                ->label('Quick Add')
                ->icon('heroicon-o-bolt')
                ->color('gray')
                ->url(fn () => InventoryItemResource::getUrl('quick-add'))
                ->visible($canCreate),

            CreateAction::make()
                ->label('Add Item')
                ->icon('heroicon-m-plus')
                ->visible($canCreate),

            ActionGroup::make([
                Action::make('view-report')
                    ->label('View report')
                    ->icon('heroicon-o-eye')
                    ->url(route('export.inventory-pdf'))
                    ->openUrlInNewTab(),
                Action::make('export-pdf')
                    ->label('Download PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->url(route('export.inventory-pdf') . '?download=1')
                    ->openUrlInNewTab(),
                Action::make('export-excel')
                    ->label('Export to Excel')
                    ->icon('heroicon-o-table-cells')
                    ->url(route('export.inventory-items'))
                    ->openUrlInNewTab(),
            ])
                ->label('More')
                ->icon('heroicon-o-ellipsis-horizontal')
                ->button()
                ->color('gray')
                ->visible($canExport),
        ];
    }
}
