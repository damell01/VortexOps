@php
    $record = $getRecord();
    $stocks = $record->stock()->with('location')->orderByDesc('quantity')->get();
    $total  = $stocks->sum('quantity');
@endphp

@if ($stocks->isEmpty())
    <p class="text-sm text-gray-400 py-2">No stock recorded across any location.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[360px]">
            <thead>
                <tr class="border-b border-gray-100 dark:border-gray-700 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="py-2 pr-4">Location</th>
                    <th class="py-2 pr-4">Type</th>
                    <th class="py-2 text-right">Qty</th>
                    <th class="py-2 pl-4">Share</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
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
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="py-2 pr-4 font-medium text-gray-900 dark:text-white">
                            {{ $stock->location?->name ?? 'Unknown' }}
                        </td>
                        <td class="py-2 pr-4 text-gray-500 dark:text-gray-400 text-xs">
                            {{ ucwords(str_replace('_', ' ', $stock->location?->type ?? '')) }}
                        </td>
                        <td class="py-2 text-right font-semibold text-gray-900 dark:text-white">
                            {{ number_format($stock->quantity, 0) }}
                        </td>
                        <td class="py-2 pl-4 w-32">
                            <div class="flex items-center gap-2">
                                <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                    <div class="h-1.5 rounded-full {{ $typeColor }}" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="text-xs text-gray-400 w-8 text-right">{{ $pct }}%</span>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="border-t border-gray-200 dark:border-gray-700">
                    <td colspan="2" class="py-2 text-xs font-medium text-gray-500 dark:text-gray-400">Total</td>
                    <td class="py-2 text-right font-bold text-gray-900 dark:text-white">{{ number_format($total, 0) }}</td>
                    <td class="py-2 pl-4">
                        <span class="text-xs text-gray-400">Est. value: ${{ number_format($total * $record->effectiveCost(), 2) }}</span>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
@endif
