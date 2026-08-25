@props(['step', 'number' => null, 'imageBase'])

{{--
    One step of the handbook, on screen.

    Deliberately the same four parts as the printed page — instruction, field
    reference, warning, picture — and in the same order, so someone who has
    read the PDF is not learning a second document.
--}}
<article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
    <div class="flex items-start gap-3">
        @if ($number)
            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-primary-100 text-xs font-bold text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                {{ $number }}
            </span>
        @endif

        <div class="min-w-0 flex-1">
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">{{ $step['title'] }}</h3>

            @if (! empty($step['where']))
                <div class="mt-1 inline-flex items-center gap-1 rounded-md bg-primary-50 px-2 py-0.5 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                    <x-heroicon-m-map-pin class="h-3 w-3" />
                    {{ $step['where'] }}
                </div>
            @endif

            <div class="mt-2 space-y-2 text-sm leading-6 text-gray-600 dark:text-gray-300">
                @foreach ($step['body'] as $paragraph)
                    <p>{!! $paragraph !!}</p>
                @endforeach
            </div>

            @if (! empty($step['fields']))
                {{-- Open by default. A field reference that has to be found
                     before it can be read is a field reference nobody reads. --}}
                <details open class="mt-3 rounded-lg border border-gray-200 dark:border-gray-700">
                    <summary class="cursor-pointer select-none px-3 py-2 text-[11px] font-bold uppercase tracking-[.1em] text-gray-500 dark:text-gray-400">
                        Every field on this screen ({{ count($step['fields']) }})
                    </summary>
                    <div class="divide-y divide-gray-100 border-t border-gray-200 dark:divide-gray-800 dark:border-gray-700">
                        @foreach ($step['fields'] as [$field, $meaning])
                            <div class="grid gap-0.5 px-3 py-2 sm:grid-cols-[11rem_1fr] sm:gap-3">
                                <div class="text-xs font-semibold text-primary-700 dark:text-primary-300">{{ $field }}</div>
                                <div class="text-xs leading-5 text-gray-600 dark:text-gray-300">{!! $meaning !!}</div>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

            @if (! empty($step['note']))
                <div class="mt-3 rounded-lg border-l-2 border-amber-400 bg-amber-50 px-3 py-2 text-xs leading-5 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
                    {!! $step['note'] !!}
                </div>
            @endif

            @if (! empty($step['shot']))
                <a href="{{ $imageBase }}/{{ $step['shot'] }}" target="_blank" rel="noopener"
                   title="Open the full-size screenshot"
                   class="mt-3 block overflow-hidden rounded-lg border border-gray-200 transition hover:border-primary-400 dark:border-gray-700">
                    <img src="{{ $imageBase }}/{{ $step['shot'] }}" alt="{{ $step['title'] }}" loading="lazy" class="w-full">
                </a>
            @endif
        </div>
    </div>
</article>
