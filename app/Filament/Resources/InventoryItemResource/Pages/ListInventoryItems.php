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
    protected ?array $statsMemo = null;
    public ?string $stockHealth = null;
    public ?int $barcodeScanTargetId = null;
    public ?string $barcodeScanTargetName = null;

    public function getView(): string { return 'filament.resources.inventory-item-resource.pages.list-inventory-items'; }
    public function getTitle(): string { return 'Inventory'; }
    public function getSubheading(): ?string { return 'Track and manage your inventory items.'; }
    public function getBreadcrumbs(): array { return []; }

    protected function getTableQuery(): ?\Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getTableQuery();
        if (! $query || ! $this->stockHealth) return $query;

        return match ($this->stockHealth) {
            'out' => $query->havingRaw('COALESCE(stock_sum_quantity, 0) <= 0'),
            'low' => $query->whereNotNull('reorder_level')->havingRaw('COALESCE(stock_sum_quantity, 0) > 0 AND stock_sum_quantity <= products.reorder_level'),
            'in' => $query->havingRaw('COALESCE(stock_sum_quantity, 0) > 0')->where(fn ($q) => $q->whereNull('reorder_level')->orWhereColumn('stock_sum_quantity', '>', 'products.reorder_level')),
            default => $query,
        };
    }

    public function filterStock(?string $status): void
    {
        $this->stockHealth = $this->stockHealth === $status ? null : $status;
        $this->resetTablePage();
    }

    public function getStats(): array
    {
        if ($this->statsMemo !== null) return $this->statsMemo;

        $items = InventoryItemResource::getEloquentQuery()->get(['products.id', 'products.reorder_level']);
        $total = $items->count();
        $out = 0;
        $low = 0;

        foreach ($items as $item) {
            $onHand = (float) ($item->stock_sum_quantity ?? 0);

            if ($onHand <= 0) {
                $out++;
            } elseif ($item->reorder_level !== null && $onHand <= (float) $item->reorder_level) {
                $low++;
            }
        }

        $in = $total - $out - $low;
        $percentage = fn (int $count): string => $total > 0
            ? number_format(($count / $total) * 100, 1) . '%'
            : '0.0%';

        return $this->statsMemo = [
            ['key' => null, 'label' => 'All Items', 'value' => number_format($total), 'percentage' => $total > 0 ? '100%' : '0%', 'icon' => 'heroicon-o-cube', 'tone' => 'purple'],
            ['key' => 'in', 'label' => 'In Stock', 'value' => number_format($in), 'percentage' => $percentage($in), 'icon' => 'heroicon-o-check-circle', 'tone' => 'green'],
            ['key' => 'low', 'label' => 'Low Stock', 'value' => number_format($low), 'percentage' => $percentage($low), 'icon' => 'heroicon-o-exclamation-circle', 'tone' => 'amber'],
            ['key' => 'out', 'label' => 'Out of Stock', 'value' => number_format($out), 'percentage' => $percentage($out), 'icon' => 'heroicon-o-x-circle', 'tone' => 'red'],
        ];
    }

    public function startBarcodeScan(int $productId): void
    {
        $product=Product::find($productId);if(!$product)return;$this->barcodeScanTargetId=$product->getKey();$this->barcodeScanTargetName=$product->name;$this->dispatch('open-camera-scanner',title:'Scan barcode',helper:$product->name);
    }
    public function saveScannedBarcode(string $barcode): void
    {
        $barcode=trim($barcode);$product=$this->barcodeScanTargetId?Product::find($this->barcodeScanTargetId):null;$this->barcodeScanTargetId=null;$name=$this->barcodeScanTargetName;$this->barcodeScanTargetName=null;if($barcode===''||!$product)return;
        $clash=Product::where('barcode',$barcode)->whereKeyNot($product->getKey())->first();if($clash){Notification::make()->title('That barcode is already in use')->body($barcode.' is on "'.$clash->name.'". Nothing was changed.')->danger()->send();return;}
        $previous=$product->barcode;$product->forceFill(['barcode'=>$barcode])->save();Notification::make()->title('Barcode saved')->body(filled($previous)?$name.' — replaced '.$previous.' with '.$barcode:$name.' — '.$barcode)->success()->send();
    }

    protected function getHeaderActions(): array
    {
        $user=auth()->user();$canExport=fn()=>$user?->isAdmin()||$user?->isOwner();$canReceive=fn()=>$user?->isAdmin()||$user?->isOwner();$canCreate=fn()=>($user?->isAdmin()??false)||($user?->isOwner()??false)||($user?->isStreamer()??false);
        return [
            Action::make('scan')->label('Quick Scan')->icon('heroicon-o-qr-code')->color('primary')->url(fn()=>InventoryScanner::getUrl())->visible($canReceive),
            Action::make('receive')->label('Receive Shipment')->icon('heroicon-o-inbox-arrow-down')->color('success')->url(fn()=>PalletResource::getUrl('index'))->visible($canReceive),
            Action::make('quick-add')->label('Quick Add')->icon('heroicon-o-bolt')->color('gray')->url(fn()=>InventoryItemResource::getUrl('quick-add'))->visible($canCreate),
            CreateAction::make()->label('Add Item')->icon('heroicon-m-plus')->visible($canCreate),
            ActionGroup::make([
                Action::make('view-report')->label('View report')->icon('heroicon-o-eye')->url(route('export.inventory-pdf'))->openUrlInNewTab(),
                Action::make('export-pdf')->label('Download PDF')->icon('heroicon-o-document-arrow-down')->url(route('export.inventory-pdf').'?download=1')->openUrlInNewTab(),
                Action::make('export-excel')->label('Export to Excel')->icon('heroicon-o-table-cells')->url(route('export.inventory-items'))->openUrlInNewTab(),
            ])->label('More')->icon('heroicon-o-ellipsis-horizontal')->button()->color('gray')->visible($canExport),
        ];
    }
}
