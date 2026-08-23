@php
    $groups   = $this->groups;
    $hasAny   = $this->hasAny;
    $syncedAt = $this->syncedAt;
@endphp

<x-filament-widgets::widget>
    <section
        x-data="{ open: false }"
        class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl"
    >
        {{-- Collapsed by default. This is reference, not a decision, and it is
             sixteen figures — open on arrival it would push the show's actual
             work off a phone screen. --}}
        <button
            type="button"
            @click="open = !open"
            class="flex w-full items-center gap-3 px-4 py-4 text-left transition-colors hover:bg-gray-50 dark:hover:bg-gray-800/50 sm:px-5"
        >
            <x-heroicon-o-presentation-chart-line class="h-5 w-5 shrink-0 text-primary-500" />

            <div class="min-w-0 flex-1">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Whatnot analytics</h2>
                <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">
                    @if (! $hasAny)
                        Nothing imported for this show
                    @elseif ($syncedAt)
                        Everything Whatnot reported · synced {{ $syncedAt }}
                    @else
                        Everything Whatnot reported for this show
                    @endif
                </p>
            </div>

            <span :class="open ? 'rotate-90' : ''" class="shrink-0 transition-transform duration-200">
                <x-heroicon-o-chevron-right class="h-4 w-4 text-gray-400" />
            </span>
        </button>

        <div x-show="open" x-cloak class="border-t border-gray-100 dark:border-gray-800">
            @if (! $hasAny)
                <p class="px-4 py-5 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:px-5 sm:text-sm">
                    This show has no Whatnot analytics. That is expected for a show added by hand —
                    the figures below come from Whatnot's own analytics page and only exist for shows
                    it imported.
                </p>
            @else
                @foreach ($groups as $groupName => $metrics)
                    <div class="px-4 py-4 sm:px-5 sm:py-5 @if(! $loop->first) border-t border-gray-100 dark:border-gray-800 @endif">
                        <h3 class="text-[10px] font-bold uppercase tracking-[.12em] text-gray-400 sm:text-xs">{{ $groupName }}</h3>

                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-4 sm:grid-cols-3 lg:grid-cols-4">
                            @foreach ($metrics as $metric)
                                <div class="min-w-0">
                                    <dt class="truncate text-[11px] text-gray-500 dark:text-gray-400" @if($metric['hint']) title="{{ $metric['hint'] }}" @endif>
                                        {{ $metric['label'] }}
                                    </dt>

                                    {{-- A dash, not a zero. Whatnot reporting
                                         nothing and Whatnot reporting none are
                                         different facts, and printing 0 for the
                                         first states something it never said. --}}
                                    <dd @class([
                                        'mt-0.5 tabular-nums',
                                        'text-base font-semibold text-gray-950 dark:text-white sm:text-lg' => $metric['value'] !== null,
                                        'text-base text-gray-300 dark:text-gray-600 sm:text-lg'            => $metric['value'] === null,
                                    ])>
                                        {{ $metric['value'] ?? '—' }}
                                    </dd>

                                    @if ($metric['hint'])
                                        <p class="mt-0.5 text-[10px] leading-4 text-gray-400 dark:text-gray-500">{{ $metric['hint'] }}</p>
                                    @endif
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endforeach

                <p class="border-t border-gray-100 px-4 py-3 text-[10px] leading-4 text-gray-400 dark:border-gray-800 dark:text-gray-500 sm:px-5">
                    A dash means Whatnot reported no figure, which is not the same as a figure of zero.
                </p>
            @endif
        </div>
    </section>
</x-filament-widgets::widget>
