<x-filament-panels::page>
    @php
        $products = $this->products();
        $stats = [
            'products' => $products->count(),
            'units' => (float) $products->sum(fn ($product) => (float) ($product->available_units ?? 0)),
            'low' => $products->filter(fn ($product) => $product->reorder_level !== null
                && (float) ($product->available_units ?? 0) <= (float) $product->reorder_level)->count(),
        ];
        $canManage = (auth()->user()?->isAdmin() || auth()->user()?->isOwner()) ?? false;
    @endphp

    <div class="space-y-4">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[.12em] text-primary-600">Visual inventory</div>
                        <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">Browse the catalog</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search by product, SKU, UPC, brand or set. Stock shown here is scoped to what you can work with.</p>
                    </div>
                    @if($canManage)
                        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('index') }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-200 px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Open full inventory table</a>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-3 gap-px overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800">
                    @foreach ([['Products', $stats['products']], ['Units', $stats['units']], ['Low Stock', $stats['low']]] as [$label, $value])
                        <div class="bg-white px-3 py-3 dark:bg-gray-900">
                            <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</div>
                            <div class="mt-1 text-xl font-bold text-gray-950 dark:text-white">{{ number_format((float) $value) }}</div>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 relative">
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search inventory..."
                        class="w-full rounded-xl border-gray-300 py-2.5 pl-9 pr-3 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6">
            @forelse($products as $product)
                @php
                    $units = (float) ($product->available_units ?? 0);
                    $low = $product->reorder_level !== null && $units <= (float) $product->reorder_level;
                    $cost = $product->costBasis();
                @endphp
                <article class="group overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                    <div class="aspect-square overflow-hidden bg-gray-100 dark:bg-gray-800">
                        @if($product->imageUrl())
                            <img src="{{ $product->imageUrl() }}" alt="{{ $product->name }}" class="h-full w-full object-cover transition duration-200 group-hover:scale-[1.02]" loading="lazy" />
                        @else
                            <div class="flex h-full items-center justify-center"><x-heroicon-o-cube class="h-10 w-10 text-gray-300" /></div>
                        @endif
                    </div>
                    <div class="p-2.5 sm:p-3">
                        <div class="line-clamp-2 min-h-9 text-xs font-semibold leading-4 text-gray-950 dark:text-white sm:text-sm">{{ $product->name }}</div>
                        <div class="mt-1 truncate font-mono text-[10px] text-gray-400">{{ $product->sku ?: $product->upc ?: 'No SKU' }}</div>

                        <div class="mt-2 flex items-center justify-between gap-2">
                            <span class="rounded-full px-2 py-1 text-[10px] font-bold {{ $low ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200' }}">
                                {{ number_format($units) }} in stock
                            </span>
                            @if($cost !== null)
                                <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">${{ number_format($cost, 2) }}</span>
                            @endif
                        </div>

                        @if($product->brand || $product->set_name)
                            <div class="mt-2 truncate text-[10px] text-gray-500 dark:text-gray-400">{{ collect([$product->brand, $product->set_name])->filter()->join(' · ') }}</div>
                        @endif
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 bg-white px-4 py-12 text-center dark:border-gray-700 dark:bg-gray-900">
                    <x-heroicon-o-squares-2x2 class="mx-auto h-9 w-9 text-gray-300" />
                    <div class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200">No products found</div>
                    <p class="mt-1 text-xs text-gray-500">Try a different search or check the inventory locations available to your account.</p>
                </div>
            @endforelse
        </section>

        @if($products->count() >= 72)
            <div class="text-center text-xs text-gray-500">Showing the first 72 matching products. Narrow your search to find a specific item.</div>
        @endif
    </div>
</x-filament-panels::page>
