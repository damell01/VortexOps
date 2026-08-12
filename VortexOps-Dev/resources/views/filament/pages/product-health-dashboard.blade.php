<x-filament-panels::page>
@php $m = $this->metrics; @endphp
<div class="space-y-6">

{{-- ── Top KPIs ──────────────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($m['total_products']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Total Products</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $m['active_products'] }} active</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ number_format($m['total_aliases']) }}</p>
        <p class="text-xs text-gray-400 mt-1">Total Aliases</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $m['with_aliases'] }} products covered</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold {{ $m['avg_confidence'] >= 90 ? 'text-green-600 dark:text-green-400' : ($m['avg_confidence'] >= 75 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500') }}">
            {{ $m['avg_confidence'] > 0 ? $m['avg_confidence'] . '%' : '—' }}
        </p>
        <p class="text-xs text-gray-400 mt-1">Avg Match Confidence</p>
        <p class="text-xs text-gray-500 mt-0.5">{{ $m['sessions_done'] }} sessions complete</p>
    </div>
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 text-center">
        <p class="text-3xl font-bold {{ $m['auto_match_rate'] >= 90 ? 'text-green-600 dark:text-green-400' : ($m['auto_match_rate'] >= 70 ? 'text-amber-600 dark:text-amber-400' : 'text-red-500') }}">
            {{ $m['auto_match_rate'] > 0 ? $m['auto_match_rate'] . '%' : '—' }}
        </p>
        <p class="text-xs text-gray-400 mt-1">Auto-Match Rate</p>
        <p class="text-xs text-gray-500 mt-0.5">across all sessions</p>
    </div>
</div>

{{-- ── Health Indicators ─────────────────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

    {{-- Missing UPC --}}
    <div class="rounded-xl border {{ $m['missing_upc'] === 0 ? 'border-green-200 dark:border-green-800' : 'border-amber-200 dark:border-amber-800' }} bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 flex items-center justify-between {{ $m['missing_upc'] === 0 ? 'bg-green-50 dark:bg-green-950' : 'bg-amber-50 dark:bg-amber-950' }}">
            <h3 class="text-sm font-semibold {{ $m['missing_upc'] === 0 ? 'text-green-900 dark:text-green-100' : 'text-amber-900 dark:text-amber-100' }}">
                Missing UPC
            </h3>
            <span class="text-xl font-bold {{ $m['missing_upc'] === 0 ? 'text-green-600 dark:text-green-400' : 'text-amber-600 dark:text-amber-400' }}">
                {{ $m['missing_upc'] }}
            </span>
        </div>
        @if(count($this->missingUpcProducts) > 0)
        <div class="divide-y divide-gray-100 dark:divide-gray-800 max-h-48 overflow-y-auto">
            @foreach($this->missingUpcProducts as $p)
            <div class="px-5 py-2 flex items-center justify-between text-xs">
                <div>
                    <p class="text-gray-900 dark:text-gray-100 truncate max-w-[160px]">{{ $p['name'] }}</p>
                    <p class="text-gray-400">{{ $p['label'] }}</p>
                </div>
                <a href="/admin/inventory-items/{{ $p['id'] }}/edit"
                    class="text-blue-500 hover:underline">Edit</a>
            </div>
            @endforeach
        </div>
        @elseif($m['missing_upc'] === 0)
            <p class="px-5 py-4 text-xs text-green-600 dark:text-green-400">✅ All active products have a UPC</p>
        @endif
    </div>

    {{-- Never received --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 flex items-center justify-between bg-gray-50 dark:bg-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Never Received</h3>
            <span class="text-xl font-bold text-gray-500">{{ $m['never_received'] }}</span>
        </div>
        <p class="px-5 py-3 text-xs text-gray-400">Products in catalog that have no receiving history. May be legacy items or added manually.</p>
    </div>

    {{-- UPC coverage bar --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">UPC Coverage</h3>
        <div class="h-4 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
            <div class="h-full rounded-full bg-blue-500 transition-all duration-500" style="width: {{ $m['with_upc_pct'] }}%"></div>
        </div>
        <p class="text-xs text-gray-400 mt-1 text-right">{{ $m['with_upc_pct'] }}% of products have a UPC</p>
        <p class="mt-3 text-xs text-gray-500">UPC enables instant alias lookup (Stage 1) — every missing UPC means the AI has to work harder during receiving.</p>
    </div>
</div>

{{-- ── Most Aliased & Most Received ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Most Aliases (best matched)</h3>
        </div>
        @if(count($this->mostAliased) === 0)
            <p class="px-5 py-8 text-center text-xs text-gray-400">No aliases yet — they build during receiving.</p>
        @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->mostAliased as $row)
            <div class="flex items-center justify-between px-5 py-2.5 text-sm">
                <p class="text-gray-900 dark:text-gray-100 truncate">{{ $row['product'] }}</p>
                <div class="flex items-center gap-4 flex-shrink-0 ml-3">
                    <span class="text-xs text-gray-400">{{ $row['alias_count'] }} aliases</span>
                    <span class="text-xs font-medium text-purple-600 dark:text-purple-400">{{ $row['total_confirms'] }} confirms</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Most Received</h3>
        </div>
        @if(count($this->mostReceived) === 0)
            <p class="px-5 py-8 text-center text-xs text-gray-400">No receiving history yet.</p>
        @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->mostReceived as $row)
            <div class="flex items-center justify-between px-5 py-2.5 text-sm">
                <p class="text-gray-900 dark:text-gray-100 truncate">{{ $row['name'] }}</p>
                <div class="flex items-center gap-4 flex-shrink-0 ml-3">
                    <span class="text-xs font-bold text-gray-900 dark:text-gray-100">{{ $row['received'] }}</span>
                    <span class="text-xs text-gray-400">${{ $row['avg_cost'] }}</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- ── Low Confidence Products ───────────────────────────────────────────────── --}}
@if(count($this->lowConfidenceProducts) > 0)
<div class="rounded-xl border border-red-200 dark:border-red-800 bg-white dark:bg-gray-900 overflow-hidden">
    <div class="px-5 py-3 border-b border-red-100 dark:border-red-900 bg-red-50 dark:bg-red-950">
        <h3 class="text-sm font-semibold text-red-900 dark:text-red-100">Low Confidence Products</h3>
        <p class="text-xs text-red-600 dark:text-red-400 mt-0.5">These products are being misidentified — add more aliases or UPCs</p>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach($this->lowConfidenceProducts as $row)
        <div class="flex items-center justify-between px-5 py-2.5 text-sm">
            <p class="text-gray-900 dark:text-gray-100 truncate">{{ $row['product'] }}</p>
            <div class="flex items-center gap-4 flex-shrink-0 ml-3">
                <span class="inline-flex items-center rounded-full bg-red-100 dark:bg-red-900 px-2 py-0.5 text-xs font-medium text-red-700 dark:text-red-300">
                    {{ $row['confidence'] }}%
                </span>
                <span class="text-xs text-gray-400">{{ $row['aliases'] }} aliases</span>
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

</div>
</x-filament-panels::page>
