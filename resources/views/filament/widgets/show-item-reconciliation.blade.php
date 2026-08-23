@php
    $rows    = $this->rows;
    $verdict = $this->verdict;
    $filed   = $this->reportFiled;
@endphp

<x-filament-widgets::widget>
    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot vs logged</h2>
                <p class="mt-1 max-w-2xl text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">
                    What Whatnot recorded for this show, against what the streamer logged at End of Stream.
                </p>
            </div>

            <span @class([
                'shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold sm:text-xs',
                'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200'   => $verdict['tone'] === 'match',
                'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200'   => $verdict['tone'] === 'differs',
                'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'       => $verdict['tone'] === 'idle',
            ])>
                {{ match ($verdict['tone']) {
                    'match'  => 'Reconciled',
                    'differs'=> 'Needs a look',
                    default  => 'Not reported',
                } }}
            </span>
        </div>

        <p @class([
            'mt-3 text-xs leading-5 sm:text-sm',
            'text-green-700 dark:text-green-300' => $verdict['tone'] === 'match',
            'text-amber-700 dark:text-amber-300' => $verdict['tone'] === 'differs',
            'text-gray-500 dark:text-gray-400'   => $verdict['tone'] === 'idle',
        ])>
            {{ $verdict['text'] }}
        </p>

        {{-- Scrolls inside itself rather than pushing the page sideways on a
             phone, which is where this is most often read. --}}
        <div class="mt-4 overflow-x-auto">
            <table class="w-full min-w-[22rem] text-left text-xs sm:text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-[10px] uppercase tracking-wide text-gray-400 dark:border-gray-700 sm:text-xs">
                        <th class="pb-2 pr-3 font-medium">Disposition</th>
                        <th class="pb-2 px-3 text-right font-medium">Whatnot</th>
                        <th class="pb-2 px-3 text-right font-medium">Logged</th>
                        <th class="pb-2 pl-3 text-right font-medium">Difference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($rows as $row)
                        <tr>
                            <td class="py-2.5 pr-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['label'] }}</td>

                            <td class="py-2.5 px-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{-- An em dash, not a zero. Whatnot reports no
                                     figure for promo or bonus stock, and a 0
                                     would read as "Whatnot says none", which is
                                     a claim it never made. --}}
                                {{ $row['whatnot'] === null ? '—' : number_format($row['whatnot']) }}
                            </td>

                            <td class="py-2.5 px-3 text-right tabular-nums text-gray-700 dark:text-gray-300">
                                {{ number_format($row['logged']) }}
                            </td>

                            <td @class([
                                'py-2.5 pl-3 text-right tabular-nums font-semibold',
                                'text-gray-300 dark:text-gray-600'   => $row['difference'] === null,
                                'text-green-600 dark:text-green-400' => $row['difference'] === 0,
                                'text-amber-600 dark:text-amber-400' => is_int($row['difference']) && $row['difference'] !== 0,
                            ])>
                                @if ($row['difference'] === null)
                                    —
                                @elseif ($row['difference'] === 0)
                                    0
                                @else
                                    {{ $row['difference'] > 0 ? '+' : '' }}{{ number_format($row['difference']) }}
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @unless ($filed)
            <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-[11px] leading-5 text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                The logged column fills in when the streamer submits their End of Stream report.
                Until then the Whatnot side is all there is, and a difference here means nothing.
            </p>
        @endunless
    </section>
</x-filament-widgets::widget>
