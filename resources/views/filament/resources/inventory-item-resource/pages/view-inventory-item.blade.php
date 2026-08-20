<x-filament-panels::page>
@php
    $tabs = [
        'overview'   => 'Overview',
        'stock'      => 'Stock by Location',
        'lots'       => 'Lots',
        'receiving'  => 'Receiving History',
        'movements'  => 'Movements',
        'cost'       => 'Cost History',
        'analysis'   => 'Cost Analysis',
        'aliases'    => 'Aliases & AI',
    ];
@endphp

<div class="space-y-6">

{{-- ── Product Hero ──────────────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div class="flex items-start gap-4 min-w-0">
            {{-- The photo, or the brand mark standing in for one. Dimmed and
                 contained when it is the fallback, so a page showing the logo
                 does not read as a product that looks like the logo. --}}
            @php $vxHasPhoto = $record->hasImage(); @endphp
            <img
                src="{{ $record->imageUrl() }}"
                alt="{{ $vxHasPhoto ? $record->name : 'No photo yet' }}"
                class="h-20 w-20 shrink-0 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800
                       {{ $vxHasPhoto ? 'object-cover' : 'object-contain p-2 opacity-60' }}"
            />
        <div class="min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ $record->name }}</h2>
                <span class="{{ $record->is_active ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }} inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium">
                    {{ $record->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <p class="text-sm text-gray-400 mt-1">{{ $record->cardLabel() }}</p>
            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500 dark:text-gray-400">
                @if($record->sku)<span>SKU: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $record->sku }}</span></span>@endif
                @if($record->upc)<span>UPC: <span class="font-mono text-gray-700 dark:text-gray-300">{{ $record->upc }}</span></span>@endif
                @if($record->brand)<span>Brand: {{ $record->brand }}</span>@endif
                @if($record->sport)<span>Sport: {{ $record->sport }}</span>@endif
                @if($record->year)<span>Year: {{ $record->year }}</span>@endif
                @if($record->product_type)<span>Type: {{ $record->product_type }}</span>@endif
            </div>
        </div>
        </div>
        <div class="flex-shrink-0 text-right space-y-1">
            @php $totalQty = collect($this->stockByLocation)->sum('qty'); @endphp
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalQty) }}</p>
            <p class="text-xs text-gray-400">units in stock</p>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">${{ number_format((float)$record->average_cost, 2) }} avg cost</p>
        </div>
    </div>
</div>

{{-- ── Tab Bar ───────────────────────────────────────────────────────────────── --}}
<div class="flex gap-2.5 overflow-x-auto border-b border-gray-200 dark:border-gray-700 pb-px scrollbar-none -mx-1 px-1">
    @foreach($tabs as $key => $label)
    <button wire:click="setTab('{{ $key }}')" type="button"
        class="flex-shrink-0 px-3.5 py-2 text-sm font-medium rounded-t transition-colors whitespace-nowrap
            {{ $tab === $key
                ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 border border-b-white dark:border-b-gray-900 border-gray-200 dark:border-gray-700 shadow-sm'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800/50' }}">
        {{ $label }}
    </button>
    @endforeach
