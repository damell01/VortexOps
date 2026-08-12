<div class="space-y-4">
    <div class="bg-amber-50 dark:bg-amber-950 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-amber-600 dark:text-amber-400 font-medium">Pallet ID</p>
                <p class="text-gray-900 dark:text-white font-bold">{{ $pallet->id }}</p>
            </div>
            <div>
                <p class="text-amber-600 dark:text-amber-400 font-medium">Reference</p>
                <p class="text-gray-900 dark:text-white">{{ $pallet->reference }}</p>
            </div>
            <div>
                <p class="text-amber-600 dark:text-amber-400 font-medium">Vendor</p>
                <p class="text-gray-900 dark:text-white">{{ $pallet->vendor?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-amber-600 dark:text-amber-400 font-medium">Status</p>
                <p class="text-gray-900 dark:text-white font-bold">{{ ucfirst($pallet->status) }}</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Item Name</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">SKU</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Cases</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Quantity</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Unit Cost</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Total Cost</th>
                    <th class="text-center py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Confidence</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalQty = 0;
                    $totalCost = 0;
                @endphp
                @forelse($pallet->lines as $line)
                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="py-3 px-4 text-gray-900 dark:text-white font-medium">{{ $line->inventoryItem?->name ?? 'Unknown' }}</td>
                    <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $line->inventoryItem?->sku ?? '—' }}</td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ $line->case_count }}</td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ number_format($line->quantity, 2) }}</td>
                    <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">${{ number_format($line->unit_cost, 2) }}</td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white font-semibold">${{ number_format($line->quantity * $line->unit_cost, 2) }}</td>
                    <td class="py-3 px-4 text-center">
                        <span class="inline-block bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-100 text-xs font-semibold px-2 py-1 rounded">
                            {{ round((float) $line->match_confidence * 100) }}%
                        </span>
                    </td>
                    @php
                        $totalQty += $line->quantity;
                        $totalCost += $line->quantity * $line->unit_cost;
                    @endphp
                </tr>
                @empty
                <tr>
                    <td class="py-6 px-4 text-center text-gray-500 dark:text-gray-400 col-span-7">No items received</td>
                </tr>
                @endforelse
                @if(!$pallet->lines->isEmpty())
                <tr class="bg-gray-100 dark:bg-gray-800 font-bold">
                    <td class="py-3 px-4 text-gray-900 dark:text-white">TOTALS</td>
                    <td class="py-3 px-4"></td>
                    <td class="py-3 px-4"></td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ number_format($totalQty, 2) }}</td>
                    <td class="py-3 px-4"></td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">${{ number_format($totalCost, 2) }}</td>
                    <td class="py-3 px-4"></td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
