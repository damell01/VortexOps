@php
    $rows    = $this->rows;
    $overall = $this->overall;
@endphp

<x-filament-panels::page>
    <div class="space-y-3 sm:space-y-5" data-vx-page="show-formats">

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="flex flex-wrap items-end gap-3 p-4 sm:p-5">
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Period</span>
                    <select wire:model.live="range" class="min-h-11 w-full rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @foreach ($this->rangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="min-w-0 flex-1">
                    <span class="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Channel</span>
                    <select wire:model.live="channelId" class="min-h-11 w-full rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                        @foreach ($this->channelOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($overall['shows'] > 0)
                <div class="grid gap-px border-t border-gray-100 bg-gray-100 dark:border-gray-800 dark:bg-gray-800 sm:grid-cols-3">
                    <div class="bg-white p-4 dark:bg-gray-900">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Shows in range</div>
                        <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($overall['shows']) }}</div>
                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">{{ number_format($overall['classified']) }} classified</div>
                    </div>
                    <div class="bg-white p-4 dark:bg-gray-900">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Average per show</div>
                        <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">${{ number_format($overall['avg_net'], 2) }}</div>
                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">The line each format below is measured against</div>
                    </div>
                    <div class="bg-white p-4 dark:bg-gray-900">
                        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total in range</div>
                        <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">${{ number_format($overall['total_net'], 2) }}</div>
                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Net where synced, gross where not</div>
                    </div>
                </div>
            @endif
        </section>

        @if (empty($rows))
            <section class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <x-heroicon-o-scale class="mx-auto h-10 w-10 text-gray-300 dark:text-gray-600" />
                <h2 class="mt-3 text-base font-semibold text-gray-950 dark:text-white">No shows in this period</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Widen the period, or pick a different channel.</p>
            </section>
        @else
            @if ($this->unclassifiedCount > 0)
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-200 sm:text-sm">
                    {{ number_format($this->unclassifiedCount) }} {{ Str::plural('show', $this->unclassifiedCount) }}
                    in this period {{ $this->unclassifiedCount === 1 ? 'has' : 'have' }} no format set. They are listed
                    below as their own group rather than folded into a default — an unclassified show is not a standard one.
                    Set a format from the Notes section of a show.
                </div>
            @endif

            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">By format</h2>
                    <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Best average first. The percentage compares each format's average show against the average of every show in range.</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full min-w-[44rem] text-sm">
                        <thead class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2.5 font-semibold">Format</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Shows</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Avg / show</th>
                                <th class="px-4 py-2.5 text-right font-semibold">vs average</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Avg units</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Avg giveaways</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Avg buyers</th>
                                <th class="px-4 py-2.5 text-right font-semibold">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr class="{{ $row['format'] === null ? 'bg-gray-50/60 dark:bg-gray-800/30' : '' }}">
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">
                                        {{ $row['label'] }}
                                        {{-- A single show is not a trend, and a table of averages
                                             invites reading one as though it were. --}}
                                        @if ($row['shows'] < 3)
                                            <span class="ml-1 rounded-full bg-gray-100 px-1.5 py-0.5 text-[10px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">too few to trust</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['shows']) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold tabular-nums text-gray-950 dark:text-white">${{ number_format($row['avg_net'], 2) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums">
                                        @if ($row['net_vs_overall_pct'] === null)
                                            <span class="text-gray-400">—</span>
                                        @else
                                            <span class="font-semibold {{ $row['net_vs_overall_pct'] >= 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ $row['net_vs_overall_pct'] >= 0 ? '+' : '' }}{{ number_format($row['net_vs_overall_pct'], 1) }}%
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['avg_units'], 1) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['avg_giveaways'], 1) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['avg_buyers'], 1) }}</td>
                                    <td class="px-4 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">${{ number_format($row['total_net'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