</div>

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: OVERVIEW                                                               --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'overview')
<div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
    {{-- Key metrics --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Inventory</h3>
        @php $lots = $this->lots; @endphp
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Total Stock</span>
                <span class="font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalQty) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Active Lots</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ collect($lots)->where('status', 'active')->count() }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Total Received</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ number_format((float)$record->total_units_received) }}</span>
            </div>
            @if($record->reorder_level)
            <div class="flex justify-between {{ $totalQty <= $record->reorder_level ? 'text-red-500' : '' }}">
                <span>Reorder At</span>
                <span class="font-medium">{{ $record->reorder_level }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Pricing</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">List Cost</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">${{ number_format((float)$record->unit_cost, 2) }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Average Cost (WAC)</span>
                <span class="font-bold text-gray-900 dark:text-gray-100">${{ number_format((float)$record->average_cost, 2) }}</span>
            </div>
            @php $activeLots = collect($lots)->where('status', 'active'); @endphp
            @if($activeLots->count() > 0)
            <div class="flex justify-between">
                <span class="text-gray-500">Inventory Value</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">${{ number_format($activeLots->sum(fn($l) => $l['remaining'] * (float)str_replace(',','',$l['unit_cost'])), 2) }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5 space-y-4">
        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Details</h3>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Category</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->category ?? '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Configuration</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->configuration ?? '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Set Name</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->set_name ?? '—' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Preferred Vendor</span>
                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $record->preferredVendor?->name ?? '—' }}</span>
            </div>
        </div>
    </div>
</div>

{{-- Stock summary by location --}}
@if(count($this->stockByLocation) > 0)
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Current Stock</h3>
    </div>
    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @foreach($this->stockByLocation as $s)
        <div class="flex items-center justify-between px-5 py-3">
            <div>
                <p class="text-sm text-gray-900 dark:text-gray-100">{{ $s['location'] }}</p>
                <p class="text-xs text-gray-400">{{ ucfirst($s['type']) }}</p>
            </div>
            <span class="text-sm font-bold text-gray-900 dark:text-gray-100">{{ number_format($s['qty']) }}</span>
        </div>
        @endforeach
    </div>
</div>
@endif

@if($record->notes || $record->description)
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 space-y-2">
    @if($record->description)<p class="text-sm text-gray-700 dark:text-gray-300">{{ $record->description }}</p>@endif
    @if($record->notes)<p class="text-xs text-gray-400 italic">{{ $record->notes }}</p>@endif
</div>
@endif
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: STOCK BY LOCATION                                                     --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'stock')
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    @if(count($this->stockByLocation) === 0)
        <div class="px-5 py-12 text-center text-gray-400 text-sm">No stock on hand.</div>
    @else
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <th class="px-5 py-3 text-left">Location</th>
                <th class="px-5 py-3 text-left">Type</th>
                <th class="px-5 py-3 text-right">Quantity</th>
                <th class="px-5 py-3 text-right">Value</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->stockByLocation as $i => $s)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $s['location'] }}</td>
                <td class="px-5 py-3 capitalize">
                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-xs text-gray-600 dark:text-gray-300">
                        {{ $s['type'] ?: 'standard' }}
                    </span>
                </td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($s['qty']) }}</td>
                <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-400 tabular-nums">${{ number_format($s['qty'] * (float)$record->average_cost, 2) }}</td>
            </tr>
            @endforeach
            <tr class="bg-gray-100/60 dark:bg-gray-800/60 border-t-2 border-gray-200 dark:border-gray-700">
                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-100" colspan="2">Total</td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($totalQty) }}</td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-gray-100 tabular-nums">${{ number_format($totalQty * (float)$record->average_cost, 2) }}</td>
            </tr>
        </tbody>
    </table>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: LOTS                                                                   --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'lots')
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    @if(count($this->lots) === 0)
        <div class="px-5 py-12 text-center text-gray-400 text-sm">No inventory lots yet.</div>
    @else
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <th class="px-4 py-3 text-left">#</th>
                <th class="px-4 py-3 text-left">Received</th>
                <th class="px-4 py-3 text-left">Vendor</th>
                <th class="px-4 py-3 text-left">Invoice</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-right">Remaining</th>
                <th class="px-4 py-3 text-right">Unit Cost</th>
                <th class="px-4 py-3 text-right">Total</th>
                <th class="px-4 py-3 text-left">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->lots as $i => $lot)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-4 py-3 text-gray-400 font-mono text-xs">{{ $lot['id'] }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $lot['received_at'] }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lot['vendor'] }}</td>
                <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $lot['invoice'] }}</td>
                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 tabular-nums">{{ number_format($lot['quantity']) }}</td>
                <td class="px-4 py-3 text-right font-semibold tabular-nums {{ $lot['remaining'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}">{{ number_format($lot['remaining']) }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">${{ $lot['unit_cost'] }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">${{ $lot['total_cost'] }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                        {{ $lot['status'] === 'active' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-500' }}">
                        {{ ucfirst($lot['status']) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: RECEIVING HISTORY                                                      --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'receiving')
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    @if(count($this->receivingHistory) === 0)
        <div class="px-5 py-12 text-center text-gray-400 text-sm">No receiving history.</div>
    @else
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <th class="px-4 py-3 text-left">Session</th>
                <th class="px-4 py-3 text-left">Date</th>
                <th class="px-4 py-3 text-left">Vendor</th>
                <th class="px-4 py-3 text-right">Cases</th>
                <th class="px-4 py-3 text-right">Unit Cost</th>
                <th class="px-4 py-3 text-center">AI Confidence</th>
                <th class="px-4 py-3 text-center">Match Stage</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->receivingHistory as $i => $row)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-4 py-3 text-gray-400 font-mono text-xs">#{{ $row['session_id'] ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['date'] }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['vendor'] }}</td>
                <td class="px-4 py-3 text-right font-medium text-gray-900 dark:text-gray-100 tabular-nums">{{ $row['cases'] }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300 tabular-nums">${{ $row['unit_cost'] }}</td>
                <td class="px-4 py-3 text-center">
                    @if($row['confidence'] > 0)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                        {{ $row['confidence'] >= 95 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
                           ($row['confidence'] >= 80 ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' :
                           'bg-gray-100 dark:bg-gray-700 text-gray-500') }}">
                        {{ $row['confidence'] }}%
                    </span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center text-xs text-gray-500 dark:text-gray-400">{{ $row['stage'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: MOVEMENTS                                                              --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'movements')
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    @if(count($this->movements) === 0)
        <div class="px-5 py-12 text-center text-gray-400 text-sm">No movement history.</div>
    @else
    <div class="overflow-x-auto">
    <table class="table-fixed w-full text-sm">
        <colgroup>
            <col class="w-[14%]" /><col class="w-[14%]" /><col class="w-[10%]" />
            <col class="w-[24%]" /><col class="w-[38%]" />
        </colgroup>
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <th class="px-4 py-3 text-left">When</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-right">Qty</th>
                <th class="px-4 py-3 text-left">Location</th>
                <th class="px-4 py-3 text-left">Reason</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->movements as $i => $m)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">{{ $m['date'] }}</td>
                <td class="px-4 py-3">
                    @php
                        $mt = strtolower($m['type']);
                        $badgeClass = str_contains($mt, 'deduct') || str_contains($mt, 'damage') || str_contains($mt, 'return')
                            ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300'
                            : (str_contains($mt, 'receive') || str_contains($mt, 'opening') || str_contains($mt, 'adjustment')
                                ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300'
                                : 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300');
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold {{ $badgeClass }}">
                        {{ ucfirst(str_replace('_', ' ', $m['type'])) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right font-bold tabular-nums {{ $m['qty'] < 0 ? 'text-red-500 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                    {{ $m['label'] }}
                    @if (($m['grouped'] ?? 1) > 1)
                        <span class="block text-[10px] font-normal text-gray-400">{{ $m['grouped'] }} scans</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $m['location'] }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 text-xs max-w-xs truncate">{{ $m['reason'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: COST HISTORY                                                           --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'cost')
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
    @if(count($this->costHistory) === 0)
        <div class="px-5 py-12 text-center text-gray-400 text-sm">No cost history yet.</div>
    @else
    {{-- table-fixed with declared widths. On auto layout the browser hands the
         slack to whichever column it likes, and a header can end up sitting
         over a different span than the cells beneath it — which is why the
         numbers did not line up under their titles. Fixed widths make that
         impossible rather than unlikely. --}}
    <table class="w-full text-sm table-fixed">
        <colgroup>
            <col class="w-[38%]" />
            <col class="w-[20%]" />
            <col class="w-[17%]" />
            <col class="w-[25%]" />
        </colgroup>
        <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
            <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <th class="px-5 py-3 text-left">Period</th>
                <th class="px-5 py-3 text-right">Unit Cost</th>
                <th class="px-5 py-3 text-right">Quantity</th>
                <th class="px-5 py-3 text-left">Source</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->costHistory as $i => $row)
            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300 whitespace-nowrap">{{ $row['date'] }}</td>
                <td class="px-5 py-3 text-right font-semibold text-gray-900 dark:text-gray-100 tabular-nums">{{ $row['unit_cost'] === null ? '—' : '$' . number_format($row['unit_cost'], 2) }}</td>
                <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-400 tabular-nums">{{ number_format($row['qty']) }}</td>
                <td class="px-5 py-3 text-xs">
                    <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-700 px-2 py-0.5 text-gray-600 dark:text-gray-300 font-medium">
                        {{ ucfirst($row['source']) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: COST ANALYSIS                                                          --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'analysis')
<div class="space-y-6">
    @php $analysis = $this->costAnalysis; @endphp

    {{-- Cost Summary Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Total Invested</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($analysis['total_invested'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">Across all lots (historic)</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Current Inventory Value</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($analysis['current_value'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">{{ number_format($analysis['current_stock']) }} units @ {{ number_format($analysis['weighted_avg_cost'], 2) }}/unit</p>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-5">
            <p class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-2">Weighted Avg Cost</p>
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($analysis['current_avg_cost'], 2) }}</p>
            <p class="text-xs text-gray-500 mt-2">Combined from all active lots</p>
        </div>
    </div>

    {{-- By Vendor Breakdown --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Cost by Vendor</h3>
        </div>
        @if(count($analysis['by_vendor']) === 0)
            <div class="px-5 py-10 text-center text-gray-400 text-sm">No vendor data available.</div>
        @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($analysis['by_vendor'] as $vendor)
            <div class="px-5 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                <div class="flex items-start justify-between mb-2">
                    <div>
                        <p class="font-medium text-gray-900 dark:text-gray-100">{{ $vendor['vendor'] }}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ $vendor['lots_count'] }} lot(s)</p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-gray-900 dark:text-gray-100">${{ number_format($vendor['total_cost'], 2) }}</p>
                        <p class="text-xs text-gray-500">{{ number_format($vendor['total_units']) }} units</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-xs">
                    <div class="flex-grow">
                        <div class="h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                            <div class="h-full bg-primary-500" style="width: {{ ($vendor['total_cost'] / ($analysis['total_invested'] || 1)) * 100 }}%"></div>
                        </div>
                    </div>
                    <span class="text-gray-500 dark:text-gray-400 whitespace-nowrap">${{ number_format($vendor['avg_unit_cost'], 2) }}/unit</span>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Active Lots with Cost Breakdown --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Active Lots - Cost Breakdown</h3>
        </div>
        @if(count($analysis['active_lots']) === 0)
            <div class="px-5 py-10 text-center text-gray-400 text-sm">No active lots.</div>
        @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    <th class="px-4 py-3 text-left">Vendor</th>
                    <th class="px-4 py-3 text-left">Received</th>
                    <th class="px-4 py-3 text-right">Qty on Hand</th>
                    <th class="px-4 py-3 text-right">Unit Cost</th>
                    <th class="px-4 py-3 text-right">Total Cost</th>
                    <th class="px-4 py-3 text-right">% of Stock</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($analysis['active_lots'] as $i => $lot)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lot['vendor'] }}</td>
                    <td class="px-4 py-3 text-gray-500 text-xs">{{ $lot['received_at'] }}</td>
                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-gray-100">{{ number_format($lot['remaining']) }}</td>
                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ number_format($lot['unit_cost'], 2) }}</td>
                    <td class="px-4 py-3 text-right font-bold text-gray-900 dark:text-gray-100">${{ number_format($lot['total_cost'], 2) }}</td>
                    <td class="px-4 py-3 text-right">
                        <span class="inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 px-2 py-0.5 text-xs font-semibold">
                            {{ $lot['pct_of_stock'] }}%
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- TAB: ALIASES & AI                                                           --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if($tab === 'aliases')
<div class="space-y-4">
    {{-- Alias summary --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Product Identities & Aliases</h3>
            <span class="text-xs text-gray-400">{{ count($this->aliases) }} total</span>
        </div>
        @if(count($this->aliases) === 0)
            <div class="px-5 py-10 text-center text-gray-400 text-sm">No aliases learned yet. They build up as you receive shipments.</div>
        @else
        <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <tr class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                    <th class="px-4 py-3 text-left">Type</th>
                    <th class="px-4 py-3 text-left">Value</th>
                    <th class="px-4 py-3 text-center">Times Confirmed</th>
                    <th class="px-4 py-3 text-center">AI Confidence</th>
                    <th class="px-4 py-3 text-left">Last Seen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($this->aliases as $i => $alias)
                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 {{ $i % 2 === 1 ? 'bg-gray-50/50 dark:bg-gray-800/20' : '' }}">
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                            {{ $alias['type'] === 'upc' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' :
                               ($alias['type'] === 'alias' ? 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300' :
                               'bg-gray-100 dark:bg-gray-700 text-gray-500') }}">
                            {{ strtoupper($alias['type']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 max-w-xs truncate">{{ $alias['value'] }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center justify-center h-6 w-6 rounded-full text-xs font-bold
                            {{ $alias['times'] >= 5 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300' }}">
                            {{ $alias['times'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($alias['confidence'] > 0)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold
                            {{ $alias['confidence'] >= 95 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
                               'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' }}">
                            {{ $alias['confidence'] }}%
                        </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap">{{ $alias['last_seen'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        </div>
        @endif
    </div>

    {{-- AI matching context --}}
    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-3">AI Match Quality</h3>
        @php
            $confirmedAliases = collect($this->aliases)->where('times', '>=', 1)->count();
            $strongAliases    = collect($this->aliases)->where('times', '>=', 5)->count();
            $avgConf          = collect($this->aliases)->avg('confidence') ?? 0;
        @endphp
        <div class="grid grid-cols-3 gap-3">
            <div class="text-center">
                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $confirmedAliases }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Confirmed Aliases</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold text-green-600 dark:text-green-400">{{ $strongAliases }}</p>
                <p class="text-xs text-gray-400 mt-0.5">Strong (5+ confirms)</p>
            </div>
            <div class="text-center">
                <p class="text-2xl font-bold {{ $avgConf >= 95 ? 'text-green-600 dark:text-green-400' : ($avgConf >= 80 ? 'text-amber-600 dark:text-amber-400' : 'text-gray-400') }}">
                    {{ $avgConf > 0 ? round($avgConf) . '%' : '—' }}
                </p>
                <p class="text-xs text-gray-400 mt-0.5">Avg Confidence</p>
            </div>
        </div>
        @if($confirmedAliases === 0)
        <p class="mt-3 text-xs text-gray-400 text-center">Aliases build automatically as you confirm matches during receiving sessions.</p>
        @endif
    </div>
</div>
@endif

</div>
</x-filament-panels::page>
