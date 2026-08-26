<x-filament-panels::page>
@php
    $sections  = $this->allSections();
    $open      = $this->visibleSections();
    $searching = trim($this->search) !== '';
    $imageBase = asset(\App\Support\InventoryManual::IMAGE_DIR);

    $troubleshooting = \App\Filament\Pages\Handbook::TROUBLESHOOTING;
    $screenIndex     = \App\Filament\Pages\Handbook::SCREEN_INDEX;

    $onSection = ! $searching && $this->section !== null && ! $this->onExtraPage();

    // One place for the two states a contents entry can be in.
    $entry = fn (bool $active) => $active
        ? 'bg-primary-600 text-white shadow-sm'
        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800';
@endphp

<div class="space-y-5">

    {{-- ── Masthead ─────────────────────────────────────────────────────────
         Says what the thing is and how big it is before anyone clicks into
         it, because the first question about a manual is always "is what I
         need in here?". --}}
    <section class="overflow-hidden rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 via-white to-white dark:border-primary-500/20 dark:from-primary-500/10 dark:via-gray-900 dark:to-gray-900">
        <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between sm:p-6">
            <div class="min-w-0">
                <div class="text-[11px] font-bold uppercase tracking-[.14em] text-primary-600 dark:text-primary-400">
                    {{ $this->modules()[$this->module]['label'] ?? 'Handbook' }}
                </div>
                <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white sm:text-2xl">
                    How this module works, screen by screen
                </h2>
                <p class="mt-1.5 max-w-2xl text-sm leading-6 text-gray-500 dark:text-gray-400">
                    Every screen, every button, and what each field on it is for — with a picture of the real
                    thing, taken from this installation. The same material as the printed handbook.
                </p>

                <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                    <span><b class="text-gray-900 dark:text-white">{{ count($sections) }}</b> sections</span>
                    <span><b class="text-gray-900 dark:text-white">{{ $this->totalSteps }}</b> walkthroughs</span>
                    <span><b class="text-gray-900 dark:text-white">{{ count($this->troubleshooting()) }}</b> things that go wrong</span>
                    <span><b class="text-gray-900 dark:text-white">{{ count($this->screenIndex()) }}</b> screens indexed</span>
                </div>
            </div>

            @if ($this->module === 'inventory')
                <div class="flex shrink-0 flex-col gap-2 sm:items-end">
                    <button type="button" wire:click="openSection(0)"
                            class="vx-plain inline-flex items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-primary-500">
                        Start at the beginning
                        <x-heroicon-o-arrow-right class="h-4 w-4" />
                    </button>
                    <a href="{{ route('export.inventory-manual-pdf') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                        <x-heroicon-o-document-arrow-down class="h-4 w-4" />
                        Printable PDF
                    </a>
                </div>
            @endif
        </div>
    </section>

    {{-- ── Module switcher ───────────────────────────────────────────────────
         Inventory is the only one written. The rest are shown rather than
         hidden on purpose: knowing a handbook is coming is more useful than
         wondering whether one exists somewhere you have not looked. --}}
    <div class="flex gap-1.5 overflow-x-auto rounded-xl border border-gray-200 bg-white p-1 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        @foreach ($this->modules() as $key => $module)
            <button type="button"
                    @if ($module['ready']) wire:click="selectModule('{{ $key }}')" @else disabled @endif
                    class="vx-plain flex shrink-0 items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                        {{ $this->module === $key
                            ? 'bg-primary-600 text-white shadow'
                            : ($module['ready']
                                ? 'text-gray-500 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-800'
                                : 'cursor-not-allowed text-gray-300 dark:text-gray-600') }}">
                <x-dynamic-component :component="$module['icon']" class="h-4 w-4" />
                {{ $module['label'] }}
                @unless ($module['ready'])
                    <span class="rounded bg-gray-100 px-1 py-px text-[10px] font-semibold uppercase tracking-wide text-gray-400 dark:bg-gray-800">Soon</span>
                @endunless
            </button>
        @endforeach
    </div>

    {{-- ── Search ──────────────────────────────────────────────────────────
         Searches step text, screen paths, warnings and field descriptions,
         which is the difference between "where do I set a reorder level" and
         having to guess which section it lives in. --}}
    <div class="relative">
        <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
        <input type="search"
               wire:model.live.debounce.350ms="search"
               placeholder="Search everything — a field name, a button, a screen, or what went wrong"
               class="w-full rounded-xl border-gray-300 bg-white py-3 pl-10 pr-3 text-sm shadow-sm placeholder:text-gray-400 focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-900 dark:text-white">
    </div>

    <div class="grid gap-5 lg:grid-cols-[17rem_minmax(0,1fr)]">

        {{-- ── Contents ────────────────────────────────────────────────────
             Hidden on a phone once you are reading something: a full contents
             list above every section is a screen of scrolling before the first
             sentence. The Back button below brings it back. --}}
        <aside class="{{ ($this->section !== null || $searching) ? 'hidden lg:block' : '' }}">
            <div class="space-y-1 lg:sticky lg:top-4">
                <div class="px-2 pb-1 text-[11px] font-bold uppercase tracking-[.12em] text-gray-400">Contents</div>

                @foreach ($sections as $i => $section)
                    @php
                        $active = $this->section === $i && ! $searching;
                    @endphp
                    <button type="button" wire:click="openSection({{ $i }})"
                            class="vx-plain flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors {{ $entry($active) }}">
                        <x-dynamic-component :component="$section['icon'] ?? 'heroicon-o-book-open'"
                                             class="h-4 w-4 shrink-0 {{ $active ? 'text-white' : 'text-gray-400' }}" />
                        <span class="min-w-0 flex-1 truncate">{{ $section['title'] }}</span>
                        <span class="shrink-0 rounded px-1.5 text-[11px] {{ $active ? 'bg-white/20 text-white' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                            {{ count($section['steps']) }}
                        </span>
                    </button>
                @endforeach

                <div class="mt-2 space-y-1 border-t border-gray-200 pt-3 dark:border-gray-700">
                    <button type="button" wire:click="openSection({{ $troubleshooting }})"
                            class="vx-plain flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors {{ $entry($this->section === $troubleshooting && ! $searching) }}">
                        <x-heroicon-o-lifebuoy class="h-4 w-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">When something looks wrong</span>
                    </button>
                    <button type="button" wire:click="openSection({{ $screenIndex }})"
                            class="vx-plain flex w-full items-center gap-2.5 rounded-lg px-2.5 py-2 text-left text-sm font-medium transition-colors {{ $entry($this->section === $screenIndex && ! $searching) }}">
                        <x-heroicon-o-list-bullet class="h-4 w-4 shrink-0" />
                        <span class="min-w-0 flex-1 truncate">Every screen</span>
                    </button>
                </div>
            </div>
        </aside>

        {{-- ── Content pane ─────────────────────────────────────────────────- --}}
        <div class="min-w-0 space-y-4">

            @if ($this->section !== null || $searching)
                <button type="button"
                        wire:click="openSection(null)"
                        class="vx-plain flex items-center gap-1 text-sm font-medium text-primary-600 hover:underline lg:hidden dark:text-primary-400">
                    <x-heroicon-o-arrow-left class="h-4 w-4" /> All sections
                </button>
            @endif

            @if ($searching)
                {{-- ── Search results ──────────────────────────────────────- --}}
                @if ($this->searchResultCount === 0)
                    <x-guide.panel tone="amber" title="Nothing matches “{{ $this->search }}”">
                        <p>
                            Try a word off the screen itself — a button label, a field name, or the
                            error you are looking at. Only the {{ $this->module }} handbook is searched.
                        </p>
                    </x-guide.panel>
                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        <b class="text-gray-900 dark:text-white">{{ $this->searchResultCount }}</b>
                        {{ Str::plural('step', $this->searchResultCount) }} of {{ $this->totalSteps }} mention “{{ $this->search }}”.
                    </p>

                    @foreach ($open as $result)
                        <div class="space-y-3">
                            <button type="button" wire:click="openSection({{ $result['index'] }})"
                                    class="vx-plain text-xs font-bold uppercase tracking-[.12em] text-primary-600 hover:underline dark:text-primary-400">
                                {{ $result['index'] + 1 }}. {{ $result['section']['title'] }}
                            </button>

                            @foreach ($result['section']['steps'] as $step)
                                <x-handbook.step :step="$step" :image-base="$imageBase" />
                            @endforeach
                        </div>
                    @endforeach
                @endif

            @elseif ($this->section === $troubleshooting)
                {{-- ── Troubleshooting ─────────────────────────────────────- --}}
                <x-handbook.pane-heading
                    icon="heroicon-o-lifebuoy"
                    title="When something looks wrong"
                    blurb="Each of these has one cause far more often than any other." />

                <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-900">
                    @foreach ($this->troubleshooting() as [$question, $answer])
                        <div class="grid gap-1 p-4 sm:grid-cols-[17rem_1fr] sm:gap-4">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $question }}</div>
                            <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $answer }}</div>
                        </div>
                    @endforeach
                </div>

            @elseif ($this->section === $screenIndex)
                {{-- ── Screen index ────────────────────────────────────────- --}}
                <x-handbook.pane-heading
                    icon="heroicon-o-list-bullet"
                    title="Every screen, and what it is for"
                    blurb="For when you know what you want and only need to be told where it lives." />

                <div class="divide-y divide-gray-100 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:divide-gray-800 dark:border-gray-700 dark:bg-gray-900">
                    @foreach ($this->screenIndex() as [$screen, $purpose])
                        <div class="grid gap-1 p-4 sm:grid-cols-[17rem_1fr] sm:gap-4">
                            <div class="text-sm font-semibold text-gray-900 dark:text-white">{{ $screen }}</div>
                            <div class="text-sm leading-6 text-gray-600 dark:text-gray-300">{{ $purpose }}</div>
                        </div>
                    @endforeach
                </div>

            @elseif ($onSection && $open !== [])
                {{-- ── One section, in order ───────────────────────────────- --}}
                @php
                    $current = $open[0]['section'];
                @endphp

                <x-handbook.pane-heading
                    :icon="$current['icon'] ?? 'heroicon-o-book-open'"
                    :number="$open[0]['index'] + 1"
                    :title="$current['title']"
                    :blurb="$current['blurb']"
                    :count="count($current['steps'])" />

                @foreach ($current['steps'] as $n => $step)
                    <x-handbook.step :step="$step" :number="$n + 1" :image-base="$imageBase" />
                @endforeach

            @elseif ($sections === [])
                {{-- ── A module with nothing written yet ───────────────────- --}}
                <x-guide.panel tone="amber" title="This handbook has not been written yet">
                    <p>Only the Inventory handbook exists today. The others are listed so you know they are coming.</p>
                </x-guide.panel>

            @else
                {{-- ── Overview ────────────────────────────────────────────- --}}
                <div class="grid gap-3 sm:grid-cols-2">
                    @foreach ($sections as $i => $section)
                        <button type="button" wire:click="openSection({{ $i }})"
                                class="vx-plain group flex flex-col rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm transition hover:-translate-y-0.5 hover:border-primary-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary-100 text-primary-700 dark:bg-primary-500/15 dark:text-primary-300">
                                    <x-dynamic-component :component="$section['icon'] ?? 'heroicon-o-book-open'" class="h-4 w-4" />
                                </span>
                                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
                                    {{ $i + 1 }}. {{ $section['title'] }}
                                </h3>
                            </div>
                            <p class="mt-2 flex-1 text-xs leading-5 text-gray-500 dark:text-gray-400">{!! $section['blurb'] !!}</p>
                            <div class="mt-3 text-[11px] font-semibold uppercase tracking-wide text-primary-600 dark:text-primary-400">
                                {{ count($section['steps']) }} {{ Str::plural('step', count($section['steps'])) }}
                                <span class="inline-block transition-transform group-hover:translate-x-0.5">→</span>
                            </div>
                        </button>
                    @endforeach
                </div>

                <x-guide.panel tone="violet" title="One rule underneath all of this">
                    <p>
                        A quantity never changes by being typed over. It changes by receiving, transferring,
                        adjusting or reconciling — and each of those records what happened and who did it.
                        That record is the difference between a discrepancy you can trace and one you can
                        only argue about.
                    </p>
                </x-guide.panel>
            @endif

            {{-- ── Read-through navigation ─────────────────────────────────- --}}
            @if (! $searching && $this->section !== null)
                @php
                    $prev = $this->neighbour(-1);
                    $next = $this->neighbour(1);
                @endphp
                <div class="flex items-center justify-between gap-3 pt-1">
                    <div>
                        @if ($prev)
                            <button type="button" wire:click="openSection({{ $prev[0] }})"
                                    class="vx-plain flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                                <x-heroicon-o-arrow-left class="h-4 w-4 shrink-0" />
                                <span class="max-w-[9rem] truncate sm:max-w-none">{{ $prev[1] }}</span>
                            </button>
                        @endif
                    </div>
                    <div>
                        @if ($next)
                            <button type="button" wire:click="openSection({{ $next[0] }})"
                                    class="vx-plain flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-600 shadow-sm transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 dark:hover:bg-gray-800">
                                <span class="max-w-[9rem] truncate sm:max-w-none">{{ $next[1] }}</span>
                                <x-heroicon-o-arrow-right class="h-4 w-4 shrink-0" />
                            </button>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
</x-filament-panels::page>
