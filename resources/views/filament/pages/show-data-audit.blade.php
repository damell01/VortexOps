@php
    $d = $this->getAuditData();
    $labels = ['gross_revenue'=>'Gross Revenue','whatnot_net'=>'Estimated Net','show_duration'=>'Stream Duration'];
    $statusMeta = [
        'partial' => ['label' => 'Partial', 'desc' => 'has some analytics, not all', 'pill' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        'unavailable' => ['label' => 'Needs source review', 'desc' => 'older automated lookup did not return analytics; CSV/import evidence may supersede this', 'pill' => 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300'],
        'unclassified' => ['label' => 'Not checked yet', 'desc' => 'due for a first scrape attempt', 'pill' => 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-300'],
    ];
@endphp
<x-filament-panels::page>
<div class="space-y-5">
    <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <label class="text-xs font-semibold text-gray-500">Period</label>
                <select wire:model.live="datePreset" class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-800">
                    <option value="this_month">This month</option><option value="last_month">Last month</option>
                    <option value="last_30">Last 30 days</option><option value="last_90">Last 90 days</option><option value="custom">Custom</option>
                </select>
            </div>
            <div><label class="text-xs font-semibold text-gray-500">From</label><input type="date" wire:model.live="dateFrom" class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-800"></div>
            <div><label class="text-xs font-semibold text-gray-500">To</label><input type="date" wire:model.live="dateTo" class="mt-1 rounded-lg border-gray-300 text-sm dark:bg-gray-800"></div>
            <div class="ml-auto text-xs text-gray-500">{{ $d['from']->format('M j, Y') }} – {{ $d['to']->format('M j, Y') }}</div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
        @foreach([
            ['Shows',number_format($d['total']),'in selected period'],
            ['Whatnot Gross','$'.number_format($d['gross'],2),'gross revenue'],
            ['Estimated Net','$'.number_format($d['estimatedNet'],2),'Whatnot estimated earnings'],
            ['Completed Earnings','$'.number_format($d['completed'],2),'settled earnings captured'],
            ['Stream Hours',number_format($d['hours'],1),'from imported duration'],
            ['Analytics Complete',number_format($d['complete']),'verified core analytics'],
        ] as $card)
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $card[0] }}</div>
            <div class="mt-2 text-xl font-bold text-gray-950 dark:text-white">{{ $card[1] }}</div>
            <div class="mt-1 text-[11px] text-gray-500">{{ $card[2] }}</div>
        </div>
        @endforeach
    </section>

    <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach([
            ['Complete',$d['statusCounts']['complete'],'Analytics imported with core metrics','text-emerald-600'],
            ['Partial',$d['statusCounts']['partial'],'Analytics reached; some metrics pending','text-amber-600'],
            ['Needs Review',$d['statusCounts']['unavailable'],'No verified analytics source yet; retry/import may resolve it','text-gray-500'],
            ['Not Checked',$d['statusCounts']['unclassified'],'Due for first classification','text-primary-600'],
        ] as $status)
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $status[0] }}</div>
            <div class="mt-2 text-xl font-bold {{ $status[3] }}">{{ number_format($status[1]) }}</div>
            <div class="mt-1 text-[11px] text-gray-500">{{ $status[2] }}</div>
        </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 p-4 dark:border-gray-800">
            <h2 class="font-semibold text-gray-950 dark:text-white">Analytics Coverage</h2>
            <p class="mt-1 text-xs text-gray-500">This is the confidence check for the totals above. Coverage counts only verified imported/synced values; database defaults alone do not count as captured.</p>
        </div>
        <div class="grid gap-px bg-gray-100 sm:grid-cols-3 dark:bg-gray-800">
            @foreach($d['coverage'] as $key=>$c)
                @php $pct=$d['total'] ? round($c['present']/$d['total']*100,1) : 0; @endphp
                <div class="bg-white p-4 dark:bg-gray-900">
                    <div class="flex justify-between gap-2"><strong class="text-sm">{{ $labels[$key] }}</strong><span class="text-xs font-bold {{ $c['missing'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $pct }}%</span></div>
                    <div class="mt-3 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-primary-500" style="width:{{ $pct }}%"></div></div>
                    <div class="mt-2 flex justify-between text-[11px] text-gray-500"><span>{{ $c['present'] }} captured</span><span class="{{ $c['missing'] ? 'font-semibold text-amber-600' : '' }}">{{ $c['missing'] }} missing</span></div>
                </div>
            @endforeach
        </div>
    </section>

    @php
        $settled = $d['settlementCoverage'];
        $settledPct = $d['total'] ? round($settled['present'] / $d['total'] * 100, 1) : 0;
    @endphp
    <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Settlement Coverage</div>
                <div class="mt-1 text-lg font-bold text-gray-950 dark:text-white">Completed Earnings</div>
                <p class="mt-1 text-xs text-gray-500">Final settled earnings are tracked separately and do not determine analytics completeness.</p>
            </div>
            <div class="min-w-[260px] flex-1 sm:max-w-md">
                <div class="flex justify-between text-xs"><span>{{ number_format($settled['present']) }} captured</span><strong class="{{ $settled['missing'] ? 'text-amber-600' : 'text-emerald-600' }}">{{ $settledPct }}%</strong></div>
                <div class="mt-2 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full bg-primary-500" style="width:{{ $settledPct }}%"></div></div>
                <div class="mt-1 text-right text-[11px] text-gray-500">{{ number_format($settled['missing']) }} awaiting settlement</div>
            </div>
        </div>
    </section>

    <section class="grid gap-3 sm:grid-cols-2">
        <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-500/20 dark:bg-amber-500/5">
            <div class="text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Needs Match</div>
            <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($d['needsMatch']) }}</div>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Missing a recoverable Whatnot UUID. Identity must be recovered before analytics can be targeted.</p>
        </div>
        <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4 dark:border-blue-500/20 dark:bg-blue-500/5">
            <div class="text-[10px] font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Needs Analytics</div>
            <div class="mt-2 text-2xl font-bold text-gray-950 dark:text-white">{{ number_format($d['needsAnalytics']) }}</div>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Has a Whatnot UUID and can be sent directly through the targeted analytics recovery.</p>
        </div>
    </section>

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 p-4 dark:border-gray-800">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div><h2 class="font-semibold text-gray-950 dark:text-white">Analytics Follow-up</h2><p class="mt-1 text-xs text-gray-500">Past shows only. Filter by why a show is here — verified CSV/import data counts as complete; unresolved rows still need a source check.</p></div>
                <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ number_format($d['followUpTotal']) }} match{{ $d['followUpTotal'] === 1 ? '' : 'es' }}</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach([
                    'all' => 'All ('.number_format(array_sum($d['statusCounts']) - $d['statusCounts']['complete']).')',
                    'partial' => 'Partial ('.number_format($d['statusCounts']['partial']).')',
                    'unclassified' => 'Not checked yet ('.number_format($d['statusCounts']['unclassified']).')',
                    'unavailable' => 'Needs source review ('.number_format($d['statusCounts']['unavailable']).')',
                ] as $key => $label)
                    <button wire:click="setStatusFilter('{{ $key }}')" class="rounded-full px-3 py-1.5 text-xs font-semibold transition {{ $this->statusFilter === $key ? 'bg-primary-600 text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @forelse($d['missing'] as $show)
                @php
                    $miss=collect([
                        'Gross'=>$show->gross_revenue,
                        'Est. Net'=>$show->whatnot_net,
                        'Completed'=>$show->completed_earnings,
                        'Duration'=>$show->show_duration,
                    ])->filter(fn($v)=>$v===null)->keys();
                    $meta = $statusMeta[$show->analyticsCoverageStatus()] ?? ['label' => ucfirst($show->analyticsCoverageStatus()), 'pill' => 'bg-gray-100 text-gray-600'];
                @endphp
                <a href="{{ $this->showUrl($show->id) }}" class="grid gap-3 p-4 transition hover:bg-gray-50 sm:grid-cols-[100px_minmax(0,1fr)_auto] dark:hover:bg-gray-800/60">
                    <div class="text-xs font-semibold text-gray-500">{{ $show->show_date?->format('M j, Y') }}</div>
                    <div class="min-w-0">
                        <div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $show->title }}</div>
                        <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] text-gray-500">
                            <span>{{ $show->channel?->name ?: 'No channel' }}</span>
                            <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $meta['pill'] }}">{{ $meta['label'] }}</span>
                            @if($show->analytics_sync_note) <span>· {{ $show->analytics_sync_note }}</span>@endif
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-1">@foreach($miss as $m)<span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $m }} missing</span>@endforeach <span class="ml-2 text-xs font-semibold text-primary-600">Open →</span></div>
                </a>
            @empty
                <div class="p-8 text-center text-sm text-gray-500">No past shows match this filter in this period.</div>
            @endforelse
        </div>
        @if($d['followUpPages'] > 1)
            <div class="flex items-center justify-between border-t border-gray-100 p-3 dark:border-gray-800">
                <button wire:click="goToFollowUpPage({{ $d['followUpPage'] - 1 }})" @disabled($d['followUpPage'] <= 1) class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300">← Previous</button>
                <span class="text-xs text-gray-500">Page {{ $d['followUpPage'] }} of {{ $d['followUpPages'] }}</span>
                <button wire:click="goToFollowUpPage({{ $d['followUpPage'] + 1 }})" @disabled($d['followUpPage'] >= $d['followUpPages']) class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 disabled:cursor-not-allowed disabled:opacity-40 dark:border-gray-700 dark:text-gray-300">Next →</button>
            </div>
        @endif
    </section>
</div>
</x-filament-panels::page>
