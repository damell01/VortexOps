<x-filament-panels::page>
    @php($data = $this->reportData())
    <div class="space-y-5">
        <section class="rounded-2xl border border-violet-200 bg-gradient-to-r from-violet-50 to-white p-5 shadow-sm dark:border-violet-900 dark:from-violet-950/40 dark:to-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-widest text-violet-700 dark:text-violet-300">Fulfillment Center / Reports</p>
                    <h1 class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ $data['manager'] ? 'Team performance' : 'My fulfillment performance' }}</h1>
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">Actual packing and scan activity, not approved payroll hours or earnings.</p>
                </div>
                <label class="text-sm font-semibold">Reporting period
                    <select wire:model.live="days" class="mt-1 block min-h-11 rounded-xl border-gray-300 bg-white text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="7">Last 7 days</option><option value="30">Last 30 days</option><option value="90">Last 90 days</option>
                    </select>
                </label>
            </div>
        </section>
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
            @foreach([
                ['Buyers packed', $data['totals']->buyers_packed ?? 0],
                ['Items packed', $data['totals']->items_packed ?? 0],
                ['Packing runs', $data['totals']->runs_worked ?? 0],
                ['Verified scan units', $data['scans']->matched_units ?? 0],
                ['Rejected scans', $data['scans']->rejected_scans ?? 0],
                ['Issues reported', $data['issues']],
            ] as [$label, $value])
                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                    <p class="mt-2 text-2xl font-bold tabular-nums text-violet-700 dark:text-violet-300">{{ number_format((int) $value) }}</p>
                </div>
            @endforeach
        </div>
        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-lg font-bold">Daily packing activity</h2>
                <div class="mt-3 max-h-80 overflow-auto">
                    <table class="w-full text-left text-sm"><thead><tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700"><th class="py-2">Date</th><th class="py-2 text-right">Buyers</th><th class="py-2 text-right">Items</th></tr></thead>
                    <tbody>@forelse($data['daily'] as $day)<tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2">{{ $day->day }}</td><td class="py-2 text-right tabular-nums">{{ $day->buyers_packed }}</td><td class="py-2 text-right tabular-nums">{{ $day->items_packed }}</td></tr>@empty<tr><td colspan="3" class="py-6 text-center text-gray-500">No packing activity for this period.</td></tr>@endforelse</tbody></table>
                </div>
            </section>
            @if($data['manager'])
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold">Team packing totals</h2>
                    <p class="mt-1 text-xs text-gray-500">Counts are attributed to the employee who confirmed each buyer packed.</p>
                    <div class="mt-3 max-h-80 overflow-auto"><table class="w-full text-left text-sm"><thead><tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700"><th class="py-2">Employee</th><th class="py-2 text-right">Buyers</th><th class="py-2 text-right">Items</th></tr></thead><tbody>
                        @forelse($data['leaderboard'] as $person)<tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-2 font-medium">{{ $person->name }}</td><td class="py-2 text-right">{{ $person->buyers_packed }}</td><td class="py-2 text-right">{{ $person->items_packed }}</td></tr>@empty<tr><td colspan="3" class="py-6 text-center text-gray-500">No packing records yet.</td></tr>@endforelse
                    </tbody></table></div>
                </section>
            @else
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <h2 class="text-lg font-bold">How your activity is measured</h2>
                    <p class="mt-3 text-sm leading-6 text-gray-600 dark:text-gray-300">Buyer and item totals are counted when you confirm a buyer packed. Verified scans are counted separately when you scan an item. Reported issues count the flags you submitted. These records are operational metrics and do not change your pay.</p>
                </section>
            @endif
        </div>
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-lg font-bold">Recent completed packing</h2>
            <div class="mt-3 overflow-x-auto"><table class="w-full min-w-[560px] text-left text-sm"><thead><tr class="border-b border-gray-200 text-xs text-gray-500 dark:border-gray-700"><th class="py-2">Completed</th><th class="py-2">Show</th><th class="py-2">Buyer</th><th class="py-2 text-right">Items</th></tr></thead><tbody>
                @forelse($data['recent'] as $row)<tr class="border-b border-gray-100 dark:border-gray-800"><td class="py-3">{{ $row->packed_at }}</td><td class="py-3">{{ $row->show_title }}</td><td class="py-3">{{ $row->letter }} · {{ $row->buyer_username }}</td><td class="py-3 text-right tabular-nums">{{ $row->item_count }}</td></tr>@empty<tr><td colspan="4" class="py-6 text-center text-gray-500">No completed packing records.</td></tr>@endforelse
            </tbody></table></div>
        </section>
    </div>
</x-filament-panels::page>
