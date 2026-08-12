@php
    $p = $preview;
@endphp
<div class="space-y-4">
    <p class="text-sm text-gray-500 dark:text-gray-400">
        What each streamer will receive if you finalize this pay run now, after loan
        repayments. Nothing is saved — this is a preview.
    </p>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-white/5 text-xs uppercase text-gray-500 dark:text-gray-400">
                <tr>
                    <th class="px-3 py-2 text-left font-medium">Streamer</th>
                    <th class="px-3 py-2 text-right font-medium">Gross</th>
                    <th class="px-3 py-2 text-right font-medium">Loan</th>
                    <th class="px-3 py-2 text-right font-medium">Net Pay</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @forelse ($p['rows'] as $row)
                    <tr>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $row['streamer'] }}</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($row['gross'], 2) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums {{ $row['loan'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                            {{ $row['loan'] > 0 ? '−$' . number_format($row['loan'], 2) : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums font-semibold text-gray-900 dark:text-gray-100">${{ number_format($row['net'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-3 py-6 text-center text-gray-400">No payouts in this batch yet.</td>
                    </tr>
                @endforelse
            </tbody>
            @if (! empty($p['rows']))
                <tfoot class="bg-gray-50 dark:bg-white/5 font-semibold">
                    <tr>
                        <td class="px-3 py-2 text-gray-900 dark:text-gray-100">Total ({{ count($p['rows']) }})</td>
                        <td class="px-3 py-2 text-right tabular-nums text-gray-600 dark:text-gray-300">${{ number_format($p['gross'], 2) }}</td>
                        <td class="px-3 py-2 text-right tabular-nums {{ $p['loan'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400' }}">
                            {{ $p['loan'] > 0 ? '−$' . number_format($p['loan'], 2) : '—' }}
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums text-primary-600 dark:text-primary-400">${{ number_format($p['net'], 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
</div>
