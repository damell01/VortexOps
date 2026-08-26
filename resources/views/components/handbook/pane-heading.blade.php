@props(['icon' => 'heroicon-o-book-open', 'title', 'blurb' => null, 'number' => null, 'count' => null])

{{-- The heading of whatever the right-hand pane is showing. One component so
     a section, the troubleshooting page and the screen index cannot drift into
     three slightly different headings. --}}
<div class="flex items-start gap-3">
    <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
        <x-dynamic-component :component="$icon" class="h-5 w-5" />
    </span>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-950 dark:text-white">
                @if ($number)<span class="text-primary-600 dark:text-primary-400">{{ $number }}.</span>@endif
                {{ $title }}
            </h2>
            @if ($count)
                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[11px] font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    {{ $count }} {{ Str::plural('step', $count) }}
                </span>
            @endif
        </div>

        @if ($blurb)
            <p class="mt-1 text-sm leading-6 text-gray-500 dark:text-gray-400">{!! $blurb !!}</p>
        @endif
    </div>
</div>
