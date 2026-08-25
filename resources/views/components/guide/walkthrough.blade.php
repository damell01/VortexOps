@props([
    'number' => null,
    'title'  => '',
    'screen' => null,
    'shot'   => null,
    'url'    => null,
])

{{--
    One stage of the cycle: what you are doing, where, and what it looks like.

    The picture sits beside the words on a wide screen and under them on a
    phone. Guides that describe a screen without showing it ask the reader to
    hold a mental image of somewhere they have not been yet, which is the part
    people get wrong — they follow the words onto the wrong page and conclude
    the guide is out of date.
--}}
<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex items-start gap-3 border-b border-gray-100 px-5 py-4 dark:border-gray-800">
        @if($number)
            <span class="grid h-7 w-7 flex-shrink-0 place-items-center rounded-full bg-violet-100 text-xs font-bold text-violet-700 dark:bg-violet-900/50 dark:text-violet-300">{{ $number }}</span>
        @endif
        <div class="min-w-0">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $title }}</h3>
            @if($screen)
                <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                    @if($url)
                        <a href="{{ $url }}" class="font-medium text-violet-600 hover:underline dark:text-violet-400">{{ $screen }}</a>
                    @else
                        {{ $screen }}
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="grid gap-5 p-5 lg:grid-cols-2">
        <div class="space-y-3 text-sm text-gray-600 dark:text-gray-300">
            {{ $slot }}
        </div>

        @if($shot)
            <figure class="min-w-0">
                {{-- loading="lazy": the walkthrough carries eight of these and
                     they are all below the fold but the first. --}}
                <img src="{{ asset('guide/' . $shot) }}"
                     alt="{{ $title }}"
                     loading="lazy"
                     class="w-full rounded-lg border border-gray-200 shadow-sm dark:border-gray-700" />
            </figure>
        @endif
    </div>
</div>
