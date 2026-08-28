<x-filament-panels::page>
@php
    $s = $this->inventorySnapshot;
    $cards = [
        ['label'=>'Total Items','value'=>$s['total'],'pct'=>$s['percentages']['total'],'key'=>null,'tone'=>'primary','icon'=>'heroicon-o-cube'],
        ['label'=>'In Stock','value'=>$s['in'],'pct'=>$s['percentages']['in'],'key'=>'in','tone'=>'success','icon'=>'heroicon-o-check-circle'],
        ['label'=>'Low Stock','value'=>$s['low'],'pct'=>$s['percentages']['low'],'key'=>'low','tone'=>'warning','icon'=>'heroicon-o-exclamation-triangle'],
        ['label'=>'Out of Stock','value'=>$s['out'],'pct'=>$s['percentages']['out'],'key'=>'out','tone'=>'danger','icon'=>'heroicon-o-x-circle'],
    ];
    $movementLabels = \App\Models\InventoryMovement::movementTypeLabels();
@endphp
<style>
.vx-workspace{max-width:1440px;margin:0 auto}.vx-wcard{border:1px solid rgb(229 231 235);background:#fff;border-radius:18px;box-shadow:0 1px 2px rgba(15,23,42,.03)}.dark .vx-wcard{border-color:#263248;background:#101827}.vx-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px}.vx-kpi{display:flex;gap:14px;padding:18px;transition:.15s}.vx-kpi:hover{transform:translateY(-1px);border-color:#8b5cf6}.vx-icon{display:grid;height:44px;width:44px;place-items:center;border-radius:14px;background:rgb(245 243 255);color:#7c3aed}.vx-kpi[data-tone="success"] .vx-icon{background:#ecfdf5;color:#059669}.vx-kpi[data-tone="warning"] .vx-icon{background:#fffbeb;color:#d97706}.vx-kpi[data-tone="danger"] .vx-icon{background:#fef2f2;color:#dc2626}.vx-main-grid{display:grid;grid-template-columns:minmax(0,1.6fr) minmax(280px,.8fr);gap:16px}.vx-split{display:grid;grid-template-columns:1fr 1.15fr;gap:16px}.vx-row{display:flex;align-items:center;gap:12px;padding:13px 0;border-top:1px solid rgb(243 244 246)}.dark .vx-row{border-color:#1f2937}.vx-action{display:flex;align-items:center;gap:12px;border:1px solid rgb(229 231 235);border-radius:14px;padding:13px 14px;transition:.15s}.dark .vx-action{border-color:#263248}.vx-action:hover{border-color:#8b5cf6;background:rgba(124,58,237,.04)}
@media(max-width:900px){.vx-kpis{grid-template-columns:repeat(2,1fr)}.vx-main-grid,.vx-split{grid-template-columns:1fr}}
@media(max-width:640px){body:has(.vx-workspace) .fi-page-header{padding-bottom:.25rem}.vx-kpis{grid-template-columns:1fr 1fr;gap:8px}.vx-kpi{padding:12px;gap:9px}.vx-icon{height:36px;width:36px;border-radius:10px}.vx-kpi .vx-big{font-size:1.3rem}.vx-top-actions{display:grid!important;grid-template-columns:1fr 1fr;width:100%}.vx-wcard{border-radius:14px}.vx-table-head{display:none}.vx-activity-row{display:grid!important;grid-template-columns:1fr auto;gap:4px 10px}.vx-activity-meta{grid-column:1/3}}
</style>
<div class="vx-workspace space-y-4">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><h2 class="text-2xl font-bold text-gray-950 dark:text-white">Inventory workspace</h2><p class="mt-1 text-sm text-gray-500">Health, receiving, movement history, and the actions you use most.</p></div>
        <div class="vx-top-actions flex flex-wrap gap-2">
            <a href="{{ $this->scanUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold dark:border-gray-700 dark:bg-gray-900">Scan Inventory</a>
            <a href="{{ $this->receiveUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold dark:border-gray-700 dark:bg-gray-900">Receive Inventory</a>
            <a href="{{ $this->quickAddUrl() }}" class="rounded-xl border border-gray-200 bg-white px-4 py-2.5 text-center text-sm font-semibold dark:border-gray-700 dark:bg-gray-900">Quick Add</a>
            <a href="{{ $this->importUrl() }}" class="rounded-xl bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white">Import</a>
        </div>
    </div>

    <div class="vx-kpis">
        @foreach($cards as $card)
            <a href="{{ $this->inventoryUrl($card['key']) }}" class="vx-wcard vx-kpi" data-tone="{{ $card['tone'] }}">
                <div class="vx-icon"><x-filament::icon :icon="$card['icon']" class="h-6 w-6" /></div>
                <div class="min-w-0"><div class="text-xs font-medium text-gray-500">{{ $card['label'] }}</div><div class="vx-big mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($card['value']) }}</div><div class="mt-1 text-xs text-gray-400">{{ number_format($card['pct'],1) }}% of inventory</div></div>
            </a>
        @endforeach
    </div>

    <div class="vx-main-grid">
        <div class="space-y-4">
            <div class="vx-split">
                <section class="vx-wcard p-5">
                    <div class="flex items-center justify-between"><div><h3 class="font-bold">Needs Attention</h3><p class="mt-1 text-xs text-gray-500">Inventory conditions worth reviewing.</p></div><a href="{{ $this->inventoryUrl() }}" class="text-xs font-semibold text-primary-600">View all</a></div>
                    <a href="{{ $this->inventoryUrl('out') }}" class="vx-row"><div class="vx-icon !h-9 !w-9 !rounded-full" style="background:#fef2f2;color:#dc2626"><x-filament::icon icon="heroicon-o-x-circle" class="h-5 w-5" /></div><div class="min-w-0 flex-1"><div class="text-sm font-semibold">Out of Stock Items</div><div class="text-xs text-gray-500">Items that need to be restocked</div></div><span class="rounded-full bg-red-50 px-2.5 py-1 text-xs font-bold text-red-600">{{ $s['out'] }}</span></a>
                    <a href="{{ $this->inventoryUrl('low') }}" class="vx-row"><div class="vx-icon !h-9 !w-9 !rounded-full" style="background:#fffbeb;color:#d97706"><x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-5 w-5" /></div><div class="min-w-0 flex-1"><div class="text-sm font-semibold">Low Stock Items</div><div class="text-xs text-gray-500">At or below the saved reorder level</div></div><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-600">{{ $s['low'] }}</span></a>
                    <div class="vx-row"><div class="vx-icon !h-9 !w-9 !rounded-full"><x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-5 w-5" /></div><div class="min-w-0 flex-1"><div class="text-sm font-semibold">No Reorder Level</div><div class="text-xs text-gray-500">Items without a reorder threshold</div></div><span class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-bold text-violet-600">{{ $s['no_reorder'] }}</span></div>
                </section>

                <section class="vx-wcard p-5">
                    <div class="flex items-center justify-between"><div><h3 class="font-bold">Recently Restocked</h3><p class="mt-1 text-xs text-gray-500">Latest opening-stock and return movements.</p></div></div>
                    @forelse($this->recentRestocks as $move)
                        <a href="{{ $move->item ? $this->itemUrl($move->item->id) : '#' }}" class="vx-row">
                            @if($move->item)<img src="{{ $move->item->imageUrl() }}" alt="" class="h-10 w-10 rounded-lg border border-gray-200 object-cover dark:border-gray-700">@endif
                            <div class="min-w-0 flex-1"><div class="truncate text-sm font-semibold">{{ $move->item?->name ?? 'Inventory item' }}</div><div class="truncate text-xs text-gray-500">{{ $move->toLocation?->name ?? 'Location not set' }} · {{ $move->changeLabel() }} units</div></div><div class="text-right text-xs text-gray-400">{{ $move->created_at?->format('M j') }}<br>{{ $move->created_at?->format('g:i A') }}</div>
                        </a>
                    @empty <div class="py-8 text-center text-sm text-gray-500">No recent restock movements.</div> @endforelse
                </section>
            </div>

            <section class="vx-wcard overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4"><div><h3 class="font-bold">Recent Stock Activity</h3><p class="mt-1 text-xs text-gray-500">The newest inventory movements across visible locations.</p></div></div>
                <div class="overflow-x-auto px-5 pb-3">
                    <div class="vx-table-head grid min-w-[760px] grid-cols-[120px_150px_1fr_180px_80px] border-b border-gray-100 py-2 text-[11px] font-bold uppercase tracking-wide text-gray-400 dark:border-gray-800"><span>Date</span><span>Type</span><span>Item</span><span>Location</span><span class="text-right">Qty</span></div>
                    @forelse($this->recentMovements as $move)
                        @php $change=$move->signedChange(); $location=$move->toLocation?->name ?? $move->fromLocation?->name ?? '—'; @endphp
                        <div class="vx-activity-row grid min-w-[760px] grid-cols-[120px_150px_1fr_180px_80px] items-center border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                            <span class="text-xs text-gray-500">{{ $move->created_at?->format('M j, g:i A') }}</span><span><span class="rounded-full bg-gray-100 px-2 py-1 text-xs dark:bg-gray-800">{{ $movementLabels[$move->movement_type] ?? ucfirst(str_replace('_',' ',$move->movement_type)) }}</span></span><span class="truncate font-medium">{{ $move->item?->name ?? 'Inventory item' }}</span><span class="vx-activity-meta truncate text-gray-500">{{ $location }}</span><span class="text-right font-bold {{ $change < 0 ? 'text-red-500' : 'text-emerald-600' }}">{{ $move->changeLabel() }}</span>
                        </div>
                    @empty <div class="py-10 text-center text-sm text-gray-500">No stock activity yet.</div> @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="vx-wcard p-5"><div class="text-sm font-semibold text-gray-500">Inventory Value</div><div class="mt-2 text-3xl font-bold text-gray-950 dark:text-white">${{ number_format($s['value'],2) }}</div><div class="mt-1 text-xs text-gray-500">Current on-hand value using each item's effective cost.</div><div class="mt-5 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-primary-500" style="width:{{ min(100,max(4,$s['percentages']['in'])) }}%"></div></div><div class="mt-2 text-xs text-gray-400">{{ number_format($s['percentages']['in'],1) }}% of catalog currently in stock</div></section>
            <section class="vx-wcard p-5"><h3 class="font-bold">Quick Actions</h3><div class="mt-4 space-y-2">
                @foreach([
                    [$this->scanUrl(),'heroicon-o-qr-code','Scan Inventory','Scan items and update quantities'],
                    [$this->receiveUrl(),'heroicon-o-inbox-arrow-down','Receive Inventory','Receive stock from a shipment or pallet'],
                    [$this->quickAddUrl(),'heroicon-o-plus','Quick Add Item','Create an inventory item quickly'],
                    [$this->importUrl(),'heroicon-o-arrow-up-tray','Import Inventory','Import known products from a sheet'],
                    [$this->locationsUrl(),'heroicon-o-map-pin','Locations','Manage inventory locations'],
                    [$this->vendorsUrl(),'heroicon-o-building-storefront','Vendors','Open supplier records'],
                ] as [$url,$icon,$title,$sub])
                    <a href="{{ $url }}" class="vx-action"><div class="vx-icon !h-9 !w-9"><x-filament::icon :icon="$icon" class="h-5 w-5" /></div><div class="min-w-0 flex-1"><div class="text-sm font-semibold">{{ $title }}</div><div class="truncate text-xs text-gray-500">{{ $sub }}</div></div><span class="text-gray-400">›</span></a>
                @endforeach
            </div></section>
        </aside>
    </div>
</div>
</x-filament-panels::page>
