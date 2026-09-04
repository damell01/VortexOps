@php
    $status = $this->getStatus();
    $overall = $status['overall'];
    $session = $status['session'];

    $tone = [
        'ok'       => ['bg-emerald-50 dark:bg-emerald-950/30', 'text-emerald-700 dark:text-emerald-400', 'border-emerald-200 dark:border-emerald-900'],
        'degraded' => ['bg-amber-50 dark:bg-amber-950/30',     'text-amber-700 dark:text-amber-400',     'border-amber-200 dark:border-amber-900'],
        'stale'    => ['bg-amber-50 dark:bg-amber-950/30',     'text-amber-700 dark:text-amber-400',     'border-amber-200 dark:border-amber-900'],
        'failing'  => ['bg-red-50 dark:bg-red-950/30',         'text-red-700 dark:text-red-400',         'border-red-200 dark:border-red-900'],
        'paused'   => ['bg-gray-100 dark:bg-gray-800',         'text-gray-600 dark:text-gray-300',       'border-gray-200 dark:border-gray-700'],
        'unknown'  => ['bg-gray-100 dark:bg-gray-800',         'text-gray-600 dark:text-gray-300',       'border-gray-200 dark:border-gray-700'],
    ];

    $headline = [
        'ok'       => 'All tracked ingestion jobs are healthy',
        'degraded' => 'One or more ingestion jobs partially completed',
        'stale'    => 'One or more ingestion jobs are running late',
        'failing'  => 'One or more ingestion jobs are failing',
        'paused'   => 'Whatnot ingestion is paused',
        'unknown'  => 'Waiting for job health data',
    ][$overall] ?? 'Importer status unknown';

    [$overallBg, $overallText, $overallBorder] = $tone[$overall] ?? $tone['unknown'];

    $pipelineBadge = function (string $status) {
        return match ($status) {
            'success' => ['bg-emerald-50 dark:bg-emerald-950/40', 'text-emerald-700 dark:text-emerald-400', '✓'],
            'failed'  => ['bg-red-50 dark:bg-red-950/40', 'text-red-700 dark:text-red-400', '✕'],
            'partial' => ['bg-amber-50 dark:bg-amber-950/40', 'text-amber-700 dark:text-amber-400', '!'],
            default   => ['bg-gray-100 dark:bg-gray-800', 'text-gray-500 dark:text-gray-400', '—'],
        };
    };
@endphp

<x-filament-panels::page>
    <div class="space-y-3 sm:space-y-5" data-vx-page="ingestion">
        <section class="overflow-hidden rounded-xl border {{ $overallBorder }} {{ $overallBg }} sm:rounded-2xl">
            <div class="flex flex-wrap items-start justify-between gap-3 p-4 sm:p-5">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-[.12em] {{ $overallText }} sm:text-xs">Whatnot ingestion health</div>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $headline }}</h2>
                    <p class="mt-1 max-w-3xl text-xs leading-5 text-gray-600 dark:text-gray-400 sm:text-sm">
                        Each scheduled pipeline is tracked separately so a shipment failure does not make every channel look broken.
                    </p>
                </div>

                <div class="text-right text-[11px] text-gray-500 dark:text-gray-400">
                    @if ($status['scheduler_ok'])
                        <div class="font-semibold text-emerald-700 dark:text-emerald-400">Scheduler healthy</div>
                    @else
                        <div class="font-semibold text-red-700 dark:text-red-400">Scheduler heartbeat missing</div>
                    @endif
                    @if ($status['scheduler_at'])
                        <div>Last heartbeat {{ $status['scheduler_at']->diffForHumans() }}</div>
                    @endif
                </div>
            </div>

            <div class="grid gap-px border-t {{ $overallBorder }} bg-gray-100 dark:bg-gray-800 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($status['jobs'] as $job)
                    @php [$jobBg, $jobText] = $tone[$job['state']] ?? $tone['unknown']; @endphp
                    <div class="bg-white p-4 dark:bg-gray-900 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $job['label'] }}</div>
                                <div class="mt-1 text-[11px] leading-4 text-gray-500 dark:text-gray-400">{{ $job['detail'] }}</div>
                            </div>
                            <span class="shrink-0 rounded-full {{ $jobBg }} px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $jobText }}">
                                {{ $job['state'] }}
                            </span>
                        </div>

                        <div class="mt-3 text-xs font-medium text-gray-700 dark:text-gray-300">{{ $job['note'] }}</div>

                        @if ($job['error'])
                            <div class="mt-2 rounded-lg bg-red-50 px-2.5 py-2 text-[11px] leading-4 text-red-700 dark:bg-red-950/30 dark:text-red-300">
                                {{ \Illuminate\Support\Str::limit($job['error'], 180) }}
                            </div>
                        @endif

                        <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400">
                            <span>{{ $job['every'] }}</span>
                            @if ($job['success_at'])
                                <span title="{{ $job['success_at']->toDayDateTimeString() }}">Last OK {{ $job['success_at']->diffForHumans() }}</span>
                            @endif
                            @if ($job['failure_at'])
                                <span title="{{ $job['failure_at']->toDayDateTimeString() }}">Last problem {{ $job['failure_at']->diffForHumans() }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="border-t {{ $overallBorder }} bg-white px-4 py-3 dark:bg-gray-900 sm:px-5">
                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-600 dark:text-gray-400">
                    <span class="font-semibold text-gray-700 dark:text-gray-300">Whatnot session</span>
                    @if ($session['exists'])
                        <span title="{{ $session['saved_at']->toDayDateTimeString() }}">Saved {{ $session['saved_at']->diffForHumans() }}</span>
                    @else
                        <span class="rounded-full bg-red-50 px-2 py-0.5 font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-400">No stored session</span>
                    @endif
                    <span class="text-gray-400 dark:text-gray-500">{{ $session['path'] }}</span>
                </div>
            </div>
        </section>

        @if (filled($status['channels']))
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                    <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Pipeline health by channel</h2>
                    <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Shows, orders, shipments and ledger are tracked independently for each channel. Click a card to filter the detailed log.</p>
                </div>

                @php $focused = $this->focusedChannelId(); @endphp
                <div class="grid gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($status['channels'] as $row)
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
                                <div>
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['channel']->name }}</div>
                                    <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                                        {{ $row['last_at'] ? 'Last activity ' . $row['last_at']->diffForHumans() : 'No pipeline data yet' }}
                                    </div>
                                </div>
                                @if ($row['failures_24h'] > 0)
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 dark:bg-red-950/40 dark:text-red-400">{{ $row['failures_24h'] }} problems</span>
                                @endif
                            </div>

                            <div class="mt-3 space-y-2">
                                @foreach ($row['pipelines'] as $pipeline)
                                    @php [$pBg, $pText, $pIcon] = $pipelineBadge($pipeline['status']); @endphp
                                    <div class="flex items-center justify-between gap-2 text-[11px]">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $pipeline['label'] }}</span>
                                        <span class="rounded-full {{ $pBg }} px-2 py-0.5 font-semibold {{ $pText }}">
                                            {{ $pIcon }} {{ ucfirst($pipeline['status']) }}
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Detailed ingestion log</h2>
                <p class="mt-0.5 text-[11px] leading-4 text-gray-500 dark:text-gray-400 sm:text-xs">Use the Job / Pipeline column and filters to see exactly which scheduled task succeeded or failed.</p>
            </div>
            <div class="p-1 sm:p-2">{{ $this->table }}</div>
        </section>
    </div>
</x-filament-panels::page>
