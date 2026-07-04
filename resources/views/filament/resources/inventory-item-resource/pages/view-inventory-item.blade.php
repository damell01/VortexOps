<x-filament-panels::page>
@php
    $tabs = [
        'overview'   => 'Overview',
        'stock'      => 'Stock by Location',
        'lots'       => 'Lots',
        'receiving'  => 'Receiving History',
        'movements'  => 'Movements',
        'cost'       => 'Cost History',
        'aliases'    => 'Aliases & AI',
    ];
@endphp

<div class="space-y-6">

{{-- ── Product Hero ──────────────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
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
        <div class="flex-shrink-0 text-right space-y-1">
            @php $totalQty = collect($this->stockByLocation)->sum('qty'); @endphp
            <p class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalQty) }}</p>
            <p class="text-xs text-gray-400">units in stock</p>
            <p class="text-sm font-medium text-gray-700 dark:text-gray-300">${{ number_format((float)$record->average_cost, 2) }} avg cost</p>
        </div>
    </div>
</div>

{{-- ── Tab Bar ───────────────────────────────────────────────────────────────── --}}
<div class="flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-700 pb-px">
    @foreach($tabs as $key => $label)
    <button wire:click="setTab('{{ $key }}')" type="button"
        class="flex-shrink-0 px-4 py-2 text-sm font-medium rounded-t-lg transition-colors
            {{ $tab === $key
                ? 'bg-white dark:bg-gray-900 text-primary-600 dark:text-primary-400 border border-b-white dark:border-b-gray-900 border-gray-200 dark:border-gray-700'
                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
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
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Location</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500 dark:text-gray-400">Type</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Quantity</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500 dark:text-gray-400">Value</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->stockByLocation as $s)
            <tr>
                <td class="px-5 py-3 font-medium text-gray-900 dark:text-gray-100">{{ $s['location'] }}</td>
                <td class="px-5 py-3 text-gray-500 capitalize">{{ $s['type'] ?: '—' }}</td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-gray-100">{{ number_format($s['qty']) }}</td>
                <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-400">${{ number_format($s['qty'] * (float)$record->average_cost, 2) }}</td>
            </tr>
            @endforeach
            <tr class="bg-gray-50 dark:bg-gray-800">
                <td class="px-5 py-3 font-semibold text-gray-900 dark:text-gray-100" colspan="2">Total</td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-gray-100">{{ number_format($totalQty) }}</td>
                <td class="px-5 py-3 text-right font-bold text-gray-900 dark:text-gray-100">${{ number_format($totalQty * (float)$record->average_cost, 2) }}</td>
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
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-500">#</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Received</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Vendor</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Invoice</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Qty</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Remaining</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Unit Cost</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Total</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->lots as $lot)
            <tr>
                <td class="px-4 py-3 text-gray-400 font-mono">{{ $lot['id'] }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lot['received_at'] }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $lot['vendor'] }}</td>
                <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $lot['invoice'] }}</td>
                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($lot['quantity']) }}</td>
                <td class="px-4 py-3 text-right font-medium {{ $lot['remaining'] > 0 ? 'text-green-600 dark:text-green-400' : 'text-gray-400' }}">{{ number_format($lot['remaining']) }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ $lot['unit_cost'] }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ $lot['total_cost'] }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
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
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Session</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Date</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Vendor</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Cases</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Unit Cost</th>
                <th class="px-4 py-3 text-center font-medium text-gray-500">AI Confidence</th>
                <th class="px-4 py-3 text-center font-medium text-gray-500">Match Stage</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->receivingHistory as $row)
            <tr>
                <td class="px-4 py-3 text-gray-400 font-mono">#{{ $row['session_id'] ?? '—' }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['date'] }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $row['vendor'] }}</td>
                <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ $row['cases'] }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">${{ $row['unit_cost'] }}</td>
                <td class="px-4 py-3 text-center">
                    @if($row['confidence'] > 0)
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                        {{ $row['confidence'] >= 95 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
                           ($row['confidence'] >= 80 ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' :
                           'bg-gray-100 dark:bg-gray-700 text-gray-500') }}">
                        {{ $row['confidence'] }}%
                    </span>
                    @else
                        <span class="text-gray-400">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-center text-xs text-gray-500">{{ $row['stage'] }}</td>
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
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-4 py-3 text-left font-medium text-gray-500">When</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                <th class="px-4 py-3 text-right font-medium text-gray-500">Qty</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Location</th>
                <th class="px-4 py-3 text-left font-medium text-gray-500">Reason</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->movements as $m)
            <tr>
                <td class="px-4 py-3 text-gray-400 text-xs">{{ $m['date'] }}</td>
                <td class="px-4 py-3">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                        {{ str_contains($m['type'], 'deduct') ? 'bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300' :
                           (str_contains($m['type'], 'receive') || str_contains($m['type'], 'opening') ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
                           'bg-gray-100 dark:bg-gray-700 text-gray-500') }}">
                        {{ ucfirst(str_replace('_', ' ', $m['type'])) }}
                    </span>
                </td>
                <td class="px-4 py-3 text-right font-medium {{ $m['qty'] < 0 ? 'text-red-500' : 'text-green-600 dark:text-green-400' }}">
                    {{ $m['qty'] > 0 ? '+' : '' }}{{ number_format($m['qty']) }}
                </td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $m['location'] }}</td>
                <td class="px-4 py-3 text-gray-500 text-xs">{{ $m['reason'] }}</td>
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
    <table class="w-full text-sm">
        <thead class="bg-gray-50 dark:bg-gray-800">
            <tr>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Period</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500">Unit Cost</th>
                <th class="px-5 py-3 text-right font-medium text-gray-500">Quantity</th>
                <th class="px-5 py-3 text-left font-medium text-gray-500">Source</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($this->costHistory as $row)
            <tr>
                <td class="px-5 py-3 text-gray-700 dark:text-gray-300">{{ $row['date'] }}</td>
                <td class="px-5 py-3 text-right font-medium text-gray-900 dark:text-gray-100">${{ number_format($row['unit_cost'], 2) }}</td>
                <td class="px-5 py-3 text-right text-gray-600 dark:text-gray-400">{{ number_format($row['qty']) }}</td>
                <td class="px-5 py-3 text-xs text-gray-500">{{ ucfirst($row['source']) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif
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
            <thead class="bg-gray-50 dark:bg-gray-800">
                <tr>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Type</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Value</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-500">Times Confirmed</th>
                    <th class="px-4 py-3 text-center font-medium text-gray-500">AI Confidence</th>
                    <th class="px-4 py-3 text-left font-medium text-gray-500">Last Seen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach($this->aliases as $alias)
                <tr>
                    <td class="px-4 py-3">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $alias['type'] === 'upc' ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' :
                               ($alias['type'] === 'alias' ? 'bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300' :
                               'bg-gray-100 dark:bg-gray-700 text-gray-500') }}">
                            {{ strtoupper($alias['type']) }}
                        </span>
                    </td>
                    <td class="px-4 py-3 font-mono text-xs text-gray-700 dark:text-gray-300 max-w-xs truncate">{{ $alias['value'] }}</td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-sm font-bold {{ $alias['times'] >= 5 ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-300' }}">
                            {{ $alias['times'] }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($alias['confidence'] > 0)
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium
                            {{ $alias['confidence'] >= 95 ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
                               'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' }}">
                            {{ $alias['confidence'] }}%
                        </span>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-xs text-gray-400">{{ $alias['last_seen'] }}</td>
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
