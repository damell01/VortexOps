<x-filament-panels::page>
    @php($counts = $this->counts)

    <div class="space-y-4">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Inventory catalog</div>
                        <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Browse products visually</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">Photos, stock, SKU and pricing at a glance. Your existing inventory permissions still control what appears here.</p>
                    </div>
                    <div class="w-full lg:max-w-md">
                        <label class="sr-only" for="catalog-search">Search inventory</label>
                        <input id="catalog-search" wire:model.live.debounce.300ms="search" type="search" placeholder="Search name, SKU, UPC, brand…"
                            class="block min-h-11 w-full rounded-xl border-gray-300 bg-white text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white" />
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-4">
                @foreach ([
                    'all' => ['All', $counts['all']],
                    'available' => ['Available', $counts['available']],
                    'low' => ['Low stock', $counts['low']],
                    'out' => ['Out', $counts['out']],
                ] as $value => [$label, $count])
                    <button type="button" wire:click="$set('stock', '{{ $value }}')"
                        class="bg-white px-3 py-3 text-left transition hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800 {{ $stock === $value ? 'ring-2 ring-inset ring-primary-500' : '' }}">
                        <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs">{{ $label }}</div>
                        <div class="mt-0.5 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($count) }}</div>
                    </button>
                @endforeach
            </div>
        </section>

        <div wire:loading.flex wire:target="search,stock" class="items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-3 text-xs text-gray-500 dark:border-gray-700 dark:bg-gray-900">
            <x-heroicon-m-arrow-path class="h-4 w-4 animate-spin" /> Updating catalog…
        </div>

        <section wire:loading.class="opacity-60" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
            @forelse($this->items as $item)
                @php
                    $onHand = (float) ($item->stock_sum_quantity ?? 0);
                    $isLow = $item->reorder_level !== null && $onHand > 0 && $onHand <= (float) $item->reorder_level;
                    $stockLabel = $onHand <= 0 ? 'Out of stock' : ($isLow ? 'Low stock' : 'In stock');
                    $stockTone = $onHand <= 0
                        ? 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-200'
                        : ($isLow ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200' : 'bg-green-50 text-green-700 dark:bg-green-950/40 dark:text-green-200');
                    $meta = collect([$item->year, $item->brand, $item->set_name ?: $item->category])->filter()->implode(' · ');
                @endphp

                <a href="{{ $this->itemUrl($item) }}" class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-700">
                    <div class="aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800">
                        @if($item->imageUrl())
                            <img src="{{ $item->imageUrl() }}" alt="{{ $item->name }}" loading="lazy" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]" />
                        @else
                            <div class="flex h-full items-center justify-center"><x-heroicon-o-photo class="h-10 w-10 text-gray-300" /></div>
                        @endif
                    </div>

                    <div class="p-3">
                        <div class="flex items-start justify-between gap-2">
                            <h3 class="line-clamp-2 min-h-10 text-sm font-semibold leading-5 text-gray-950 dark:text-white">{{ $item->name }}</h3>
                            <x-heroicon-m-chevron-right class="mt-0.5 h-4 w-4 shrink-0 text-gray-300 group-hover:text-primary-500" />
                        </div>

                        @if($meta)
                            <div class="mt-1 truncate text-[10px] text-gray-500 dark:text-gray-400">{{ $meta }}</div>
                        @endif

                        <div class="mt-3 flex items-end justify-between gap-2">
                            <div>
                                <div class="text-[10px] uppercase tracking-wide text-gray-400">On hand</div>
                                <div class="text-lg font-semibold leading-none text-gray-950 dark:text-white">{{ number_format($onHand, $onHand == floor($onHand) ? 0 : 2) }}</div>
                            </div>
                            @if((float) $item->sale_price > 0)
                                <div class="text-right">
                                    <div class="text-[10px] uppercase tracking-wide text-gray-400">Price</div>
                                    <div class="text-sm font-semibold text-gray-900 dark:text-gray-100">${{ number_format((float)$item->sale_price, 2) }}</div>
                                </div>
                            @endif
                        </div>

                        <div class="mt-3 flex items-center justify-between gap-2">
                            <span class="rounded-full px-2 py-1 text-[10px] font-semibold {{ $stockTone }}">{{ $stockLabel }}</span>
                            <span class="max-w-[55%] truncate font-mono text-[10px] text-gray-400">{{ $item->sku }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white px-5 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                    <x-heroicon-o-magnifying-glass class="mx-auto h-9 w-9 text-gray-300" />
                    <div class="mt-2 text-sm font-semibold text-gray-800 dark:text-gray-100">No products match</div>
                    <p class="mt-1 text-xs text-gray-500">Try another search or stock filter.</p>
                </div>
            @endforelse
        </section>
    </div>
</x-filament-panels::page>
