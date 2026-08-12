<x-filament-panels::page>
<div class="space-y-6">

{{-- ── Flash Messages ──────────────────────────────────────────────────────── --}}
@if($flashOk)
    <div class="rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 px-5 py-3 flex items-center gap-3">
        <x-heroicon-o-check-circle class="h-5 w-5 text-green-500 flex-shrink-0" />
        <span class="text-sm text-green-800 dark:text-green-200">{{ $flashOk }}</span>
        <button wire:click="$set('flashOk', null)" class="ml-auto text-green-400 hover:text-green-600">
            <x-heroicon-o-x-mark class="h-4 w-4" />
        </button>
    </div>
@endif
@if($flashError)
    <div class="rounded-lg bg-red-50 dark:bg-red-950 border border-red-200 dark:border-red-800 px-5 py-3 flex items-center gap-3">
        <x-heroicon-o-exclamation-circle class="h-5 w-5 text-red-400 flex-shrink-0" />
        <span class="text-sm text-red-700 dark:text-red-300">{{ $flashError }}</span>
        <button wire:click="$set('flashError', null)" class="ml-auto text-red-400 hover:text-red-600">
            <x-heroicon-o-x-mark class="h-4 w-4" />
        </button>
    </div>
@endif

{{-- ── Session Header ───────────────────────────────────────────────────────── --}}
<div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-5">
    <div class="flex items-start justify-between gap-4 flex-wrap">
        <div>
            <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                Session #{{ $record->id }}
                @if($record->vendor)
                    · {{ $record->vendor->name }}
                @endif
            </h2>
            <div class="mt-1 flex flex-wrap gap-4 text-xs text-gray-400">
                @if($record->invoice_number)<span>Invoice: {{ $record->invoice_number }}</span>@endif
                @if($record->purchase_order)<span>PO: {{ $record->purchase_order }}</span>@endif
                <span>Started: {{ $record->created_at->diffForHumans() }}</span>
            </div>
        </div>
        <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-medium
            {{ $record->status === 'completed' ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' :
               ($record->status === 'reviewing' ? 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300' :
               'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
            {{ \App\Models\ReceivingSession::statusLabels()[$record->status] ?? $record->status }}
        </span>
    </div>

    {{-- Match summary tiles --}}
    @php
        $total  = $record->total_lines;
        $auto   = $record->auto_matched_count;
        $review = $record->review_required_count;
        $new    = $record->new_product_count;
        $pct    = $total > 0 ? round(($auto / $total) * 100) : 0;
    @endphp
    <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="rounded-lg bg-gray-50 dark:bg-gray-800 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $total }}</p>
            <p class="text-xs text-gray-400 mt-0.5">Total Lines</p>
        </div>
        <div class="rounded-lg bg-green-50 dark:bg-green-950 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-green-700 dark:text-green-300">{{ $auto }}</p>
            <p class="flex items-center justify-center gap-1 text-xs text-green-600 dark:text-green-400 mt-0.5">
                <x-heroicon-o-check-circle class="h-3.5 w-3.5" /> Auto-Matched
            </p>
        </div>
        <div class="rounded-lg bg-amber-50 dark:bg-amber-950 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-amber-700 dark:text-amber-300">{{ $review }}</p>
            <p class="flex items-center justify-center gap-1 text-xs text-amber-600 dark:text-amber-400 mt-0.5">
                <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5" /> Needs Review
            </p>
        </div>
        <div class="rounded-lg bg-violet-50 dark:bg-violet-950 px-4 py-3 text-center">
            <p class="text-2xl font-bold text-violet-700 dark:text-violet-300">{{ $new }}</p>
            <p class="flex items-center justify-center gap-1 text-xs text-violet-600 dark:text-violet-400 mt-0.5">
                <x-heroicon-o-sparkles class="h-3.5 w-3.5" /> New Products
            </p>
        </div>
    </div>

    @if($total > 0)
        <div class="mt-4">
            <div class="h-2 rounded-full bg-gray-100 dark:bg-gray-800 overflow-hidden">
                <div class="h-full rounded-full bg-green-500 transition-all duration-500" style="width: {{ $pct }}%"></div>
            </div>
            <p class="text-xs text-gray-400 mt-1 text-right">{{ $pct }}% auto-matched</p>
        </div>
    @endif
</div>

{{-- ── Receiving Preview ─────────────────────────────────────────────────────── --}}
@if($total > 0 && $record->status !== 'completed')
<div>
    <button wire:click="$toggle('showPreview')" type="button"
        class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
        <x-heroicon-o-clipboard-document-list class="h-4 w-4" />
        {{ $showPreview ? 'Hide preview' : 'Preview what will happen when completed' }}
    </button>

    @if($showPreview)
    @php $preview = $this->preview; @endphp
    <div class="mt-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 shadow-sm space-y-4">
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Receiving Session #{{ $record->id }} — Preview</h3>
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
            <div class="rounded-lg bg-violet-50 dark:bg-violet-950 p-3">
                <p class="text-xl font-bold text-violet-700 dark:text-violet-300">{{ $preview['new_products'] }}</p>
                <p class="text-xs text-violet-600 dark:text-violet-400">Products to Create</p>
            </div>
            <div class="rounded-lg bg-green-50 dark:bg-green-950 p-3">
                <p class="text-xl font-bold text-green-700 dark:text-green-300">{{ $preview['update_products'] }}</p>
                <p class="text-xs text-green-600 dark:text-green-400">Products to Update</p>
            </div>
            <div class="rounded-lg bg-purple-50 dark:bg-purple-950 p-3">
                <p class="text-xl font-bold text-purple-700 dark:text-purple-300">{{ $preview['lots_to_create'] }}</p>
                <p class="text-xs text-purple-600 dark:text-purple-400">Lots to Create</p>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($preview['total_cases']) }}</p>
                <p class="text-xs text-gray-500">Cases Added</p>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($preview['total_cost'], 2) }}</p>
                <p class="text-xs text-gray-500">Total Cost</p>
            </div>
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800 p-3">
                <p class="text-xl font-bold text-gray-900 dark:text-gray-100">${{ number_format($preview['avg_cost'], 2) }}</p>
                <p class="text-xs text-gray-500">Avg Cost/Case</p>
            </div>
        </div>
        @if(! empty($preview['warnings']))
        <div class="space-y-1">
            <p class="text-xs font-medium text-amber-600 dark:text-amber-400">Warnings</p>
            @foreach($preview['warnings'] as $warn)
                <div class="flex items-center gap-2 text-xs text-amber-700 dark:text-amber-300">
                    <x-heroicon-o-exclamation-triangle class="h-3.5 w-3.5 flex-shrink-0" />
                    {{ $warn }}
                </div>
            @endforeach
        </div>
        @endif
    </div>
    @endif
