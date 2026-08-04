<div class="space-y-4">
    <div class="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <p class="text-blue-600 dark:text-blue-400 font-medium">Session ID</p>
                <p class="text-gray-900 dark:text-white font-bold">{{ $session->id }}</p>
            </div>
            <div>
                <p class="text-blue-600 dark:text-blue-400 font-medium">Operator</p>
                <p class="text-gray-900 dark:text-white">{{ $session->user?->name ?? '—' }}</p>
            </div>
            <div>
                <p class="text-blue-600 dark:text-blue-400 font-medium">Mode</p>
                <p class="text-gray-900 dark:text-white">{{ ucfirst($session->mode) }}</p>
            </div>
            <div>
                <p class="text-blue-600 dark:text-blue-400 font-medium">Items Scanned</p>
                <p class="text-gray-900 dark:text-white font-bold">{{ $session->items_scanned ?? 0 }}</p>
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800">
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Item</th>
                    <th class="text-left py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">SKU</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Cases</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Qty</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Unit Cost</th>
                    <th class="text-right py-3 px-4 font-semibold text-gray-700 dark:text-gray-300">Total</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $totalQty = 0;
                    $totalCost = 0;
                @endphp
                @forelse($session->pallet->lines as $line)
                <tr class="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800">
                    <td class="py-3 px-4 text-gray-900 dark:text-white font-medium">{{ $line->inventoryItem?->name ?? 'Unknown' }}</td>
                    <td class="py-3 px-4 text-gray-600 dark:text-gray-400">{{ $line->inventoryItem?->sku ?? '—' }}</td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ $line->case_count }}</td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ number_format($line->quantity, 2) }}</td>
                    <td class="py-3 px-4 text-right text-gray-600 dark:text-gray-400">${{ number_format($line->unit_cost, 2) }}</td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white font-semibold">${{ number_format($line->quantity * $line->unit_cost, 2) }}</td>
                    @php
                        $totalQty += $line->quantity;
                        $totalCost += $line->quantity * $line->unit_cost;
                    @endphp
                </tr>
                @empty
                <tr>
                    <td class="py-6 px-4 text-center text-gray-500 dark:text-gray-400 col-span-6">No items in this session</td>
                </tr>
                @endforelse
                @if(!$session->pallet->lines->isEmpty())
                <tr class="bg-gray-100 dark:bg-gray-800 font-bold">
                    <td class="py-3 px-4 text-gray-900 dark:text-white">TOTALS</td>
                    <td class="py-3 px-4"></td>
                    <td class="py-3 px-4"></td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">{{ number_format($totalQty, 2) }}</td>
                    <td class="py-3 px-4"></td>
                    <td class="py-3 px-4 text-right text-gray-900 dark:text-white">${{ number_format($totalCost, 2) }}</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
