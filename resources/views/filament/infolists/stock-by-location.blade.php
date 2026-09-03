@php
    $record = $getRecord();
    $stocks = $record->stock()->with('location')->orderByDesc('quantity')->get();
    $total  = $stocks->sum('quantity');
@endphp

@if ($stocks->isEmpty())
    <p class="text-sm text-gray-400 py-2">No stock recorded across any location.</p>
@else
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach ($stocks as $stock)
            @php
                $pct = $total > 0 ? round(($stock->quantity / $total) * 100) : 0;
                $typeColor = match ($stock->location?->type) {
                    'main_storage'       => 'bg-blue-500',
                    'streamer_inventory' => 'bg-emerald-500',
                    'returned'           => 'bg-amber-500',
                    'damaged'            => 'bg-red-500',
                    'fulfillment'        => 'bg-violet-500',
                    default              => 'bg-gray-400',
                };
            @endphp
            <div class="py-2">
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                    <span class="font-medium text-gray-900 dark:text-white">{{ $stock->location?->name ?? 'Unknown' }}</span>
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ ucwords(str_replace('_', ' ', $stock->location?->type ?? '')) }}</span>
                    <span class="font-semibold text-gray-900 dark:text-white tabular-nums">{{ number_format($stock->quantity, 0) }}</span>
                </div>
                <div class="mt-1 flex items-center gap-2">
                    <div class="h-1.5 flex-1 rounded-full bg-gray-100 dark:bg-gray-700">
                        <div class="h-1.5 rounded-full {{ $typeColor }}" style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="w-8 flex-shrink-0 text-right text-xs text-gray-400">{{ $pct }}%</span>
                </div>
            </div>
        @endforeach

        <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5 pt-2">
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Total</span>
            <span class="font-bold text-gray-900 dark:text-white tabular-nums">{{ number_format($total, 0) }}</span>
            <span class="text-xs text-gray-400">Est. value: ${{ number_format($total * $record->effectiveCost(), 2) }}</span>
        </div>
    </div>
@endif
