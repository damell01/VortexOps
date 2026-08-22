<x-filament-panels::page>
    <div class="space-y-5">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <input type="search" wire:model.live.debounce.300ms="searchQuery" placeholder="Search show, Whatnot ID, or streamer…"
                    class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                <select wire:model.live="filterDelivery" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="all">All shipment shows</option>
                    <option value="open">Has open deliveries</option>
                    <option value="delivered">All delivered</option>
                </select>
                <select wire:model.live="sortBy" class="rounded-lg border-gray-300 bg-white text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white">
                    <option value="date">Newest shows</option>
                    <option value="shipments">Most shipments</option>
                    <option value="open">Most open deliveries</option>
                </select>
            </div>
        </div>

        <div class="grid gap-3 lg:grid-cols-2">
            @forelse($this->shows as $show)
                <article class="rounded-xl border border-gray-200 bg-white p-4 transition hover:border-primary-300 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <a href="{{ $this->shipmentsUrl($show->id) }}" class="block truncate text-base font-semibold text-gray-950 hover:text-primary-600 dark:text-white">
                                {{ $show->title }}
                            </a>
                            <div class="mt-1 text-xs text-gray-400">
                                {{ $show->show_date?->format('M j, Y') }}
                                @if($show->start_time) · {{ $show->start_time->format('g:i A') }} @endif
                                @if($show->channel) · {{ $show->channel->name }} @endif
                            </div>
                        </div>
                        @if($show->pending_shipments_count > 0)
                            <span class="shrink-0 rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200">{{ $show->pending_shipments_count }} open</span>
                        @else
                            <span class="shrink-0 rounded-full bg-green-50 px-2.5 py-1 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-200">All delivered</span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        @forelse($show->streamers as $streamer)
                            <span class="rounded-full bg-violet-50 px-2 py-1 text-xs text-violet-700 dark:bg-violet-950 dark:text-violet-200">{{ $streamer->name }}</span>
                        @empty
                            <span class="rounded-full bg-gray-100 px-2 py-1 text-xs text-gray-500 dark:bg-gray-800">Unassigned streamer</span>
                        @endforelse
                    </div>

                    <div class="mt-4 grid grid-cols-3 overflow-hidden rounded-lg border border-gray-100 dark:border-gray-800">
                        <div class="p-3 text-center">
                            <div class="text-xs text-gray-400">Shipments</div>
                            <div class="mt-1 text-lg font-semibold">{{ $show->shipments_count }}</div>
                        </div>
                        <div class="border-x border-gray-100 p-3 text-center dark:border-gray-800">
                            <div class="text-xs text-gray-400">Delivered</div>
                            <div class="mt-1 text-lg font-semibold text-green-600">{{ $show->delivered_shipments_count }}</div>
                        </div>
                        <div class="p-3 text-center">
                            <div class="text-xs text-gray-400">Shipping Spend</div>
                            <div class="mt-1 text-lg font-semibold">${{ number_format((float)($show->shipments_sum_shipping_cost ?? 0), 2) }}</div>
                        </div>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2">
                        <a href="{{ $this->shipmentsUrl($show->id) }}" class="rounded-lg bg-primary-600 px-3 py-2.5 text-center text-sm font-semibold text-white">
                            View {{ $show->shipments_count }} Shipments
                        </a>
                        <a href="{{ $this->showUrl($show->id) }}" class="rounded-lg border border-gray-200 px-3 py-2.5 text-center text-sm font-medium dark:border-gray-700">
                            Open Show
                        </a>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-12 text-center text-gray-500 dark:border-gray-700">
                    No shows with shipments match these filters.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
