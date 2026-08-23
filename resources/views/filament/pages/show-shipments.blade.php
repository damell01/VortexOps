<x-filament-panels::page>
    <div class="space-y-3 sm:space-y-5" data-vx-page="show-shipments">
        <section class="rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900 sm:p-4">
            <div class="mb-3">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Find a show</h2>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400">Choose the show first, then open its shipment list. This keeps mobile usable even with a large shipping history.</p>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:gap-3 md:grid-cols-3">
                <div class="relative">
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input type="search" wire:model.live.debounce.300ms="searchQuery" placeholder="Search show or streamer…"
                        class="min-h-11 w-full rounded-lg border-gray-300 bg-white pl-9 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                </div>
                <select wire:model.live="filterDelivery" class="min-h-11 rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="all">All shipment shows</option>
                    <option value="open">Has open deliveries</option>
                    <option value="delivered">All delivered</option>
                </select>
                <select wire:model.live="sortBy" class="min-h-11 rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="date">Newest shows</option>
                    <option value="shipments">Most shipments</option>
                    <option value="open">Most open deliveries</option>
                </select>
            </div>
        </section>

        <div class="grid gap-2.5 sm:gap-3 lg:grid-cols-2">
            @forelse($this->shows as $show)
                <article class="overflow-hidden rounded-xl border border-gray-200 bg-white transition hover:border-primary-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="p-3.5 sm:p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 flex-1">
                                <a href="{{ $this->shipmentsUrl($show->id) }}" class="block truncate text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white sm:text-base">
                                    {{ $show->title }}
                                </a>
                                <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-1 text-[10px] text-gray-400 sm:text-xs">
                                    <span>{{ $show->show_date?->format('M j, Y') }}</span>
                                    @if($show->start_time)<span>· {{ $show->start_time->format('g:i A') }}</span>@endif
                                    @if($show->channel)<span class="hidden sm:inline">· {{ $show->channel->name }}</span>@endif
                                </div>
                            </div>

                            @if($show->pending_shipments_count > 0)
                                <span class="shrink-0 rounded-full bg-amber-50 px-2 py-1 text-[10px] font-semibold text-amber-700 dark:bg-amber-950 dark:text-amber-200 sm:text-xs">{{ $show->pending_shipments_count }} open</span>
                            @else
                                <span class="shrink-0 rounded-full bg-green-50 px-2 py-1 text-[10px] font-semibold text-green-700 dark:bg-green-950 dark:text-green-200 sm:text-xs">Delivered</span>
                            @endif
                        </div>

                        <div class="mt-2 flex min-h-6 flex-wrap gap-1">
                            @forelse($show->streamers as $streamer)
                                <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[10px] text-violet-700 dark:bg-violet-950 dark:text-violet-200 sm:text-xs">{{ $streamer->name }}</span>
                            @empty
                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] text-gray-500 dark:bg-gray-800 sm:text-xs">No streamer</span>
                            @endforelse
                        </div>

                        <div class="mt-3 grid grid-cols-3 divide-x divide-gray-100 rounded-lg bg-gray-50 dark:divide-gray-800 dark:bg-gray-800/50">
                            <div class="px-2 py-2 text-center sm:py-2.5">
                                <div class="text-[9px] font-medium uppercase tracking-wide text-gray-400 sm:text-[10px]">Shipments</div>
                                <div class="mt-0.5 text-base font-semibold leading-none text-gray-950 dark:text-white sm:text-lg">{{ $show->shipments_count }}</div>
                            </div>
                            <div class="px-2 py-2 text-center sm:py-2.5">
                                <div class="text-[9px] font-medium uppercase tracking-wide text-gray-400 sm:text-[10px]">Delivered</div>
                                <div class="mt-0.5 text-base font-semibold leading-none text-green-600 sm:text-lg">{{ $show->delivered_shipments_count }}</div>
                            </div>
                            <div class="px-2 py-2 text-center sm:py-2.5">
                                <div class="text-[9px] font-medium uppercase tracking-wide text-gray-400 sm:text-[10px]">Shipping</div>
                                <div class="mt-0.5 truncate text-sm font-semibold leading-none text-gray-950 dark:text-white sm:text-lg">${{ number_format((float)($show->shipments_sum_shipping_cost ?? 0), 2) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-[1fr_auto] gap-px border-t border-gray-100 bg-gray-100 dark:border-gray-800 dark:bg-gray-800">
                        <a href="{{ $this->shipmentsUrl($show->id) }}" class="inline-flex min-h-11 items-center justify-center gap-1.5 bg-primary-600 px-3 text-sm font-semibold text-white hover:bg-primary-500">
                            View Shipments
                            <x-heroicon-m-chevron-right class="h-4 w-4" />
                        </a>
                        <a href="{{ $this->showUrl($show->id) }}" class="inline-flex min-h-11 items-center justify-center bg-white px-4 text-xs font-medium text-gray-600 dark:bg-gray-900 dark:text-gray-300">
                            Show
                        </a>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-700">
                    <x-heroicon-o-truck class="mx-auto h-8 w-8 text-gray-300 dark:text-gray-600" />
                    <div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No shipment shows found</div>
                    <p class="mt-1 text-xs text-gray-500">Try changing the filters or search.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
