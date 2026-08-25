@php
    $status   = $this->getStatus();
    $overall  = $status['overall'];
    $session  = $status['session'];

    $tone = [
        'ok'      => ['bg-emerald-50 dark:bg-emerald-950/30', 'text-emerald-700 dark:text-emerald-400', 'border-emerald-200 dark:border-emerald-900'],
        'stale'   => ['bg-amber-50 dark:bg-amber-950/30',     'text-amber-700 dark:text-amber-400',     'border-amber-200 dark:border-amber-900'],
        'failing' => ['bg-red-50 dark:bg-red-950/30',         'text-red-700 dark:text-red-400',         'border-red-200 dark:border-red-900'],
        'paused'  => ['bg-gray-100 dark:bg-gray-800',         'text-gray-600 dark:text-gray-300',       'border-gray-200 dark:border-gray-700'],
        'unknown' => ['bg-gray-100 dark:bg-gray-800',         'text-gray-600 dark:text-gray-300',       'border-gray-200 dark:border-gray-700'],
    ];

    $headline = [
        'ok'      => 'Importer is working',
        'stale'   => 'Importer is running late',
        'failing' => 'Importer is failing',
        'paused'  => 'Importer is paused',
        'unknown' => 'Importer has not run yet',
    ][$overall] ?? 'Importer status unknown';

    [$overallBg, $overallText, $overallBorder] = $tone[$overall] ?? $tone['unknown'];
@endphp

