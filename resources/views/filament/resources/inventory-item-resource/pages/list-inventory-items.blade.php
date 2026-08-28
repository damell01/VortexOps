<x-filament-panels::page>
<div x-data x-on:barcode-scanned.window="if ($wire.barcodeScanTargetId) $wire.saveScannedBarcode($event.detail.value)"></div>
<div class="space-y-3 sm:space-y-5 vx-inventory-list" data-vx-page="inventory-center">
    <div class="vx-inventory-mobile-intro sm:hidden"><h2 class="text-2xl font-bold text-gray-950 dark:text-white">Inventory</h2><p class="mt-1 text-sm text-gray-500">Track and manage your inventory items.</p></div>

    <section class="vx-inventory-desktop-tools overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
        <div class="p-4 sm:p-5"><div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Inventory Center</div><h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">What are you trying to do?</h2><p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Search first when checking stock. Scan, add, receive, or manage locations from here.</p></div>
        <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">@php $user=auth()->user(); $canReceive=$user?->isAdmin()||$user?->isOwner(); @endphp
            <a href="{{\App\Filament\Resources\InventoryItemResource::getUrl('quick-add')}}" class="bg-white p-3 dark:bg-gray-900 sm:p-4"><x-heroicon-o-bolt class="h-5 w-5 text-primary-600"/><div class="mt-2 text-sm font-semibold">Quick Add</div></a>
            @if($canReceive)<a href="{{\App\Filament\Pages\InventoryScanner::getUrl()}}" class="bg-white p-3 dark:bg-gray-900 sm:p-4"><x-heroicon-o-qr-code class="h-5 w-5 text-primary-600"/><div class="mt-2 text-sm font-semibold">Quick Scan</div></a><a href="{{\App\Filament\Resources\PalletResource::getUrl('index')}}" class="bg-white p-3 dark:bg-gray-900 sm:p-4"><x-heroicon-o-inbox-arrow-down class="h-5 w-5 text-primary-600"/><div class="mt-2 text-sm font-semibold">Receive Shipment</div></a>@endif
            <a href="{{\App\Filament\Resources\InventoryLocationResource::getUrl('index')}}" class="bg-white p-3 dark:bg-gray-900 sm:p-4"><x-heroicon-o-map-pin class="h-5 w-5 text-primary-600"/><div class="mt-2 text-sm font-semibold">Locations</div></a>
        </div>
    </section>
    <div class="vx-inventory-kpis"><x-kpi-row :stats="$this->getStats()" /></div>
    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl vx-inventory-items-panel"><div class="hidden border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:block sm:px-5"><h2 class="text-base font-semibold">Inventory Items</h2><p class="mt-0.5 text-xs text-gray-500">Search by item name, SKU, or barcode.</p></div><div class="p-1 sm:p-2">{{$this->table}}</div></section>
</div>
</x-filament-panels::page>