</div>
@endif

{{-- ── Manual Line Import ───────────────────────────────────────────────────── --}}
@if($record->status !== 'completed')
<div>
    <button wire:click="$toggle('showManualImport')" type="button"
        class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200">
        <x-heroicon-o-plus-circle class="h-4 w-4" />
        {{ $showManualImport ? 'Cancel import' : 'Import lines manually' }}
    </button>

    @if($showManualImport)
    <div class="mt-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-4 shadow-sm space-y-5">
        {{-- Upload a manifest document (CSV) --}}
        <div class="space-y-2">
            <p class="text-sm font-medium text-gray-700 dark:text-gray-200">Upload a manifest</p>
            <p class="text-xs text-gray-400">
                A CSV with columns <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">Description, Qty, Unit Cost, UPC</code>.
                Each row runs through the matching pipeline — auto-matched, needs-review, or new product. Use the
                <span class="font-medium">Sample manifest</span> button above to grab one to try.
            </p>
            <input type="file" wire:model="csvFile" accept=".csv,text/csv"
                class="block w-full text-sm text-gray-600 dark:text-gray-300 file:mr-3 file:rounded-lg file:border-0 file:bg-violet-50 dark:file:bg-violet-500/10 file:px-3 file:py-2 file:text-sm file:font-medium file:text-violet-700 dark:file:text-violet-300 hover:file:bg-violet-100" />
            <div wire:loading wire:target="csvFile" class="text-xs text-gray-400">Uploading…</div>
            @error('csvFile') <p class="text-xs text-red-500">{{ $message }}</p> @enderror
            <button wire:click="importCsvFile" wire:loading.attr="disabled" type="button"
                @disabled(! $csvFile)
                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700 disabled:opacity-50 disabled:cursor-not-allowed">
                Parse &amp; match file
            </button>
        </div>

        <div class="flex items-center gap-3">
            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
            <span class="text-xs uppercase tracking-wide text-gray-400">or paste lines</span>
            <div class="h-px flex-1 bg-gray-200 dark:bg-gray-700"></div>
        </div>

        {{-- Paste lines manually --}}
        <div class="space-y-2">
            <p class="text-xs text-gray-400">One line per row: <code class="bg-gray-100 dark:bg-gray-800 px-1 rounded">Description | Qty | Unit Cost | UPC (optional)</code></p>
            <textarea wire:model="manualLines" rows="5" placeholder="2025 Topps Chrome Hobby | 12 | 95.00
