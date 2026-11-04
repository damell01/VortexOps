<x-filament-panels::page>
    @php ['rows' => $rows, 'totals' => $totals] = $this->dashboardData(); @endphp
    <div class="space-y-5" wire:poll.30s>
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
                        @foreach(['created','pending','label_generated','label_created','dropped_off','in_transit','shipped','delivered','failed','returned','cancelled'] as $value)
                            <option value="{{ $value }}">{{ ucwords(str_replace('_', ' ', $value)) }}</option>
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
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach(['created' => 'Created', 'label_generated' => 'Label ready', 'in_transit' => 'In transit', 'shipped' => 'Shipped', 'delivered' => 'Delivered', 'failed' => 'Failed'] as $key => $label)
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $label }}</p>
                    <p class="mt-2 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $totals[$key] ?? 0 }}</p>
                </div>
            @endforeach
        </div>
        <div class="overflow-x-auto rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full min-w-[760px] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800">
                    <tr><th class="p-3">Show</th><th class="p-3">Buyer</th><th class="p-3">Tracking</th><th class="p-3">Carrier</th><th class="p-3">Status</th><th class="p-3">Updated</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($rows as $shipment)
                        <tr wire:key="shipment-{{ $shipment->id }}">
                            <td class="p-3 font-medium text-gray-950 dark:text-white">{{ $shipment->show?->title ?? 'Unknown show' }}<span class="mt-1 block text-xs font-normal text-gray-500">{{ $shipment->show?->show_date?->format('M j, Y') }}</span></td>
                            <td class="p-3">{{ $shipment->buyer_username ?: 'Unknown' }}</td>
                            <td class="p-3 font-mono text-xs">{{ $shipment->tracking_number ?: 'Pending' }}</td>
                            <td class="p-3">{{ $shipment->carrier ?: '—' }}</td>
                            <td class="p-3"><span class="rounded-full bg-violet-100 px-2 py-1 text-xs font-semibold text-violet-800 dark:bg-violet-950 dark:text-violet-200">{{ ucwords(str_replace('_', ' ', $shipment->status ?: 'pending')) }}</span></td>
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
