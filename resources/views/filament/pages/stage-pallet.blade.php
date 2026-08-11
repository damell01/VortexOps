<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Workflow Progress ────────────────────────────────────────── --}}
        <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 shadow-sm px-6 py-4">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-check-badge class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100">Staging Workflow</h2>
            </div>
            <div class="flex items-center gap-2 text-sm">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 font-medium">
                    <x-heroicon-o-check-circle class="h-4 w-4" /> Stage 1: Manifest
                </span>
                <x-heroicon-o-arrow-right class="h-4 w-4 text-gray-400" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-medium">
                    <x-heroicon-o-inbox-arrow-down class="h-4 w-4" /> Stage 2: Receive
                </span>
                <x-heroicon-o-arrow-right class="h-4 w-4 text-gray-400" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 font-medium">
                    <x-heroicon-o-check class="h-4 w-4" /> Stage 3: Complete
                </span>
            </div>
        </div>

        {{-- ── Pallet Header ────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-4 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Vendor</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->vendor?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Reference</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->reference ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Total Cost</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $this->record->total_cost ? '$' . number_format($this->record->total_cost, 2) : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Lines Added</p>
                    <p class="text-sm font-bold text-blue-600 dark:text-blue-400">{{ $this->record->lines()->count() }}</p>
                </div>
            </div>
        </div>

        {{-- ── CSV Import Section ───────────────────────────────────────── --}}
        <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950 shadow-sm px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-document-arrow-up class="h-5 w-5 text-amber-600 dark:text-amber-400" />
                <h2 class="text-sm font-semibold text-amber-900 dark:text-amber-100">Import Manifest (CSV)</h2>
                <span class="text-xs text-amber-600 dark:text-amber-400 ml-auto">Optional - or add lines manually below</span>
            </div>

            <form wire:submit="importCsv" class="space-y-3">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            CSV File
                        </label>
                        <input type="file"
                            wire:model="csvData"
                            accept=".csv"
                            class="w-full rounded-lg border border-amber-300 dark:border-amber-600 bg-white dark:bg-gray-900 px-3 py-2 text-sm text-gray-900 dark:text-gray-100 file:mr-3 file:bg-amber-100 dark:file:bg-amber-900 file:border-0 file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-amber-700 dark:file:text-amber-300"
                        />
                        <p class="text-[11px] text-amber-600 dark:text-amber-400 mt-1">
                            Columns: description, quantity, case_count, unit_cost (optional)
                        </p>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit"
                            class="flex-1 rounded-lg bg-amber-600 px-4 py-2 text-sm font-medium text-white hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        >
                            Import CSV
                        </button>
                    </div>
                </div>
            </form>

            <details class="text-xs text-amber-700 dark:text-amber-200 bg-amber-100/30 dark:bg-amber-900/20 rounded-lg px-3 py-2 cursor-pointer">
                <summary class="font-semibold">📋 CSV Format Example</summary>
                <div class="mt-2 font-mono text-[10px] whitespace-pre-wrap">
description,quantity,case_count,unit_cost
2024 Topps Chrome Box,1,24,2.50
2024 Bowman Platinum Pack,5,20,1.75
2024 Judge Card Case,1,1,500.00
                </div>
            </details>
        </div>

        {{-- ── Manifest Lines ───────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Manifest Lines</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Add items expected on this pallet. Map to inventory and assign locations later.</p>
                </div>
                <span class="text-sm font-bold text-gray-700 dark:text-gray-200">
                    {{ $this->record->lines()->count() }} line{{ $this->record->lines()->count() !== 1 ? 's' : '' }}
                </span>
            </div>

            {{-- Desktop table header --}}
            <div class="hidden sm:grid grid-cols-12 gap-3 px-6 py-2.5 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700
                        text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <div class="col-span-1">#</div>
                <div class="col-span-4">Description</div>
                <div class="col-span-2">Cases</div>
                <div class="col-span-2">Qty/Case</div>
                <div class="col-span-2">Unit Cost</div>
                <div class="col-span-1">Actions</div>
            </div>

            {{-- Lines list --}}
            @forelse ($this->record->lines as $idx => $line)
                {{-- Mobile card --}}
                <div class="sm:hidden px-4 py-3.5 border-b border-gray-100 dark:border-gray-800 last:border-b-0 space-y-2">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5">
                                <span class="text-[11px] font-mono text-gray-400">L{{ $line->line_number }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $line->description }}</span>
                            </div>
                            <div class="mt-2 space-y-1 text-xs text-gray-600 dark:text-gray-400">
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Cases:</span>
                                    <span class="font-medium">{{ $line->case_count }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Qty/Case:</span>
                                    <span class="font-medium">{{ $line->quantity_per_case }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-500 dark:text-gray-400">Cost:</span>
                                    <span class="font-medium">${{ number_format($line->unit_cost, 2) }}</span>
                                </div>
                            </div>
                        </div>
                        <a href="{{ PalletResource::getUrl('edit', ['record' => $this->record]) }}"
                            class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap">
                            Edit
                        </a>
                    </div>
                </div>

                {{-- Desktop row --}}
                <div class="hidden sm:grid grid-cols-12 gap-3 items-center px-6 py-3.5
                            border-b border-gray-100 dark:border-gray-800 last:border-b-0
                            {{ $idx % 2 === 1 ? 'bg-gray-50/60 dark:bg-gray-800/30' : '' }}
                            hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <div class="col-span-1 text-xs font-mono text-gray-400">L{{ $line->line_number }}</div>
                    <div class="col-span-4 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $line->description }}</div>
                    <div class="col-span-2 text-sm text-gray-700 dark:text-gray-300 text-center">{{ $line->case_count }}</div>
                    <div class="col-span-2 text-sm text-gray-700 dark:text-gray-300 text-center">{{ $line->quantity_per_case }}</div>
                    <div class="col-span-2 text-sm text-gray-700 dark:text-gray-300 text-center">${{ number_format($line->unit_cost, 2) }}</div>
                    <div class="col-span-1 text-right">
                        <a href="{{ PalletResource::getUrl('edit', ['record' => $this->record]) }}"
                            class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                            Map
                        </a>
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">
                    <x-heroicon-o-document-text class="h-8 w-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                    No manifest lines added yet. Import a CSV or edit the pallet to add lines.
                </div>
            @endforelse

            {{-- Footer summary --}}
            @if ($this->record->lines()->count() > 0)
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-4">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->record->lines()->count() }} line{{ $this->record->lines()->count() !== 1 ? 's' : '' }} ·
                        {{ $this->record->totalCasesCount() }} total cases
                    </span>
                    <span class="text-sm font-bold text-gray-800 dark:text-gray-100 tabular-nums">
                        Total: ${{ number_format($this->record->lines()->sum('unit_cost'), 2) }}
                    </span>
                </div>
            @endif
        </div>

        {{-- ── Next Steps ────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30 shadow-sm px-6 py-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                <div class="flex-1 min-w-0 text-sm text-green-800 dark:text-green-200">
                    <p class="font-semibold mb-1">Next Steps:</p>
                    <ol class="list-decimal list-inside space-y-0.5 text-xs">
                        <li>Review manifest lines above</li>
                        <li><a href="{{ PalletResource::getUrl('edit', ['record' => $this->record]) }}" class="font-medium underline">Edit pallet</a> to map items to inventory and assign receiving locations</li>
                        <li>Click <strong>"Ready to Receive"</strong> above to start the receiving workflow when the pallet arrives</li>
                    </ol>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
