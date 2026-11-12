<x-filament-panels::page>
<x-reports.shell>
@php $s = $this->summary; @endphp
<div class="vxr">

<div class="vxr-head" style="justify-content:flex-end"><x-report-toolbar :exports="['exportCsv' => 'Export top vendors CSV']" /></div>

@php $rate = (float) $s['auto_match_rate']; $months = array_reverse($this->sessionsByMonth); @endphp
<div class="vxr-stats" style="grid-template-columns:repeat(5,minmax(0,1fr))">
    <x-vxr.stat label="Sessions completed" :value="number_format($s['sessions'])" icon="heroicon-o-inbox-arrow-down" :spark="count($months) > 1 ? array_column($months, 'sessions') : null" />
    <x-vxr.stat label="Lines processed" :value="number_format($s['total_lines'])" icon="heroicon-o-queue-list" :spark="count($months) > 1 ? array_column($months, 'lines') : null" />
    <x-vxr.stat label="Auto-match rate" :value="$rate > 0 ? $s['auto_match_rate'].'%' : '—'" icon="heroicon-o-sparkles" featured :note="$rate >= 90 ? 'Great: almost everything matches itself' : ($rate >= 70 ? 'Good, room to improve' : ($rate > 0 ? 'Low: teach it more aliases' : 'No sessions yet'))" />
    <x-vxr.stat label="Aliases learned" :value="number_format($s['total_aliases'])" icon="heroicon-o-academic-cap" :note="$s['confirmed'].' confirmed'" />
    <x-vxr.stat label="Total receiving cost" :value="'$'.$s['receiving_cost']" icon="heroicon-o-banknotes" note="All received lots" />
</div>

@if (count($months) > 0)
<section class="vxr-card">
    <div class="vxr-card-head"><div><h2 class="vxr-card-title"><x-filament::icon icon="heroicon-o-chart-bar" /> Receiving by month</h2><p class="vxr-card-sub">Sessions and lines received each month.</p></div>
        <div class="vxr-legend"><span><i style="height:8px;width:8px;border-radius:2px;background:var(--vxw-accent)"></i>Lines</span><span><i style="height:8px;width:8px;border-radius:2px;background:var(--vxc-3)"></i>Sessions</span></div></div>
    <div class="vxr-card-body">
        <x-vxr.grouped :money="false" :height="220" :labels="array_column($months, 'month')" aria-label="Receiving by month"
            :series="[['name' => 'Lines', 'values' => array_column($months, 'lines'), 'color' => 'var(--vxw-accent)'], ['name' => 'Sessions', 'values' => array_column($months, 'sessions'), 'color' => 'var(--vxc-3)']]" />
    </div>
</section>
@endif

{{-- ── Sessions by Month ─────────────────────────────────────────────────────── --}}
@if(count($this->sessionsByMonth) > 0)
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Sessions by Month</h3>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Month</th>
                <th class="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Sessions</th>
                <th class="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Lines</th>
                <th class="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Auto-Match %</th>
                <th class="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Quality</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->sessionsByMonth as $i => $row)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100 whitespace-nowrap">{{ $row['month'] }}</td>
                <td class="px-5 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ $row['sessions'] }}</td>
                <td class="px-5 py-3 text-right tabular-nums text-gray-700 dark:text-gray-300">{{ number_format($row['lines']) }}</td>
                <td class="px-5 py-3 text-right tabular-nums font-bold {{ $row['auto_pct'] >= 90 ? 'text-green-600 dark:text-green-400' : ($row['auto_pct'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500') }}">
                    {{ $row['auto_pct'] }}%
                </td>
                <td class="px-5 py-3">
                    <div class="w-24 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full rounded-full {{ $row['auto_pct'] >= 90 ? 'bg-green-500' : ($row['auto_pct'] >= 70 ? 'bg-amber-500' : 'bg-red-500') }}" style="width: {{ $row['auto_pct'] }}%"></div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

{{-- ── Match Stage Breakdown & Top Vendors ──────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    {{-- Match stage --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Match Stage Breakdown</h3>
            <p class="text-xs text-gray-500 mt-0.5">How lines are being matched</p>
        </div>
        @php
            $stages = $this->matchStageBreakdown;
            $totalMatched = array_sum(array_column($stages, 'count'));
        @endphp
        @if(count($stages) === 0)
            <p class="px-5 py-5 text-center text-xs text-gray-500">No match data yet.</p>
        @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($stages as $stage)
            @php $pct = $totalMatched > 0 ? round($stage['count'] / $totalMatched * 100) : 0; @endphp
            <div class="px-5 py-3">
                <div class="flex items-center justify-between text-sm mb-1.5">
                    <span class="font-medium text-gray-900 dark:text-gray-100 capitalize">{{ $stage['stage'] }}</span>
                    <span class="text-gray-500">{{ number_format($stage['count']) }} ({{ $pct }}%)</span>
                </div>
                <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                    <div class="h-full rounded-full
                        {{ $stage['stage'] === 'alias' ? 'bg-green-500' :
                           ($stage['stage'] === 'fuzzy' ? 'bg-amber-500' :
                           ($stage['stage'] === 'embedding' ? 'bg-blue-500' :
                           ($stage['stage'] === 'human' ? 'bg-purple-500' : 'bg-gray-400'))) }}"
                        style="width: {{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800 text-xs text-gray-500 space-y-0.5">
            <p>🟢 Alias = instant match (0ms)</p>
            <p>🟡 Fuzzy = token scoring</p>
            <p>🔵 Embedding = Ollama AI</p>
            <p>🟣 Human = manually confirmed</p>
        </div>
        @endif
    </div>

    {{-- Top vendors --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Top Vendors</h3>
                <p class="text-xs text-gray-500 mt-0.5">By number of receiving sessions</p>
            </div>
        </div>
        @if(count($this->topVendors) === 0)
            <p class="px-5 py-5 text-center text-xs text-gray-500">No vendor data yet.</p>
        @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->topVendors as $v)
            <div class="flex items-center justify-between px-5 py-2.5 text-sm">
                <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $v['vendor'] }}</p>
                    <p class="text-xs text-gray-500">{{ $v['sessions'] }} sessions · {{ number_format($v['lines']) }} lines</p>
                </div>
                <span class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-bold ml-3
                    {{ $v['auto_pct'] >= 90 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
                       ($v['auto_pct'] >= 70 ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' :
                       'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300') }}">
                    {{ $v['auto_pct'] }}%
                </span>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ── Aliases Learned by Month ──────────────────────────────────────────────── --}}
@if(count($this->aliasesLearnedByMonth) > 0)
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Aliases Learned by Month</h3>
        <p class="text-xs text-gray-500 mt-0.5">Growing knowledge base — more aliases = faster matching</p>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr>
                <th class="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Month</th>
                <th class="px-5 py-2.5 text-right text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Aliases Added</th>
                <th class="px-5 py-2.5 text-left text-[11px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-500">Growth</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @php $maxAliases = max(array_column($this->aliasesLearnedByMonth, 'aliases') ?: [1]); @endphp
            @foreach($this->aliasesLearnedByMonth as $i => $row)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['month'] }}</td>
                <td class="px-5 py-3 text-right tabular-nums font-bold text-purple-600 dark:text-purple-400">{{ $row['aliases'] }}</td>
                <td class="px-5 py-3">
                    <div class="w-32 h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                        <div class="h-full rounded-full bg-purple-500" style="width: {{ $maxAliases > 0 ? round($row['aliases'] / $maxAliases * 100) : 0 }}%"></div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
</div>
@endif

</div>
</x-reports.shell>
</x-filament-panels::page>