2025 Bowman Jumbo Hobby | 6 | 130.00"
                class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
            </textarea>
            <button wire:click="importManualLines" type="button"
                class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                Run Matching
            </button>
        </div>
    </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- AUTO-MATCHED                                                                --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if(count($grouped['auto']) > 0)
<div class="rounded-xl border border-green-200 dark:border-green-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
    <div class="px-5 py-3 flex items-center justify-between bg-green-50 dark:bg-green-950">
        <div class="flex items-center gap-2">
            <x-heroicon-o-check-circle class="h-5 w-5 text-green-600 dark:text-green-400" />
            <h3 class="text-sm font-semibold text-green-900 dark:text-green-100">
                Auto-Matched ({{ count($grouped['auto']) }})
            </h3>
            <span class="text-xs text-green-600 dark:text-green-400">≥ 95% confidence — no action needed</span>
        </div>
        <div class="flex items-center gap-3">
            @if($record->status !== 'completed')
                <button wire:click="acceptAllAuto" type="button"
                    class="text-xs rounded-lg bg-green-600 px-3 py-1.5 font-medium text-white hover:bg-green-700">
                    Confirm All
                </button>
            @endif
            <button wire:click="$toggle('showAuto')" type="button" class="text-xs text-green-600 dark:text-green-400 underline">
                {{ $showAuto ? 'Hide' : 'Show' }}
            </button>
        </div>
    </div>
    @if($showAuto)
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($grouped['auto'] as $line)
            @php $card = $this->productCards[$line['matched_id']] ?? null; @endphp
            <div class="px-5 py-3 space-y-2">
                <div class="flex items-center gap-4 text-sm">
                    <span class="text-gray-400 w-8 text-right flex-shrink-0">#{{ $line['line_number'] }}</span>
                    <div class="flex-1 min-w-0">
                        <p class="font-medium text-gray-900 dark:text-gray-100 truncate">{{ $line['matched_name'] }}</p>
                        @if($line['vendor_desc'] && $line['vendor_desc'] !== $line['matched_name'])
                            <p class="text-xs text-gray-400 truncate">Vendor: "{{ $line['vendor_desc'] }}"</p>
                        @endif
                    </div>
                    <span class="flex-shrink-0 inline-flex items-center rounded-full bg-green-100 dark:bg-green-900 px-2 py-0.5 text-xs font-medium text-green-700 dark:text-green-300">
                        {{ $line['confidence_pct'] }}%
                    </span>
                    <span class="flex-shrink-0 text-xs text-gray-400">{{ $line['case_count'] }} cases</span>
                    <span class="flex-shrink-0 text-xs text-gray-400">${{ $line['unit_cost'] }}/ea</span>
                </div>
                {{-- Reasons --}}
                @if(! empty($line['reasons']))
                <div class="ml-12 flex flex-wrap gap-1.5">
                    @foreach($line['reasons'] as $reason)
                        <span class="inline-flex items-center gap-1 text-xs text-green-700 dark:text-green-300 bg-green-50 dark:bg-green-950 px-2 py-0.5 rounded-full">
                            <x-heroicon-o-check class="h-3 w-3 flex-shrink-0" /> {{ $reason }}
                        </span>
                    @endforeach
                </div>
                @endif
                {{-- Compact stock --}}
                @if($card && $card['total_stock'] > 0)
                <div class="ml-12 text-xs text-gray-400">
                    Stock: {{ $card['total_stock'] }} units · Avg cost ${{ $card['avg_cost'] }} · Last received {{ $card['last_received'] }}
                </div>
                @endif
            </div>
            @endforeach
        </div>
    @endif
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- NEEDS REVIEW                                                                --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if(count($grouped['review']) > 0)
<div class="space-y-3">
    <div class="flex items-center gap-2">
        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-500" />
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
            Needs Review ({{ count($grouped['review']) }})
        </h3>
        <span class="text-xs text-gray-400">80–94% confidence — confirm or change</span>
    </div>

    @foreach($grouped['review'] as $line)
    @php
        $panel = $panels[$line['id']] ?? [];
        $card  = $this->productCards[$line['matched_id']] ?? null;
    @endphp
    <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        {{-- Wide screens: suggested match on the left, actions/panels in a fixed-width
             column on the right, so a monitor's width goes toward showing more match
             context rather than empty margin either side of a single narrow column. --}}
        <div class="px-5 py-4 lg:grid lg:grid-cols-[1fr,340px] lg:gap-5 lg:items-start">
            <div class="min-w-0">
                {{-- Line header --}}
                <div class="flex items-start justify-between gap-3 flex-wrap">
                    <div>
                        <p class="text-xs text-gray-400 mb-0.5">Line #{{ $line['line_number'] }} · Vendor description</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">"{{ $line['vendor_desc'] }}"</p>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2.5 py-1 text-sm font-bold text-amber-700 dark:text-amber-300">
                            {{ $line['confidence_pct'] }}%
                        </span>
                        <p class="text-xs text-gray-400 mt-0.5">{{ ucfirst($line['stage'] ?? 'fuzzy') }}</p>
                    </div>
                </div>

                {{-- Suggested match with rich card --}}
                @if($line['matched_name'])
                <div class="mt-3 rounded-lg bg-amber-50 dark:bg-amber-950 overflow-hidden">
                    <div class="flex items-start gap-3 px-4 py-3">
                        <x-heroicon-o-light-bulb class="h-4 w-4 text-amber-400 flex-shrink-0 mt-0.5" />
                        <div class="flex-1 min-w-0">
                            <p class="text-xs text-amber-600 dark:text-amber-400">Suggested match</p>
                            <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $line['matched_name'] }}</p>

                            {{-- Match reasons --}}
                            @if(! empty($line['reasons']))
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach($line['reasons'] as $reason)
                                    <span class="inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-900 px-2 py-0.5 rounded-full">
                                        <x-heroicon-o-check class="h-3 w-3 flex-shrink-0" /> {{ $reason }}
                                    </span>
                                @endforeach
                            </div>
                            @endif
                        </div>
                    </div>

                    {{-- Rich product card --}}
                    @if($card)
                    <div class="border-t border-amber-100 dark:border-amber-900 px-4 py-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                        <div>
                            <p class="text-gray-400 font-medium mb-1">Current Stock</p>
                            @if(empty($card['stock']))
                                <p class="text-gray-500">None in stock</p>
                            @else
                                @foreach($card['stock'] as $s)
                                    <div class="flex justify-between">
                                        <span class="text-gray-500 truncate">{{ $s['location'] }}</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100 ml-2">{{ $s['qty'] }}</span>
                                    </div>
                                @endforeach
                                <div class="flex justify-between border-t border-amber-100 dark:border-amber-900 mt-1 pt-1">
                                    <span class="font-medium text-gray-600 dark:text-gray-300">Total</span>
                                    <span class="font-bold text-gray-900 dark:text-gray-100">{{ $card['total_stock'] }}</span>
                                </div>
                            @endif
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium mb-1">Cost</p>
                            <p class="font-medium text-gray-900 dark:text-gray-100">${{ $card['avg_cost'] }}</p>
                            <p class="text-gray-400">avg cost</p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium mb-1">Last Received</p>
                            <p class="text-gray-700 dark:text-gray-300">{{ $card['last_received'] }}</p>
                        </div>
                        <div>
                            <p class="text-gray-400 font-medium mb-1">Aliases</p>
                            @if(empty($card['aliases']))
                                <p class="text-gray-500">None</p>
                            @else
                                @foreach(array_slice($card['aliases'], 0, 3) as $alias)
                                    <p class="text-gray-600 dark:text-gray-400 truncate">{{ $alias }}</p>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                @endif
            </div>

            <div class="mt-3 lg:mt-0 lg:border-l lg:border-gray-100 dark:lg:border-gray-800 lg:pl-5">
                {{-- Action buttons (when no panel open) --}}
                @if(! isset($panel['action']))
                <div class="flex flex-wrap gap-2 lg:flex-col lg:items-stretch">
                    @if($line['matched_name'])
                    <button wire:click="acceptLine({{ $line['id'] }})" type="button"
                        class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700">
                        <x-heroicon-o-check class="h-4 w-4" /> Accept
                    </button>
                    @endif
                    <button wire:click="openPanel({{ $line['id'] }}, 'choose')" type="button"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                        Choose Different
                    </button>
                    <button wire:click="openPanel({{ $line['id'] }}, 'create')" type="button"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                        Create New Product
                    </button>
                </div>
                @endif

                {{-- Choose Different panel --}}
                @if(($panel['action'] ?? null) === 'choose')
                <div class="space-y-3">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Choose product</p>
                    <select wire:model="panels.{{ $line['id'] }}.selectedProductId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="">— search products —</option>
                        @foreach($this->products as $p)
                            <option value="{{ $p->id }}">{{ $p->name }}{{ $p->year ? " ({$p->year})" : '' }}</option>
                        @endforeach
                    </select>
                    <select wire:model="panels.{{ $line['id'] }}.locationId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                        <option value="0">— destination location (optional) —</option>
                        @foreach($this->locations as $loc)
                            <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                        @endforeach
                    </select>
                    <div class="flex gap-2">
                        <button wire:click="confirmChoose({{ $line['id'] }})" type="button"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                            Confirm
                        </button>
                        <button wire:click="closePanel({{ $line['id'] }})" type="button"
                            class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Cancel
                        </button>
                    </div>
                </div>
                @endif

                {{-- Create New panel --}}
                @if(($panel['action'] ?? null) === 'create')
                <div class="space-y-3">
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Create new product</p>
                    <div class="grid grid-cols-1 gap-3">
                        <input wire:model="panels.{{ $line['id'] }}.newName" type="text" placeholder="Product name *"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <input wire:model="panels.{{ $line['id'] }}.newBrand" type="text" placeholder="Brand (e.g. Topps)"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <input wire:model="panels.{{ $line['id'] }}.newSport" type="text" placeholder="Sport (e.g. Baseball)"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <input wire:model="panels.{{ $line['id'] }}.newYear" type="number" placeholder="Year (e.g. 2025)"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <input wire:model="panels.{{ $line['id'] }}.newUpc" type="text" placeholder="UPC (optional)"
                            class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                        <select wire:model="panels.{{ $line['id'] }}.locationId"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                            <option value="0">— destination location (optional) —</option>
                            @foreach($this->locations as $loc)
                                <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button wire:click="confirmCreate({{ $line['id'] }})" type="button"
                            class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                            Create & Accept
                        </button>
                        <button wire:click="closePanel({{ $line['id'] }})" type="button"
                            class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-500 hover:bg-gray-50 dark:hover:bg-gray-800">
                            Cancel
                        </button>
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- ════════════════════════════════════════════════════════════════════════════ --}}
{{-- NEW PRODUCTS                                                                --}}
{{-- ════════════════════════════════════════════════════════════════════════════ --}}
@if(count($grouped['new']) > 0)
<div class="space-y-3">
    <div class="flex items-center gap-2">
        <x-heroicon-o-sparkles class="h-5 w-5 text-violet-500" />
        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
            New Products ({{ count($grouped['new']) }})
        </h3>
        <span class="text-xs text-gray-400">No match found — choose an existing product or create new</span>
    </div>

    @foreach($grouped['new'] as $line)
    @php $panel = $panels[$line['id']] ?? []; @endphp
    <div class="rounded-xl border border-violet-200 dark:border-violet-800 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
        <div class="px-5 py-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-question-mark-circle class="h-5 w-5 text-violet-400 flex-shrink-0 mt-0.5" />
                <div>
                    <p class="text-xs text-gray-400 mb-0.5">Line #{{ $line['line_number'] }} · No match found</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">"{{ $line['vendor_desc'] }}"</p>
                    <p class="text-xs text-gray-400 mt-1">{{ $line['case_count'] }} cases · ${{ $line['unit_cost'] }}/ea</p>
                </div>
            </div>

            @if(! isset($panel['action']))
            <div class="mt-3 flex flex-wrap gap-2">
                <button wire:click="openPanel({{ $line['id'] }}, 'choose')" type="button"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800">
                    Map to Existing Product
                </button>
                <button wire:click="openPanel({{ $line['id'] }}, 'create')" type="button"
                    class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                    Create New Product
                </button>
            </div>
            @endif

            @if(($panel['action'] ?? null) === 'choose')
            <div class="mt-3 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                <select wire:model="panels.{{ $line['id'] }}.selectedProductId"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="">— select a product —</option>
                    @foreach($this->products as $p)
                        <option value="{{ $p->id }}">{{ $p->name }}{{ $p->year ? " ({$p->year})" : '' }}</option>
                    @endforeach
                </select>
                <select wire:model="panels.{{ $line['id'] }}.locationId"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="0">— destination location (optional) —</option>
                    @foreach($this->locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button wire:click="confirmChoose({{ $line['id'] }})" type="button"
                        class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                        Confirm
                    </button>
                    <button wire:click="closePanel({{ $line['id'] }})" type="button"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
            @endif

            @if(($panel['action'] ?? null) === 'create')
            <div class="mt-3 space-y-3 border-t border-gray-100 dark:border-gray-800 pt-3">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="lg:col-span-4">
                        <input wire:model="panels.{{ $line['id'] }}.newName" type="text" placeholder="Product name *"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    </div>
                    <input wire:model="panels.{{ $line['id'] }}.newBrand" type="text" placeholder="Brand"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    <input wire:model="panels.{{ $line['id'] }}.newSport" type="text" placeholder="Sport"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    <input wire:model="panels.{{ $line['id'] }}.newYear" type="number" placeholder="Year"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                    <input wire:model="panels.{{ $line['id'] }}.newUpc" type="text" placeholder="UPC (optional)"
                        class="rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm font-mono text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500" />
                </div>
                <select wire:model="panels.{{ $line['id'] }}.locationId"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 focus:outline-none focus:ring-2 focus:ring-violet-500">
                    <option value="0">— destination location (optional) —</option>
                    @foreach($this->locations as $loc)
                        <option value="{{ $loc->id }}">{{ $loc->name }}</option>
                    @endforeach
                </select>
                <div class="flex gap-2">
                    <button wire:click="confirmCreate({{ $line['id'] }})" type="button"
                        class="rounded-lg bg-violet-600 px-4 py-2 text-sm font-medium text-white hover:bg-violet-700">
                        Create & Accept
                    </button>
                    <button wire:click="closePanel({{ $line['id'] }})" type="button"
                        class="rounded-lg border border-gray-200 dark:border-gray-700 px-4 py-2 text-sm text-gray-500">
                        Cancel
                    </button>
                </div>
            </div>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif

{{-- ── Empty state --}}
@if($total === 0)
<div class="rounded-xl border border-dashed border-gray-200 dark:border-gray-700 px-8 py-14 text-center space-y-2">
    <x-heroicon-o-inbox-arrow-down class="h-10 w-10 mx-auto text-gray-300 dark:text-gray-600" />
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No lines yet</p>
    <p class="text-xs text-gray-400 dark:text-gray-500">Use the manual import above or attach a pallet to this session</p>
</div>
@endif

{{-- ── Complete Session ─────────────────────────────────────────────────────── --}}
@if($record->status !== 'completed' && $total > 0)
<div class="flex items-center justify-between pt-2">
    <p class="text-xs text-gray-400">
        @php $pending = count($grouped['review']) + count($grouped['new']); @endphp
        @if($pending > 0)
            {{ $pending }} line(s) still need attention before completing.
        @else
            All lines resolved — ready to complete.
        @endif
    </p>
    <button wire:click="completeSession" type="button"
        @if($pending > 0) disabled @endif
        class="inline-flex items-center gap-1.5 rounded-lg px-5 py-2.5 text-sm font-medium text-white transition-colors
            {{ $pending > 0 ? 'bg-gray-300 dark:bg-gray-700 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700' }}">
        <x-heroicon-o-check class="h-4 w-4" /> Complete Session
    </button>
</div>
@endif

@if($record->status === 'completed')
<div class="rounded-lg bg-green-50 dark:bg-green-950 border border-green-200 dark:border-green-800 px-5 py-4 flex items-center gap-3">
    <x-heroicon-o-check-badge class="h-6 w-6 text-green-500 flex-shrink-0" />
    <div>
        <p class="text-sm font-semibold text-green-900 dark:text-green-100">Session Completed</p>
        <p class="text-xs text-green-600 dark:text-green-400">All lines received, inventory lots created, stock updated.</p>
    </div>
</div>
@endif

</div>
</x-filament-panels::page>
