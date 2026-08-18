@php
    $totals = $review['totals'];
    // Staging is the same reconciliation read forward rather than back:
    // nothing has arrived, so "short" is simply everything, and saying so
    // would be noise rather than a warning.
    $staging = $staging ?? false;
    $short   = ! $staging && $totals['short_units'] > 0;
@endphp

<div class="vx-review-pallet space-y-4">

    {{-- Anything that would stop this being received, said up front rather
         than as an error after pressing the button. --}}
    @if ($review['blockers'])
        <div class="rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 px-4 py-3">
            <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                {{ $staging ? 'Not ready yet' : 'Not ready to receive' }}
            </p>
            <ul class="mt-1.5 space-y-1 text-xs text-amber-800 dark:text-amber-200 list-disc list-inside">
                @foreach ($review['blockers'] as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- The headline question: does what turned up match what was expected. --}}
    <div class="grid grid-cols-3 gap-3">
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide">Expected</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ number_format($totals['expected_units']) }}
            </p>
            <p class="text-xs text-gray-400">units</p>
        </div>
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
            <p class="text-xs text-gray-400 uppercase tracking-wide">{{ $staging ? 'Lines' : 'Confirmed' }}</p>
            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {{ $staging ? count($review['lines']) : number_format($totals['confirmed_units']) }}
            </p>
            <p class="text-xs text-gray-400">{{ $staging ? 'items staged' : 'units scanned in' }}</p>
        </div>
        @if ($staging)
            <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-3 py-2">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Landed cost</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    ${{ number_format($totals['landed'], 2) }}
                </p>
                <p class="text-xs text-gray-400">goods, shipping and fees</p>
            </div>
        @else
            <div class="rounded-lg border {{ $short ? 'border-amber-300 dark:border-amber-700' : 'border-gray-200 dark:border-gray-700' }} px-3 py-2">
                <p class="text-xs text-gray-400 uppercase tracking-wide">Short</p>
                <p class="text-lg font-semibold {{ $short ? 'text-amber-600 dark:text-amber-400' : 'text-gray-900 dark:text-gray-100' }}">
                    {{ number_format($totals['short_units']) }}
                </p>
                <p class="text-xs text-gray-400">not accounted for</p>
            </div>
        @endif
    </div>

    {{-- Per line, and what each item ends up valued at. The projected average
         is the part worth reading: receiving rewrites it permanently, and this
         is the only chance to see the number beforehand. --}}
    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-800">
                    <th class="px-3 py-2 font-medium">Item</th>
                    <th class="px-3 py-2 font-medium text-right">{{ $staging ? 'Expected' : 'Cases' }}</th>
                    <th class="px-3 py-2 font-medium text-right">Landed / unit</th>
                    @unless ($staging)
                        <th class="px-3 py-2 font-medium text-right">New avg cost</th>
                    @endunless
                </tr>
            </thead>
            <tbody>
                @foreach ($review['lines'] as $line)
                    <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0">
                        <td class="px-3 py-2">
                            <p class="font-medium text-gray-900 dark:text-gray-100">{{ $line['name'] }}</p>
                            <p class="text-xs text-gray-400">
                                {{ $line['sku'] ?: 'No SKU' }} · {{ $line['location'] ?? 'No location' }}
                            </p>
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($staging)
                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $line['expected_cases'] }}</span>
                                <span class="text-gray-400">cases</span>
                            @else
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $line['confirmed_cases'] }}</span>
                            <span class="text-gray-400">/ {{ $line['expected_cases'] }}</span>
                            @if ($line['variance_cases'] < 0)
                                <span class="block text-xs text-amber-600 dark:text-amber-400">
                                    {{ abs($line['variance_cases']) }} short
                                </span>
                            @elseif ($line['variance_cases'] > 0)
                                <span class="block text-xs text-blue-600 dark:text-blue-400">
                                    {{ $line['variance_cases'] }} over
                                </span>
                            @endif
                            @endif
                        </td>
                        <td class="px-3 py-2 text-right whitespace-nowrap text-gray-900 dark:text-gray-100">
                            ${{ number_format($line['landed_unit_cost'], 2) }}
                            @if ($line['landed_unit_cost'] > $line['unit_cost'])
                                <span class="block text-xs text-gray-400">
                                    was ${{ number_format($line['unit_cost'], 2) }}
                                </span>
                            @endif
                        </td>
                        @unless ($staging)
                        <td class="px-3 py-2 text-right whitespace-nowrap">
                            @if ($line['projected_average_cost'] !== null)
                                <span class="font-medium text-gray-900 dark:text-gray-100">
                                    ${{ number_format($line['projected_average_cost'], 2) }}
                                </span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        @endunless
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <dl class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 space-y-1.5 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Goods</dt>
            <dd class="text-gray-900 dark:text-gray-100">${{ number_format($totals['goods'], 2) }}</dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-500 dark:text-gray-400">Shipping &amp; fees</dt>
            <dd class="text-gray-900 dark:text-gray-100">${{ number_format($totals['extras'], 2) }}</dd>
        </div>
        <div class="flex justify-between pt-1.5 border-t border-gray-100 dark:border-gray-800 font-semibold">
            <dt class="text-gray-900 dark:text-gray-100">Landed total</dt>
            <dd class="text-gray-900 dark:text-gray-100">${{ number_format($totals['landed'], 2) }}</dd>
        </div>
    </dl>

    @if ($short)
        {{-- Two different outcomes, and the difference is real stock, so it is
             spelled out rather than left to the button labels. --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-3 text-xs space-y-2">
            <p class="font-semibold text-gray-900 dark:text-gray-100">
                {{ number_format($totals['short_units']) }} units are unaccounted for
            </p>
            <p class="text-gray-600 dark:text-gray-400">
                <span class="font-medium">Receive all</span> takes in everything expected,
                including what was never scanned. Use it when the delivery is complete and
                scanning was just a spot check.
            </p>
            <p class="text-gray-600 dark:text-gray-400">
                <span class="font-medium">Close short</span> keeps only what was scanned and
                leaves the rest outstanding, so nothing is credited that did not arrive.
            </p>
        </div>
    @endif
</div>
