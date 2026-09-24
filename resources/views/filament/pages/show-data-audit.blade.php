@php
    $d = $this->getAuditData();
    $labels = ['gross_revenue'=>'Gross Revenue','whatnot_net'=>'Estimated Net','completed_earnings'=>'Completed Earnings','show_duration'=>'Stream Duration'];
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
            ['Fully Covered',number_format($d['complete']),'gross + net + duration'],
        ] as $card)
        <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $card[0] }}</div>
            <div class="mt-2 text-xl font-bold text-gray-950 dark:text-white">{{ $card[1] }}</div>
            <div class="mt-1 text-[11px] text-gray-500">{{ $card[2] }}</div>
        </div>
        @endforeach
    </section>

    <section class="rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 p-4 dark:border-gray-800">
            <h2 class="font-semibold text-gray-950 dark:text-white">Analytics Coverage</h2>
            <p class="mt-1 text-xs text-gray-500">This is the confidence check for the totals above. Missing values are not silently presented as complete data.</p>
        </div>
        <div class="grid gap-px bg-gray-100 sm:grid-cols-2 xl:grid-cols-4 dark:bg-gray-800">
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

    <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex items-center justify-between border-b border-gray-100 p-4 dark:border-gray-800">
            <div><h2 class="font-semibold text-gray-950 dark:text-white">Shows Missing Data</h2><p class="mt-1 text-xs text-gray-500">Past shows only. Use this list to spot failed refreshes or shows that may not have actually happened.</p></div>
            <span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700">{{ $d['missing']->count() }} shown</span>
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
                @endphp
                <a href="{{ $this->showUrl($show->id) }}" class="grid gap-3 p-4 transition hover:bg-gray-50 sm:grid-cols-[100px_minmax(0,1fr)_auto] dark:hover:bg-gray-800/60">
                    <div class="text-xs font-semibold text-gray-500">{{ $show->show_date?->format('M j, Y') }}</div>
                    <div class="min-w-0"><div class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $show->title }}</div><div class="mt-1 text-[11px] text-gray-500">{{ $show->channel?->name ?: 'No channel' }}</div></div>
                    <div class="flex flex-wrap items-center justify-end gap-1">@foreach($miss as $m)<span class="rounded-full bg-amber-50 px-2 py-1 text-[10px] font-bold text-amber-700 dark:bg-amber-500/10 dark:text-amber-300">{{ $m }} missing</span>@endforeach <span class="ml-2 text-xs font-semibold text-primary-600">Open →</span></div>
                </a>
            @empty
                <div class="p-8 text-center text-sm text-gray-500">No past shows are missing these core analytics in this period.</div>
            @endforelse
        </div>
    </section>
</div>
</x-filament-panels::page>
