<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">Show Flow</x-slot>
        <x-slot name="description">One view of what is waiting on the streamer, admin, fulfillment, or payroll.</x-slot>

        <div class="space-y-4">
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ([
                    ['Streamer', $counts['streamer'] ?? 0, 'amber'],
                    ['Admin Review', $counts['admin'] ?? 0, 'orange'],
                    ['Fulfillment', $counts['fulfillment'] ?? 0, 'sky'],
                    ['Payroll Ready', $counts['payroll'] ?? 0, 'emerald'],
                ] as [$label, $value, $color])
                    <div class="rounded-xl border border-gray-200 bg-white px-3 py-3 dark:border-gray-700 dark:bg-gray-900">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $label }}</div>
                        <div class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                    </div>
                @endforeach
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
                @forelse($rows as $row)
                    @php
                        $badge = match($row['tone']) {
                            'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
                            'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-200',
                            'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-200',
                            'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
                            'danger' => 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200',
                            default => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300',
                        };
                    @endphp
                    <a href="{{ $row['url'] }}" class="block border-b border-gray-100 bg-white px-3.5 py-3 last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-900 dark:hover:bg-gray-800/60 sm:px-4">
                        <div class="flex items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['title'] }}</div>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badge }}">{{ $row['stage'] }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                                    <span>{{ $row['date']?->format('M j, Y') ?? 'No date' }}</span>
                                    @if($row['streamers'])<span>{{ $row['streamers'] }}</span>@endif
                                    @if($row['gross'] > 0)<span>${{ number_format($row['gross'], 2) }} sales</span>@endif
                                    @if($row['open_shipments'] > 0)<span>{{ number_format($row['open_shipments']) }} open shipment(s)</span>@endif
                                    @if($row['payout_total'] > 0)<span>${{ number_format($row['payout_total'], 2) }} payroll</span>@endif
                                </div>
                                <div class="mt-1 text-[11px] font-medium text-gray-600 dark:text-gray-300">{{ $row['hint'] }}</div>
                            </div>
                            <x-heroicon-m-chevron-right class="mt-1 h-4 w-4 shrink-0 text-gray-300" />
                        </div>
                    </a>
                @empty
                    <div class="bg-white px-4 py-8 text-center text-sm text-gray-500 dark:bg-gray-900 dark:text-gray-400">No active shows in this view.</div>
                @endforelse
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
