<x-filament-panels::page>
    @php ['rows' => $rows, 'totals' => $totals] = $this->dashboardData(); @endphp
    @php $recentShows = $this->recentShows(); @endphp
    <div class="space-y-4" wire:poll.visible.30s>
        <header class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs font-semibold uppercase tracking-wider text-violet-600">Fulfillment Center · All shows</p>
            <h2 class="mt-1 text-xl font-bold text-gray-950 dark:text-white">Shipment tracking</h2>
            <p class="mt-1 text-sm text-gray-500">Search historical packages across shows. This screen updates from stored shipment records every 30 seconds; Whatnot sync freshness may vary.</p>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Buyer, tracking or show
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Search shipments" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">Status
                    <select wire:model.live="status" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="all">All statuses</option>
                        @foreach(\App\Filament\Pages\ShipmentOverview::BUCKETS as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-200">History
                    <select wire:model.live="days" class="mt-1 min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                        <option value="7">7 days</option><option value="30">30 days</option><option value="90">90 days</option><option value="365">1 year</option>
                    </select>
                </label>
            </div>
        </header>
        <section class="rounded-2xl border border-violet-100 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900" aria-label="Choose a recent show">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h3 class="font-bold text-gray-950 dark:text-white">Recent shows</h3><p class="text-xs text-gray-500">Choose a show to see its shipments and labels, or view all shipments.</p></div>
                @if($this->showId !== 'all')
                    <div class="flex flex-wrap gap-2"><button type="button" wire:click="refreshSelectedShow" wire:loading.attr="disabled" wire:target="refreshSelectedShow" class="inline-flex min-h-10 items-center rounded-lg border border-violet-300 px-3 text-sm font-bold text-violet-700">Refresh from Whatnot</button><a class="inline-flex min-h-10 items-center rounded-lg bg-violet-700 px-4 text-sm font-bold text-white hover:bg-violet-800" href="{{ \App\Filament\Resources\FulfillmentResource::getUrl('shipping', ['record' => (int) $this->showId]) }}">Open Shipping &amp; Labels</a></div>
                @endif
            </div>
            <div class="mt-3 flex gap-2 overflow-x-auto pb-2" role="group" aria-label="Filter by show">
                <button type="button" wire:click="$set('showId', 'all')" aria-pressed="{{ $this->showId === 'all' ? 'true' : 'false' }}" class="min-w-28 shrink-0 rounded-xl border px-3 py-3 text-left text-sm font-bold {{ $this->showId === 'all' ? 'border-violet-500 bg-violet-50 text-violet-800' : 'border-gray-200 text-gray-700 dark:text-gray-200' }}">All shipments</button>
                @foreach($recentShows as $recent)
                    <button type="button" wire:key="recent-show-{{ $recent->id }}" wire:click="$set('showId', '{{ $recent->id }}')" aria-pressed="{{ $this->showId === (string) $recent->id ? 'true' : 'false' }}" class="w-52 shrink-0 rounded-xl border px-3 py-2 text-left {{ $this->showId === (string) $recent->id ? 'border-violet-500 bg-violet-50' : 'border-gray-200 bg-white dark:bg-gray-900 dark:border-gray-700' }}">
                        <span class="block truncate text-sm font-bold text-gray-950 dark:text-white" title="{{ $recent->title }}">{{ $recent->title }}</span>
                        <span class="mt-1 block text-xs text-gray-500">{{ $recent->show_date?->format('M j') }} · {{ number_format($recent->shipments_count) }} shipments</span>
                    </button>
                @endforeach
            </div>
        </section>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach(array_diff_key(\App\Filament\Pages\ShipmentOverview::BUCKETS, ['cancelled' => true]) as $key => $label)
                <button type="button" wire:click="$set('status', '{{ $this->status === $key ? 'all' : $key }}')" aria-pressed="{{ $this->status === $key ? 'true' : 'false' }}"
                    class="rounded-2xl border bg-white p-4 text-left shadow-sm transition hover:border-violet-400 dark:bg-gray-900 {{ $this->status === $key ? 'border-violet-500 ring-2 ring-violet-200 dark:ring-violet-900' : 'border-gray-200 dark:border-gray-700' }}">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($totals[$key] ?? 0) }}</p>
                </button>
            @endforeach
        </div>
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full table-fixed text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                    <tr><th class="p-3 w-[25%]">Show</th><th class="p-3 w-[15%]">Buyer</th><th class="p-3 w-[23%]">Tracking</th><th class="p-3 w-[10%]">Carrier</th><th class="p-3 w-[15%]">Status</th><th class="p-3 w-[12%]">Updated</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $shipment)
                        <tr wire:key="shipment-{{ $shipment->id }}">
                            <td class="p-3 break-words font-medium text-gray-950 dark:text-white">{{ $shipment->show?->title ?? 'Unknown show' }}<span class="mt-1 block text-xs font-normal text-gray-500">{{ $shipment->show?->show_date?->format('M j, Y') }}</span></td>
                            <td class="p-3">{{ $shipment->buyer_username ?: 'Unknown' }}</td>
                            <td class="p-3 break-all font-mono text-xs">{{ $shipment->tracking_number ?: 'Pending' }}</td>
                            <td class="p-3">{{ $shipment->carrier ?: '—' }}</td>
                            <td class="p-3"><span title="{{ $shipment->status }}" class="rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200">{{ \App\Filament\Pages\ShipmentOverview::BUCKETS[\App\Filament\Pages\ShipmentOverview::bucket($shipment->status)] }}</span></td>
                            <td class="p-3 text-xs text-gray-500">{{ $shipment->updated_at?->diffForHumans() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="p-8 text-center text-gray-500">No shipments match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if($rows->count() === 150)<p class="p-3 text-xs text-gray-500">Showing the 150 most recently updated matching shipments. Narrow the filters for older records.</p>@endif
        </div>
    </div>
</x-filament-panels::page>
