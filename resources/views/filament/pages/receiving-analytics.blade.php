<x-filament-panels::page>
@php $s = $this->summary; @endphp
<div class="space-y-6">

{{-- ── Summary KPIs ──────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-3 gap-4">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $s['sessions'] }}</p>
        <p class="text-xs text-gray-400 mt-1">Sessions Completed</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $s['total_lines'] }}</p>
        <p class="text-xs text-gray-400 mt-1">Lines Processed</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold {{ (float)$s['auto_match_rate'] >= 90 ? 'text-green-600 dark:text-green-400' : ((float)$s['auto_match_rate'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500') }}">
            {{ $s['auto_match_rate'] > 0 ? $s['auto_match_rate'] . '%' : '—' }}
        </p>
        <p class="text-xs text-gray-400 mt-1">Auto-Match Rate</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($s['total_aliases']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Aliases Learned</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $s['confirmed'] }} confirmed</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center col-span-2 sm:col-span-2">
        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">${{ $s['receiving_cost'] }}</p>
        <p class="text-xs text-gray-400 mt-1">Total Receiving Cost</p>
        <p class="text-xs text-gray-500 mt-0.5">all received lots</p>
    </div>
</div>

{{-- ── Sessions by Month ─────────────────────────────────────────────────────── --}}
@if(count($this->sessionsByMonth) > 0)
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Sessions by Month</h3>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Month</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500">Sessions</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500">Lines</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500">Auto-Match %</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Quality</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->sessionsByMonth as $row)
            <tr>
                <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $row['month'] }}</td>
                <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ $row['sessions'] }}</td>
                <td class="px-5 py-3 text-right text-gray-700 dark:text-gray-300">{{ number_format($row['lines']) }}</td>
                <td class="px-5 py-3 text-right font-bold {{ $row['auto_pct'] >= 90 ? 'text-green-600 dark:text-green-400' : ($row['auto_pct'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500') }}">
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
            <p class="text-xs text-gray-400 mt-0.5">How lines are being matched</p>
        </div>
        @php
            $stages = $this->matchStageBreakdown;
            $totalMatched = array_sum(array_column($stages, 'count'));
        @endphp
        @if(count($stages) === 0)
            <p class="px-5 py-8 text-center text-xs text-gray-400">No match data yet.</p>
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
        <div class="px-5 py-3 bg-gray-50 dark:bg-gray-800 text-xs text-gray-400 space-y-0.5">
            <p>🟢 Alias = instant match (0ms)</p>
            <p>🟡 Fuzzy = token scoring</p>
            <p>🔵 Embedding = Ollama AI</p>
            <p>🟣 Human = manually confirmed</p>
        </div>
        @endif
    </div>

    {{-- Top vendors --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Top Vendors</h3>
            <p class="text-xs text-gray-400 mt-0.5">By number of receiving sessions</p>
        </div>
        @if(count($this->topVendors) === 0)
            <p class="px-5 py-8 text-center text-xs text-gray-400">No vendor data yet.</p>
        @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->topVendors as $v)
            <div class="flex items-center justify-between px-5 py-2.5 text-sm">
                <div>
                    <p class="font-medium text-gray-900 dark:text-gray-100">{{ $v['vendor'] }}</p>
                    <p class="text-xs text-gray-400">{{ $v['sessions'] }} sessions · {{ number_format($v['lines']) }} lines</p>
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
        <p class="text-xs text-gray-400 mt-0.5">Growing knowledge base — more aliases = faster matching</p>
    </div>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Month</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500">Aliases Added</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Growth</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @php $maxAliases = max(array_column($this->aliasesLearnedByMonth, 'aliases') ?: [1]); @endphp
            @foreach($this->aliasesLearnedByMonth as $row)
            <tr>
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $row['month'] }}</td>
                <td class="px-5 py-3 text-right font-bold text-purple-600 dark:text-purple-400">{{ $row['aliases'] }}</td>
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
</x-filament-panels::page>
