<x-filament-panels::page>
    @php
        $current = $this->currentPayRun();
        $attention = $this->needsAttention();
        $breakdown = $this->currentBreakdown();
        $people = $this->currentPeople();
        $shows = $this->currentShows();
        $recent = $this->recentPayRuns();
    @endphp

    <div class="space-y-4">
        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="p-4 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <div class="text-xs font-bold uppercase tracking-[.12em] text-primary-600">Payroll workspace</div>
                        <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $current ? 'Current Pay Run' : 'Build this week’s payroll' }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if($current)
                                {{ $current->week_start->format('M j') }} – {{ $current->week_end->format('M j, Y') }} · {{ \App\Models\WeeklyPayoutBatch::statusLabels()[$current->status] ?? ucfirst($current->status) }}
                            @else
                                Shows, streamer reports and fulfillment work roll into one weekly total.
                            @endif
                        </p>
                    </div>
                    @if($current)
                        <a href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $current]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary-600 px-3 text-xs font-bold text-white hover:bg-primary-500">Review / finalize run →</a>
                    @endif
                </div>

                <div class="mt-4 grid grid-cols-2 gap-px overflow-hidden rounded-xl bg-gray-100 dark:bg-gray-800 sm:grid-cols-6">
                    @foreach ([
                        ['Payroll', '$'.number_format((float)($current?->total_payout ?? 0), 2)],
                        ['Streamer Pay', '$'.number_format($breakdown['streamer_total'], 2)],
                        ['Fulfillment', '$'.number_format($breakdown['fulfillment_total'], 2)],
                        ['People', number_format($breakdown['people'])],
                        ['Shows', number_format($shows->count())],
                        ['Needs Review', number_format(count($attention))],
                    ] as [$label, $value])
                        <div class="bg-white px-3 py-3 dark:bg-gray-900">
                            <div class="truncate text-[10px] font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</div>
                            <div class="mt-1 truncate text-lg font-bold text-gray-950 dark:text-white sm:text-xl">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        @if(count($attention))
            <section class="rounded-2xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/60 dark:bg-amber-950/20 sm:p-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-sm font-bold text-amber-900 dark:text-amber-100">Needs Attention</h3>
                        <p class="text-[11px] text-amber-700 dark:text-amber-300">Fix these before finalizing payroll.</p>
                    </div>
                    <span class="rounded-full bg-amber-100 px-2 py-1 text-xs font-bold text-amber-800 dark:bg-amber-900/50 dark:text-amber-100">{{ count($attention) }}</span>
                </div>
                <div class="mt-3 grid gap-2 lg:grid-cols-2">
                    @foreach($attention as $warning)
                        <div class="rounded-lg bg-white/75 px-3 py-2 text-xs font-medium text-amber-900 dark:bg-gray-900/60 dark:text-amber-100">{{ $warning }}</div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">People in this Pay Run</h3>
                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Each total is built from the person’s show/activity entries for the week.</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($people as $person)
                    <details class="group px-4 py-3 sm:px-5">
                        <summary class="flex cursor-pointer list-none items-center gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary-50 text-xs font-bold text-primary-700 dark:bg-primary-950/40 dark:text-primary-200">{{ strtoupper(substr($person['name'], 0, 1)) }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $person['name'] }}</span>
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $person['role'] }}</span>
                                </div>
                                <div class="mt-0.5 text-[10px] text-gray-500">{{ $person['entries']->count() }} earning line(s) · {{ str_replace('_', ' ', $person['payout_type'] ?? 'configured pay') }}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-bold text-gray-950 dark:text-white">${{ number_format($person['total'], 2) }}</div>
                                @if(abs($person['adjustments']) > 0.004)<div class="text-[10px] text-gray-500">{{ $person['adjustments'] < 0 ? '-' : '+' }}${{ number_format(abs($person['adjustments']), 2) }} adj.</div>@endif
                            </div>
                            <x-heroicon-m-chevron-down class="h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </summary>

                        <div class="mt-3 overflow-hidden rounded-xl border border-gray-100 dark:border-gray-800">
                            @foreach($person['entries'] as $entry)
                                <div class="border-b border-gray-100 px-3 py-2.5 last:border-0 dark:border-gray-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="truncate text-xs font-semibold text-gray-800 dark:text-gray-100">{{ $entry->show?->title ?? 'Manual payroll entry' }}</div>
                                            <div class="mt-0.5 text-[10px] text-gray-500">{{ $entry->show?->show_date?->format('M j, Y') ?? 'No show date' }}</div>
                                            @if($entry->calculation_notes)<div class="mt-1 text-[10px] leading-4 text-gray-500 dark:text-gray-400">{{ $entry->calculation_notes }}</div>@endif
                                        </div>
                                        <div class="shrink-0 text-xs font-bold text-gray-950 dark:text-white">${{ number_format((float)$entry->calculated_payout, 2) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500">No payout entries are attached to this week yet. Use <strong>Build/Refresh Current Week</strong> above.</div>
                @endforelse
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Shows Feeding This Week</h3>
                <p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">Sales → COGS → payroll → final show profit. The status tells you what is blocking the show.</p>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($shows as $row)
                    @php
                        $tone = match($row['tone']) {
                            'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200',
                            'primary' => 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-200',
                            'info' => 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-200',
                            default => 'bg-amber-50 text-amber-700 dark:bg-amber-950/40 dark:text-amber-200',
                        };
                    @endphp
                    <details class="group px-4 py-3 sm:px-5">
                        <summary class="flex cursor-pointer list-none items-start gap-3">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $row['show']]) }}" class="truncate text-sm font-semibold text-gray-950 hover:text-primary-600 dark:text-white">{{ $row['show']->title }}</a>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $tone }}">{{ $row['status'] }}</span>
                                </div>
                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-[10px] text-gray-500">
                                    <span>{{ $row['show']->show_date?->format('M j') }}</span>
                                    @if($row['streamers'])<span>{{ $row['streamers'] }}</span>@endif
                                    <span>{{ number_format($row['margin_pct'], 1) }}% margin</span>
                                </div>
                            </div>
                            <div class="grid shrink-0 grid-cols-2 gap-x-4 text-right sm:grid-cols-4">
                                <div class="hidden sm:block"><div class="text-[9px] uppercase text-gray-400">Sales</div><div class="text-xs font-semibold">${{ number_format($row['sales'], 2) }}</div></div>
                                <div class="hidden sm:block"><div class="text-[9px] uppercase text-gray-400">COGS</div><div class="text-xs font-semibold">${{ number_format($row['cogs'], 2) }}</div></div>
                                <div><div class="text-[9px] uppercase text-gray-400">Payroll</div><div class="text-xs font-semibold">${{ number_format($row['payroll'], 2) }}</div></div>
                                <div><div class="text-[9px] uppercase text-gray-400">Show Net</div><div class="text-xs font-bold {{ $row['show_net'] >= 0 ? 'text-emerald-600' : 'text-rose-600' }}">${{ number_format($row['show_net'], 2) }}</div></div>
                            </div>
                            <x-heroicon-m-chevron-down class="mt-1 h-4 w-4 shrink-0 text-gray-400 transition group-open:rotate-180" />
                        </summary>

                        <div class="mt-3 grid gap-2 rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60 sm:grid-cols-3">
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Show Results</div>
                                <dl class="mt-1 space-y-1 text-xs"><div class="flex justify-between"><dt>Gross sales</dt><dd>${{ number_format($row['sales'],2) }}</dd></div><div class="flex justify-between"><dt>Net + tips</dt><dd>${{ number_format($row['net'],2) }}</dd></div><div class="flex justify-between"><dt>COGS</dt><dd>-${{ number_format($row['cogs'],2) }}</dd></div></dl>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Payroll</div>
                                <dl class="mt-1 space-y-1 text-xs"><div class="flex justify-between"><dt>Team compensation</dt><dd>${{ number_format($row['payroll'],2) }}</dd></div><div class="flex justify-between"><dt>Gross profit before payroll</dt><dd>${{ number_format($row['gross_profit'],2) }}</dd></div></dl>
                            </div>
                            <div>
                                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Final</div>
                                <dl class="mt-1 space-y-1 text-xs"><div class="flex justify-between"><dt>Show net</dt><dd class="font-bold">${{ number_format($row['show_net'],2) }}</dd></div><div class="flex justify-between"><dt>Margin</dt><dd>{{ number_format($row['margin_pct'],1) }}%</dd></div><div class="flex justify-between"><dt>Payroll status</dt><dd>{{ $row['status'] }}</dd></div></dl>
                            </div>
                        </div>
                    </details>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-gray-500">No shows found for this pay period.</div>
                @endforelse
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Recent Pay Runs</h3>
                <div class="mt-3 space-y-2">
                    @forelse($recent as $run)
                        <a href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('view', ['record' => $run]) }}" class="flex items-center justify-between gap-3 rounded-xl border border-gray-100 px-3 py-2.5 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50">
                            <div><div class="text-xs font-semibold">{{ $run->week_start->format('M j') }} – {{ $run->week_end->format('M j, Y') }}</div><div class="text-[10px] text-gray-500">{{ $run->payouts_count }} entries · {{ \App\Models\WeeklyPayoutBatch::statusLabels()[$run->status] ?? ucfirst($run->status) }}</div></div>
                            <div class="text-sm font-bold">${{ number_format((float)$run->total_payout,2) }}</div>
                        </a>
                    @empty
                        <div class="text-xs text-gray-500">No pay-run history yet.</div>
                    @endforelse
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-sm font-bold text-gray-950 dark:text-white">Payroll Tools</h3>
                <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <a class="rounded-xl border border-gray-100 p-3 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50" href="{{ \App\Filament\Resources\WeeklyPayoutBatchResource::getUrl('index') }}"><div class="text-xs font-bold">Pay Runs</div><div class="mt-1 text-[10px] text-gray-500">Weekly history</div></a>
                    <a class="rounded-xl border border-gray-100 p-3 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50" href="{{ \App\Filament\Resources\StreamerResource::getUrl('index') }}"><div class="text-xs font-bold">People & Rates</div><div class="mt-1 text-[10px] text-gray-500">Compensation</div></a>
                    <a class="rounded-xl border border-gray-100 p-3 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-800/50" href="{{ \App\Filament\Resources\PayoutResource::getUrl('index') }}"><div class="text-xs font-bold">Payout Entries</div><div class="mt-1 text-[10px] text-gray-500">Raw detail</div></a>
                </div>
            </section>
        </div>
    </div>
</x-filament-panels::page>
