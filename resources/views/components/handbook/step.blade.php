@props(['step', 'number' => null, 'imageBase'])

{{--
    One step of the handbook, on screen.

    Deliberately the same four parts as the printed page — instruction, field
    reference, warning, picture — and in the same order, so someone who has
    read the PDF is not learning a second document.
--}}
<article class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600">
    <div class="flex items-start gap-3 p-4 sm:p-5">
        @if ($number)
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-xs font-bold text-white shadow-sm">
                {{ $number }}
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</h3>

            @if (! empty($step['where']))
                <div class="mt-1.5 inline-flex max-w-full items-center gap-1 rounded-md bg-primary-50 px-2 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                    <x-heroicon-m-map-pin class="h-3 w-3 shrink-0" />
                    <span class="truncate">{{ $step['where'] }}</span>
                </div>
            @endif

            <div class="mt-2.5 space-y-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                @foreach ($step['body'] as $paragraph)
                    <p>{!! $paragraph !!}</p>
                @endforeach
            </div>

            @if (! empty($step['fields']))
                {{-- Open by default. A field reference that has to be found
                     before it can be read is a field reference nobody reads. --}}
                <details open class="mt-3.5 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <summary class="cursor-pointer select-none bg-gray-50 px-3 py-2 text-[11px] font-bold uppercase tracking-[.1em] text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        Every field on this screen ({{ count($step['fields']) }})
                    </summary>
                    <div class="divide-y divide-gray-100 border-t border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                        @foreach ($step['fields'] as [$field, $meaning])
                            <div class="grid gap-0.5 px-3 py-2.5 sm:grid-cols-[12rem_1fr] sm:gap-4">
                                <div class="text-xs font-semibold text-primary-700 dark:text-primary-300">{{ $field }}</div>
                                <div class="text-xs leading-5 text-gray-600 dark:text-gray-300">{!! $meaning !!}</div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            @if (! empty($step['note']))
                <div class="mt-3.5 flex gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 text-xs leading-5 text-amber-900 dark:border-amber-500/20 dark:bg-amber-950/40 dark:text-amber-200">
                    <x-heroicon-m-exclamation-triangle class="mt-px h-4 w-4 shrink-0 text-amber-500" />
                    <span>{!! $step['note'] !!}</span>
                </div>
            @endif
        </div>
    </div>

    @php
        // A long form is captured in parts, so a step can carry more than one
        // picture — shown top to bottom, the way you meet the form.
        $shots = array_values(array_filter(array_merge([$step['shot'] ?? null], $step['more'] ?? [])));
    @endphp

    @if ($shots !== [])
        <div class="space-y-3 border-t border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-800/40">
            @foreach ($shots as $i => $shot)
                <a href="{{ $imageBase }}/{{ $shot }}" target="_blank" rel="noopener"
                   title="Open the full-size screenshot"
                   class="group block">
                    <img src="{{ $imageBase }}/{{ $shot }}" alt="{{ $step['title'] }}" loading="lazy"
                         class="w-full rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700">
                    <span class="mt-2 flex items-center gap-1 text-[11px] font-medium text-gray-400 group-hover:text-primary-600 dark:group-hover:text-primary-400">
                        <x-heroicon-m-arrows-pointing-out class="h-3 w-3" />
                        @if (count($shots) > 1)
                            Part {{ $i + 1 }} of {{ count($shots) }} — click to open full size
                        @else
                            Click to open full size
                        @endif
                    </span>
                </a>
            @endforeach
        </div>
    @endif
</article>
