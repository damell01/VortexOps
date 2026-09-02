<x-filament-panels::page>
    <style>
        .vx-catalog-toolbar{display:flex;gap:.65rem;align-items:center;flex-wrap:wrap}
        .vx-catalog-search{flex:1;min-width:220px}
        .vx-catalog-input,.vx-catalog-select{width:100%;min-height:44px;border:1px solid rgb(209 213 219);border-radius:.75rem;background:white;padding:.65rem .8rem;font-size:.875rem}
        .dark .vx-catalog-input,.dark .vx-catalog-select{background:rgb(17 24 39);border-color:rgb(75 85 99);color:white}
        .vx-catalog-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
        .vx-product-card{overflow:hidden;border:1px solid rgb(229 231 235);border-radius:1rem;background:white;transition:.15s ease;display:flex;flex-direction:column;min-width:0}
        .vx-product-card:hover{transform:translateY(-1px);box-shadow:0 8px 24px rgba(15,23,42,.08);border-color:rgb(191 219 254)}
        .dark .vx-product-card{background:rgb(17 24 39);border-color:rgb(55 65 81)}
        .vx-product-image{aspect-ratio:4/3;background:rgb(249 250 251);display:flex;align-items:center;justify-content:center;overflow:hidden;border-bottom:1px solid rgb(243 244 246)}
        .dark .vx-product-image{background:rgb(31 41 55);border-color:rgb(55 65 81)}
        .vx-product-image img{width:100%;height:100%;object-fit:contain;padding:.65rem}
        .vx-product-body{padding:.8rem;display:flex;flex-direction:column;gap:.55rem;flex:1}
        .vx-product-title{font-weight:750;font-size:.875rem;line-height:1.2rem;color:rgb(17 24 39);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
        .dark .vx-product-title{color:white}
        .vx-product-meta{font-size:.7rem;color:rgb(107 114 128);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .vx-product-row{display:flex;align-items:center;justify-content:space-between;gap:.5rem}
        .vx-stock{display:inline-flex;align-items:center;border-radius:999px;padding:.25rem .5rem;font-size:.67rem;font-weight:800}
        .vx-stock-in{background:rgb(236 253 245);color:rgb(4 120 87)}
        .vx-stock-low{background:rgb(255 247 237);color:rgb(194 65 12)}
        .vx-stock-out{background:rgb(254 242 242);color:rgb(185 28 28)}
        .dark .vx-stock-in{background:rgba(4,120,87,.18);color:rgb(110 231 183)}
        .dark .vx-stock-low{background:rgba(194,65,12,.18);color:rgb(253 186 116)}
        .dark .vx-stock-out{background:rgba(185,28,28,.18);color:rgb(252 165 165)}
        .vx-figure{font-size:.8rem;font-weight:800;color:rgb(31 41 55)}
        .dark .vx-figure{color:rgb(229 231 235)}
        .vx-empty{border:1px dashed rgb(209 213 219);border-radius:1rem;padding:3rem 1rem;text-align:center;color:rgb(107 114 128)}
        .vx-catalog-note{font-size:.75rem;color:rgb(107 114 128)}
        @media(min-width:720px){.vx-catalog-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.vx-product-body{padding:1rem}.vx-product-title{font-size:.95rem}}
        @media(min-width:1100px){.vx-catalog-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
        @media(min-width:1450px){.vx-catalog-grid{grid-template-columns:repeat(5,minmax(0,1fr))}}
    </style>

    <div class="space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-xs font-bold uppercase tracking-[.12em] text-primary-600">Visual inventory</div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">Browse the catalog</h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search by product, SKU, UPC or brand. Open a card for full stock and movement details.</p>
                </div>
                <a href="{{ $this->tableUrl() }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Table view</a>
            </div>

            <div class="vx-catalog-toolbar mt-4">
                <div class="vx-catalog-search">
                    <input wire:model.live.debounce.300ms="search" class="vx-catalog-input" type="search" placeholder="Search products, SKU, UPC, brand…" />
                </div>
                <div style="min-width:160px">
                    <select wire:model.live="stock" class="vx-catalog-select">
                        <option value="all">All stock</option>
                        <option value="in">In stock</option>
                        <option value="low">Low stock</option>
                        <option value="out">Out of stock</option>
                    </select>
                </div>
                @if(filled($search) || $stock !== 'all')
                    <button wire:click="clearFilters" class="inline-flex min-h-11 items-center rounded-lg px-3 text-sm font-semibold text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-950/30">Clear</button>
                @endif
            </div>
        </section>

        <div class="flex items-center justify-between gap-3">
            <div class="vx-catalog-note">Showing up to 60 matching products</div>
            <div class="vx-catalog-note">{{ $this->items->count() }} shown</div>
        </div>

        @if($this->items->isNotEmpty())
            <div class="vx-catalog-grid">
                @foreach($this->items as $item)
                    @php
                        $onHand = (float) ($item->stock_sum_quantity ?? 0);
                        $reorder = $item->reorder_level !== null ? (float) $item->reorder_level : null;
                        $stockState = $onHand <= 0 ? 'out' : (($reorder !== null && $onHand <= $reorder) ? 'low' : 'in');
                        $locations = $item->stock->where('quantity', '>', 0)->pluck('location.name')->filter()->unique()->take(2)->implode(', ');
                    @endphp
                    <a href="{{ $this->itemUrl($item->id) }}" class="vx-product-card">
                        <div class="vx-product-image">
                            @if($item->imageUrl())<img loading="lazy" src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" />@endif
                        </div>
                        <div class="vx-product-body">
                            <div>
                                <div class="vx-product-title">{{ $item->name }}</div>
                                <div class="vx-product-meta mt-1">{{ $item->sku ?: 'No SKU' }}@if($item->brand) · {{ $item->brand }}@endif</div>
                            </div>
                            <div class="vx-product-row mt-auto">
                                <span class="vx-stock vx-stock-{{ $stockState }}">
                                    {{ $stockState === 'out' ? 'Out of stock' : ($stockState === 'low' ? 'Low stock' : 'In stock') }}
                                </span>
                                <span class="vx-figure">{{ number_format($onHand) }} units</span>
                            </div>
                            <div class="vx-product-row">
                                <span class="vx-product-meta">{{ $locations ?: ($item->category ?: 'Inventory') }}</span>
                                @if($item->sale_price !== null)<span class="vx-product-meta" style="font-weight:700">${{ number_format((float)$item->sale_price,2) }}</span>@endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            <div class="vx-empty">
                <x-heroicon-o-magnifying-glass class="mx-auto h-8 w-8" />
                <div class="mt-2 font-semibold text-gray-800 dark:text-gray-200">No matching products</div>
                <div class="mt-1 text-sm">Try another search or clear the stock filter.</div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
