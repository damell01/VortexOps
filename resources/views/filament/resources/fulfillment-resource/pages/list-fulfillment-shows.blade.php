<x-filament-panels::page>
    @php
        $cards = $this->getQueueCards();
        $toneClass = fn (string $tone) => match ($tone) {
            'danger' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-900',
            'success' => 'bg-green-50 text-green-700 ring-green-200 dark:bg-green-950/40 dark:text-green-200 dark:ring-green-900',
            'warning' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-900',
            default => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-900',
        };
        $buttonClass = fn (string $tone) => match ($tone) {
            'danger' => 'bg-red-600 hover:bg-red-500',
            'success' => 'bg-green-600 hover:bg-green-500',
            'warning' => 'bg-amber-600 hover:bg-amber-500',
            default => 'bg-primary-600 hover:bg-primary-500',
        };
    @endphp

    <div class="space-y-4" data-vx-page="fulfillment-card-workspace">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-[.14em] text-primary-600 sm:text-xs">Fulfillment Workspace</div>
                        <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">Pack the next show, not the next table row</h2>
                        <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">The active work queue is card-first. Open the show, pack against the streamer log, build boxes, seal them, and complete fulfillment from one flow.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-[10px] sm:text-xs">
                        <div class="rounded-xl bg-red-50 px-3 py-2 dark:bg-red-950/30"><div class="font-bold text-red-700 dark:text-red-200">{{ $cards->where('stage','Needs Attention')->count() }}</div><div class="text-red-600/80 dark:text-red-300">attention</div></div>
                        <div class="rounded-xl bg-blue-50 px-3 py-2 dark:bg-blue-950/30"><div class="font-bold text-blue-700 dark:text-blue-200">{{ $cards->where('stage','Packing')->count() + $cards->where('stage','Seal Boxes')->count() }}</div><div class="text-blue-600/80 dark:text-blue-300">active</div></div>
                        <div class="rounded-xl bg-green-50 px-3 py-2 dark:bg-green-950/30"><div class="font-bold text-green-700 dark:text-green-200">{{ $cards->where('stage','Completed')->count() }}</div><div class="text-green-600/80 dark:text-green-300">done</div></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-3 xl:grid-cols-2">
            @forelse($cards as $card)
                @php
                    $show = $card['show'];
                    $assigned = $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Unassigned';
                    $streamer = $show->streamers->pluck('name')->join(', ') ?: 'No streamer';
                @endphp
                <article class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-gray-700 dark:bg-gray-900 dark:hover:border-primary-700">
                    <div class="p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate text-base font-bold text-gray-950 dark:text-white">{{ $show->title ?: 'Show #'.$show->id }}</h3>
                                    <span class="rounded-full px-2.5 py-1 text-[10px] font-bold ring-1 ring-inset sm:text-xs {{ $toneClass($card['tone']) }}">{{ $card['stage'] }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                    <span>{{ $show->show_date?->format('M j, Y') }}</span>
                                    <span>· {{ $streamer }}</span>
                                    @if($show->channel)<span>· {{ $show->channel->name }}</span>@endif
                                </div>
                            </div>
                            <div class="shrink-0 rounded-xl bg-gray-50 px-2.5 py-2 text-right dark:bg-gray-800">
                                <div class="text-base font-bold text-gray-950 dark:text-white">{{ $card['packed_units'] }}/{{ $card['total_units'] }}</div>
                                <div class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">units</div>
                            </div>
                        </div>

                        <div class="mt-4">
                            <div class="mb-1.5 flex items-center justify-between text-[10px] font-semibold text-gray-500 sm:text-xs"><span>Packing progress</span><span>{{ $card['progress'] }}%</span></div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-primary-600 transition-all" style="width:{{ $card['progress'] }}%"></div></div>
                        </div>

                        <div class="mt-4 grid grid-cols-3 gap-2">
                            <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-gray-800"><div class="text-sm font-bold text-gray-950 dark:text-white">{{ $card['boxes'] }}</div><div class="text-[9px] uppercase tracking-wide text-gray-500">boxes</div></div>
                            <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-gray-800"><div class="text-sm font-bold {{ $card['open_boxes'] ? 'text-amber-600' : 'text-gray-950 dark:text-white' }}">{{ $card['open_boxes'] }}</div><div class="text-[9px] uppercase tracking-wide text-gray-500">open</div></div>
                            <div class="rounded-xl bg-gray-50 p-2.5 dark:bg-gray-800"><div class="text-sm font-bold {{ $card['issues'] ? 'text-red-600' : 'text-gray-950 dark:text-white' }}">{{ $card['issues'] }}</div><div class="text-[9px] uppercase tracking-wide text-gray-500">issues</div></div>
                        </div>

                        <div class="mt-4 flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-gray-800">
                            <div class="min-w-0"><div class="text-[9px] font-semibold uppercase tracking-wide text-gray-500">Assigned</div><div class="truncate text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $assigned }}</div></div>
                            <span class="text-[10px] text-gray-500">{{ (int)$show->shipments_count }} shipment(s)</span>
                        </div>
                    </div>
                    <div class="grid grid-cols-[auto_1fr] gap-2 border-t border-gray-100 bg-gray-50/60 p-3 dark:border-gray-800 dark:bg-gray-900 sm:p-4">
                        <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $show]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl border border-gray-300 px-3 text-xs font-semibold text-gray-700 hover:bg-white dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Show Details</a>
                        <a href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('view', ['record' => $show]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl px-4 text-xs font-bold text-white {{ $buttonClass($card['tone']) }}">{{ $card['action'] }}</a>
                    </div>
                </article>
            @empty
                <div class="col-span-full rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center dark:border-gray-700 dark:bg-gray-900"><x-heroicon-o-check-circle class="mx-auto h-9 w-9 text-green-500"/><div class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200">No active fulfillment work</div></div>
            @endforelse
        </section>

        <details class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-gray-700 dark:text-gray-200 sm:px-5">Advanced queue, tabs & filters</summary>
            <div class="border-t border-gray-100 p-3 dark:border-gray-800 sm:p-4">{{ $this->table }}</div>
        </details>
    </div>
</x-filament-panels::page>
