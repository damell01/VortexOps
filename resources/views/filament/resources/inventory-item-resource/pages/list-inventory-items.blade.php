<x-filament-panels::page>
<style>
.vx-health-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.vx-health{border:1px solid rgb(229 231 235);border-radius:14px;padding:14px;text-align:left;background:white;transition:.15s}.dark .vx-health{background:#101827;border-color:#263248}.vx-health:hover,.vx-health.active{border-color:#7c3aed;box-shadow:0 0 0 1px #7c3aed}.vx-health strong{display:block;font-size:1.35rem}.vx-health span{font-size:.76rem;color:#94a3b8}
@media(max-width:640px){
 body:has(.vx-inventory-list) .fi-page-header{display:none!important}.vx-inventory-desktop-tools{display:none!important}.vx-inventory-items-panel{border:0!important;background:transparent!important;box-shadow:none!important}.vx-inventory-items-panel>div{padding:0!important}
 .vx-health-grid{grid-template-columns:repeat(3,1fr);gap:8px}.vx-health:first-child{display:none}.vx-health{padding:10px 8px;border-radius:12px}.vx-health strong{font-size:1.15rem}.vx-health span{font-size:.68rem}
 body:has(.vx-inventory-list) nav.fi-topbar{background:rgba(7,12,22,.96)!important;border-bottom:1px solid rgba(255,255,255,.06)!important;backdrop-filter:blur(18px);box-shadow:none!important}body:has(.vx-inventory-list) nav.fi-topbar svg{color:#e8e8f0!important}
 body:has(.vx-inventory-list) .fi-modal-window{border:1px solid rgba(255,255,255,.1)!important;border-radius:28px 28px 0 0!important;background:#10151f!important;box-shadow:0 -20px 70px rgba(0,0,0,.5)!important}.dark .vx-inventory-mobile-intro{color:#fff}
 body:has(.vx-inventory-list) .fi-ta{background:transparent!important}body:has(.vx-inventory-list) .fi-ta-ctn{border:0!important;background:transparent!important;box-shadow:none!important}body:has(.vx-inventory-list) .fi-ta-header-toolbar{padding:10px 0!important;gap:10px!important}body:has(.vx-inventory-list) .fi-ta-search-field{min-width:0!important;flex:1!important}body:has(.vx-inventory-list) .fi-ta-filters-trigger{border:1px solid #7c3aed!important;border-radius:10px!important;color:#c4b5fd!important}
 body:has(.vx-inventory-list) .fi-ta-content{background:transparent!important}body:has(.vx-inventory-list) .fi-ta-table{border-collapse:separate!important;border-spacing:0 12px!important}body:has(.vx-inventory-list) .fi-ta-row{display:grid!important;grid-template-columns:74px 1fr 1fr!important;background:#101827!important;border:1px solid #263248!important;border-radius:16px!important;padding:14px!important;overflow:hidden!important;box-shadow:0 8px 24px rgba(0,0,0,.16)!important}
 body:has(.vx-inventory-list) .fi-ta-cell{border:0!important;padding:5px 4px!important}body:has(.vx-inventory-list) .fi-ta-cell:first-child{grid-row:1/4;grid-column:1;width:70px!important}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(2){grid-column:2/4;font-size:1rem!important}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(3){grid-column:2/4}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(4),body:has(.vx-inventory-list) .fi-ta-cell:nth-child(7){display:none!important}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(5){grid-column:1/4}body:has(.vx-inventory-list) .fi-ta-cell:nth-child(6){grid-column:1/2;margin-top:6px;border:1px solid #263248!important;border-radius:10px!important;padding:10px!important}body:has(.vx-inventory-list) .fi-ta-actions-cell{grid-column:1/4!important;margin-top:6px!important}
 body:has(.vx-inventory-list) .fi-ta-image img{width:64px!important;height:64px!important;min-width:64px!important;border-radius:12px!important}body:has(.vx-inventory-list) .fi-ta-header-cell{display:none!important}
}
</style>
<div x-data x-on:barcode-scanned.window="if ($wire.barcodeScanTargetId) $wire.saveScannedBarcode($event.detail.value)"></div>
<div class="space-y-3 sm:space-y-5 vx-inventory-list" data-vx-page="inventory-center">
 <div class="vx-inventory-mobile-intro sm:hidden"><h2 class="text-2xl font-bold text-gray-950 dark:text-white">Inventory</h2><p class="mt-1 text-sm text-gray-500">Track and manage your inventory items.</p></div>
 <section class="vx-inventory-desktop-tools overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"><div class="p-5"><div class="text-xs font-bold uppercase tracking-[.12em] text-primary-600">Inventory Center</div><h2 class="mt-1 text-xl font-semibold">What are you trying to do?</h2><p class="mt-1 text-sm text-gray-500">Search first when checking stock. Scan, add, receive, or manage locations from here.</p></div></section>
 <div class="vx-health-grid">
  @foreach($this->getStats() as $stat)
   @if($stat['key'])
    <button type="button" wire:click="filterStock('{{ $stat['key'] }}')" class="vx-health {{ $stockHealth === $stat['key'] ? 'active' : '' }}"><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></button>
   @else
    <button type="button" wire:click="filterStock(null)" class="vx-health {{ $stockHealth === $stat['key'] ? 'active' : '' }}"><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></button>
   @endif
  @endforeach
 </div>
 @if($stockHealth)<div class="flex items-center justify-between rounded-xl border border-primary-800/40 bg-primary-950/20 px-4 py-2 text-sm"><span>Showing <strong>{{ match($stockHealth) { 'in' => 'In Stock', 'low' => 'Low Stock', 'out' => 'Out of Stock' } }}</strong> items</span><button wire:click="filterStock(null)" class="font-bold text-primary-400">Show all</button></div>@endif
 <section class="vx-inventory-items-panel overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900"><div class="hidden border-b border-gray-100 px-5 py-3 dark:border-gray-800 sm:block"><h2 class="text-base font-semibold">Inventory Items</h2><p class="mt-0.5 text-xs text-gray-500">Search by item name, SKU, or barcode.</p></div><div class="p-1 sm:p-2">{{ $this->table }}</div></section>
</div>
</x-filament-panels::page>