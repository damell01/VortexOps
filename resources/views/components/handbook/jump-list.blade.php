@props(['steps', 'sectionKey' => 0])

{{--
    Jump to a step within the open section.

    A section runs to fourteen steps with a picture each, which is a long scroll
    to reach step eleven and a longer one to find your place again after you
    looked at the screen. These are plain anchors, so they work whatever Alpine
    does; the highlight is the enhancement, not the mechanism.
--}}
<nav wire:key="jump-{{ $sectionKey }}"
     x-data="{
        current: null,
        init() {
            // The observer window is a band near the top of the viewport: a
            // step counts as 'current' when its heading is in that band, not
            // when any part of a tall step is on screen.
            const observer = new IntersectionObserver(
                (entries) => entries.forEach((entry) => {
                    if (entry.isIntersecting) this.current = entry.target.id;
                }),
                { rootMargin: '-96px 0px -70% 0px' },
            );

            this.$nextTick(() => document
                .querySelectorAll('[data-handbook-step]')
                .forEach((step) => observer.observe(step)));
        },
     }"
     {{ $attributes }}>
    <div class="px-2 pb-1 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">In this section</div>

    @foreach ($steps as $n => $step)
        {{-- Scrolled rather than jumped: it is smooth, it honours the step's
             scroll-margin so the heading clears the sticky topbar, and it keeps
             a hash out of a URL that already carries the open section. The href
             stays for middle-click, the status bar and anyone without JS. --}}
        <a href="#step-{{ $n + 1 }}"
           x-on:click.prevent="document.getElementById('step-{{ $n + 1 }}')
                ?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
           x-bind:class="current === 'step-{{ $n + 1 }}'
                ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300'
                : 'text-gray-500 hover:bg-gray-100 dark:text-gray-400 dark:hover:bg-gray-800'"
           class="flex items-start gap-2 rounded-lg px-2.5 py-1.5 text-xs leading-5 transition-colors">
            {{-- Wide enough for two digits: at w-4 the number in "10" broke
                 across two lines and read as a 1 above a 0. --}}
            <span class="w-5 shrink-0 whitespace-nowrap text-right font-semibold tabular-nums">{{ $n + 1 }}</span>
            <span class="min-w-0 flex-1">{{ $step['title'] }}</span>
        </a>
    @endforeach
</nav>
