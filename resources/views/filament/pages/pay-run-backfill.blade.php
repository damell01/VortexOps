<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end">
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">From</label>
                    <input type="date" wire:model="from_date" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                    @error('from_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">To</label>
                    <input type="date" wire:model="to_date" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                    @error('to_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                </div>
                <div class="flex-1">
                    <label class="mb-1.5 block text-sm font-medium text-gray-900 dark:text-gray-100">Team Type</label>
                    <select wire:model="member_type" class="w-full rounded-lg border border-gray-300 bg-gray-50 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                        <option value="">All</option>
                        <option value="streamer">Streamer</option>
                        <option value="fulfillment">Fulfillment</option>
                    </select>
                </div>
                <button type="button" wire:click="preview" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-500">
                    Preview / Dry Run
                </button>
            </div>

            <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800 dark:border-blue-900 dark:bg-blue-950/30 dark:text-blue-300">
                <strong>Preview is read-only.</strong> It compares historical weekly Pay Runs without changing payroll. Safe Backfill below only creates or recalculates missing/Draft weeks; Finalized, Submitted to ADP, and Paid weeks remain untouched.
            </div>
        </div>

        @if($results !== [])
            @php
                $matches = collect($results)->where('result', 'MATCH')->count();
                $differences = collect($results)->where('result', 'DIFFERENCE')->count();
                $missing = collect($results)->where('result', 'MISSING PAY RUN')->count();
            @endphp

            <div class="grid gap-4 md:grid-cols-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Periods Tested</p>
                    <p class="mt-1 text-2xl font-bold text-gray-900 dark:text-white">{{ count($results) }}</p>
                </div>
                <div class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/30">
                    <p class="text-xs font-medium uppercase tracking-wide text-green-700 dark:text-green-400">Matches</p>
                    <p class="mt-1 text-2xl font-bold text-green-800 dark:text-green-300">{{ $matches }}</p>
                </div>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/30">
                    <p class="text-xs font-medium uppercase tracking-wide text-amber-700 dark:text-amber-400">Differences</p>
                    <p class="mt-1 text-2xl font-bold text-amber-800 dark:text-amber-300">{{ $differences }}</p>
                </div>
                <div class="rounded-xl border border-red-200 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/30">
                    <p class="text-xs font-medium uppercase tracking-wide text-red-700 dark:text-red-400">Missing Pay Runs</p>
                    <p class="mt-1 text-2xl font-bold text-red-800 dark:text-red-300">{{ $missing }}</p>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3">Pay Period</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Shows</th>
                                <th class="px-4 py-3 text-right">Existing</th>
                                <th class="px-4 py-3 text-right">Calculated</th>
                                <th class="px-4 py-3 text-right">Difference</th>
                                <th class="px-4 py-3">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach($results as $row)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['week_start'] }} – {{ $row['week_end'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['batch_status'] ?? 'Missing' }} @if($row['read_only']) <span class="ml-1 text-xs text-gray-400">(read-only)</span> @endif</td>
                                    <td class="px-4 py-3 text-right">{{ $row['shows_found'] }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format($row['existing_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">${{ number_format($row['calculated_amount'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ abs($row['difference']) < 0.005 ? 'text-green-600' : 'text-amber-600' }}">
                                        {{ $row['difference'] >= 0 ? '+' : '-' }}${{ number_format(abs($row['difference']), 2) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        @php
                                            $tone = match($row['result']) {
                                                'MATCH' => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
                                                'DIFFERENCE' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                                default => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
                                            };
                                        @endphp
                                        <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold {{ $tone }}">{{ $row['result'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end">
                <button type="button" wire:click="applySafe" wire:confirm="Create/recalculate only missing or Draft Pay Runs in this range? Finalized, submitted, and paid payroll will not be changed." class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-500">
                    Safe Backfill Missing / Draft
                </button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