<x-filament-panels::page>
    <div class="space-y-3 sm:space-y-5" data-vx-page="ingestion">

        {{-- ── Is it working? ──────────────────────────────────────────────
             The scheduler has recorded the answer to this since the jobs were
             written; until now nothing read it back, so a job failing every
             ten minutes showed up only as a number that quietly stopped
             moving. --}}
        <section class="overflow-hidden rounded-xl border {{ $overallBorder }} {{ $overallBg }} sm:rounded-2xl">
            <div class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] {{ $overallText }} sm:text-xs">Whatnot importer</div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $headline }}</h2>

                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-600 dark:text-gray-400 sm:text-sm">
                        @if ($status['paused'])
                            The scheduled jobs are switched off, so nothing imports on its own. Set
                            <code class="rounded bg-white/70 px-1 dark:bg-gray-900/70">WHATNOT_SCHEDULE_ENABLED=true</code> to start them again.
                        @elseif (! $status['scheduler_ok'])
                            The scheduler itself has not checked in
                            {{ $status['scheduler_at'] ? $status['scheduler_at']->diffForHumans() : 'at all' }}.
                            Nothing scheduled is running — check cron before looking at the importer.
                        @else
                            Runs on its own every few minutes. The jobs below record whether each attempt worked.
                        @endif
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <a href="{{ \App\Filament\Pages\WhatnotScraperPage::getUrl() }}"
                       class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        <x-heroicon-o-play class="h-4 w-4" /> Run an import
                    </a>
                    <a href="{{ \App\Filament\Pages\WhatnotSyncPage::getUrl() }}"
                       class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                        <x-heroicon-o-arrow-path class="h-4 w-4" /> Sync dashboard
                    </a>
                </div>
            </div>

            {{-- Per-job outcome. Two jobs, each with its own idea of "late". --}}
            <div class="grid gap-px border-t {{ $overallBorder }} bg-gray-100 dark:bg-gray-800 sm:grid-cols-2">
                @foreach ($status['jobs'] as $job)
                    @php [$jobBg, $jobText] = $tone[$job['state']] ?? $tone['unknown']; @endphp
                    <div class="bg-white p-4 dark:bg-gray-900 sm:p-5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $job['label'] }}</div>
                            <span class="rounded-full {{ $jobBg }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $jobText }}">
                                {{ $job['state'] }}
                            </span>
                        </div>
                        <div class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400">{{ $job['detail'] }}</div>
                        <div class="mt-2 text-xs text-gray-700 dark:text-gray-300">{{ $job['note'] }}</div>
                        <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                            <span>{{ $job['every'] }}</span>
                            @if ($job['success_at'])
                                <span title="{{ $job['success_at']->toDayDateTimeString() }}">Last OK: {{ $job['success_at']->diffForHumans() }}</span>
                            @endif
                            @if ($job['failure_at'])
                                <span title="{{ $job['failure_at']->toDayDateTimeString() }}">Last failure: {{ $job['failure_at']->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- The single most common cause of "everything stopped": Cloudflare
                 will not let the scraper sign itself in, so an expired cookie
                 file is a dead importer until a person refreshes it. --}}
            <div class="border-t {{ $overallBorder }} bg-white px-4 py-3 dark:bg-gray-900 sm:px-5">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Whatnot session</span>
                    @if ($session['exists'])
                        <span title="{{ $session['saved_at']->toDayDateTimeString() }}">
                            Saved {{ $session['saved_at']->diffForHumans() }}
                        </span>
                        @if ($session['saved_at']->diffInDays(now()) >= 60)
                            <span class="rounded-full bg-amber-50 px-2 py-0.5 font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-400">
                                Cookies usually last 30–90 days — refresh this soon
                            </span>
                        @endif
                    @else
                        <span class="rounded-full bg-red-50 px-2 py-0.5 font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-400">
                            No stored session — run <code>php artisan whatnot:login</code>
                        </span>
                    @endif
                    <span class="text-gray-400 dark:text-gray-500">{{ $session['path'] }}</span>
                </div>
            </div>
        </section>

        {{-- ── By channel ──────────────────────────────────────────────────
             The question the old page could not answer: rows carried no
             channel of their own, and the failures — the ones worth finding —
             carried no show to join through either. --}}
        @if (filled($status['channels']))
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">By channel</h2>
                    <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Activity over the last 24 hours. Click a channel to filter the log below it.</p>
                </div>

                @php $focused = $this->focusedChannelId(); @endphp

                <div class="grid gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($status['channels'] as $row)
                        {{-- A div, not a <button>: accessibility-enterprise-polish.css
                             skins every plain button[type=button] as a grey
                             inline-flex secondary button, which centres and
                             reflows whatever is inside one. --}}
                        <div
                            role="button"
                            tabindex="0"
                            wire:click="focusChannel({{ $row['channel']->id }})"
                            wire:keydown.enter.prevent="focusChannel({{ $row['channel']->id }})"
                            wire:keydown.space.prevent="focusChannel({{ $row['channel']->id }})"
                            aria-pressed="{{ $focused === $row['channel']->id ? 'true' : 'false' }}"
                            @class([
                                'w-full cursor-pointer p-4 text-left transition',
                                'bg-primary-50 dark:bg-primary-950/40' => $focused === $row['channel']->id,
                                'bg-white hover:bg-gray-50 dark:bg-gray-900 dark:hover:bg-gray-800/70' => $focused !== $row['channel']->id,
                            ])
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['channel']->name }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        @if ($row['last_at'])
                                            <span title="{{ $row['last_at']->toDayDateTimeString() }}">Last activity {{ $row['last_at']->diffForHumans() }}</span>
                                        @else
                                            Nothing imported yet
                                        @endif
                                    </div>
                                </div>

                                @if ($row['failures_24h'] > 0)
                                    <span class="shrink-0 rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950/40 dark:text-red-400">
                                        {{ $row['failures_24h'] }} failed
                                    </span>
                                @elseif ($row['runs_24h'] > 0)
                                    <span class="shrink-0 rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-400">
                                        All OK
                                    </span>
                                @endif
                            </div>

                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                                <span><span class="font-semibold text-gray-700 dark:text-gray-300">{{ number_format($row['runs_24h']) }}</span> records in 24h</span>
                                @if ($row['last_success_at'])
                                    <span title="{{ $row['last_success_at']->toDayDateTimeString() }}">Last success {{ $row['last_success_at']->diffForHumans() }}</span>
                                @endif
                                @if ($focused === $row['channel']->id)
                                    <span class="font-semibold text-primary-600 dark:text-primary-400">Filtering the log · click to clear</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Ingestion log</h2>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Every record the importer wrote, newest first. Open one to see the raw payload it was built from.</p>
            </div>
            <div class="p-1 sm:p-2">
                {{ $this->table }}
            </div>
        </section>
    </div>
</x-filament-panels::page>
