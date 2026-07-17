<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Pallet Summary ──────────────────────────────────────────────── --}}
        @php
            $summaryExpected = collect($lineProgress)->sum('case_count');
            $summaryReceived = collect($lineProgress)->sum('received');
            $summaryPct      = $summaryExpected > 0 ? round(($summaryReceived / $summaryExpected) * 100) : 0;
            $summaryDone     = $summaryExpected > 0 && $summaryReceived >= $summaryExpected;
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-4 space-y-3">
            <div class="flex flex-wrap gap-6">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Vendor</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->vendor?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Reference</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->reference ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Status</p>
                    @php
                        $palletStatusColors = [
                            'pending'   => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                            'receiving' => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                            'received'  => 'bg-sky-100 dark:bg-sky-900 text-sky-700 dark:text-sky-300',
                            'processed' => 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300',
                        ];
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $palletStatusColors[$this->record->status] ?? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                        {{ \App\Models\Pallet::statusLabels()[$this->record->status] ?? ucfirst($this->record->status) }}
                    </span>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Lines</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->lines->count() }}</p>
                </div>
                @if ($summaryExpected > 0)
                    <div class="ml-auto flex items-center gap-3 shrink-0">
                        <div class="text-right">
                            <p class="text-xs text-gray-400 uppercase tracking-wide">Overall Progress</p>
                            <p class="text-sm font-bold {{ $summaryDone ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }} tabular-nums">
                                {{ $summaryPct }}% &nbsp;·&nbsp; {{ $summaryReceived }}/{{ $summaryExpected }} boxes
                            </p>
                        </div>
                        <div class="w-32 bg-gray-100 dark:bg-gray-700 rounded-full h-2.5">
                            <div class="h-2.5 rounded-full {{ $summaryDone ? 'bg-green-500' : 'bg-violet-500' }} transition-all"
                                 style="width: {{ $summaryPct }}%"></div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Barcode Scanner Input ────────────────────────────────────────── --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 shadow-sm px-6 py-5 space-y-3">
            <div class="flex items-center gap-3">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Barcode Scanner</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400">Click the field below, then scan a case barcode</span>
            </div>

            <div class="flex gap-3 items-center">
                <input
                    wire:model="barcodeInput"
                    wire:keydown.enter="submitBarcode"
                    type="text"
                    placeholder="Scan barcode here…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono"
                />
                <button
                    wire:click="submitBarcode"
                    type="button"
                    class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 focus:outline-none focus:ring-2 focus:ring-violet-500"
                >
                    Receive
                </button>
            </div>

            @if ($lastScannedResult)
                <div class="rounded-lg px-4 py-2.5 text-sm font-medium {{ $lastScanSuccess ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }}">
                    {{ $lastScannedResult }}
                </div>
            @endif
        </div>

        {{-- ── Manifest Lines ───────────────────────────────────────────────── --}}
        @php
            $totalExpected = collect($lineProgress)->sum('case_count');
            $totalReceived = collect($lineProgress)->sum('received');
            $allDone       = $totalExpected > 0 && $totalReceived >= $totalExpected;
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">

            {{-- Header with overall progress --}}
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800 flex flex-wrap items-center gap-4">
                <div class="flex-1 min-w-0">
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Manifest Lines</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Use "Receive All" to mark all boxes on a line as received without scanning.</p>
                </div>
                @if ($totalExpected > 0)
                    <div class="flex items-center gap-3 shrink-0">
                        <div class="text-right">
                            <p class="text-sm font-bold {{ $allDone ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }} tabular-nums">
                                {{ $totalReceived }}/{{ $totalExpected }}
                            </p>
                            <p class="text-[11px] text-gray-400">boxes received</p>
                        </div>
                        <div class="w-24 bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                            <div class="h-2 rounded-full {{ $allDone ? 'bg-green-500' : 'bg-violet-500' }} transition-all"
                                 style="width: {{ $totalExpected > 0 ? round(($totalReceived / $totalExpected) * 100) : 0 }}%"></div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Desktop table header (hidden on mobile) --}}
            <div class="hidden sm:grid grid-cols-12 gap-3 px-6 py-2.5 bg-gray-50 dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700
                        text-[11px] font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                <div class="col-span-1">#</div>
                <div class="col-span-4">Description</div>
                <div class="col-span-2">Item → Location</div>
                <div class="col-span-2 text-center">Progress</div>
                <div class="col-span-1 text-center">Expected</div>
                <div class="col-span-1 text-center">Received</div>
                <div class="col-span-1"></div>
            </div>

            @forelse ($lineProgress as $idx => $line)
                @php
                    $pct    = $line['case_count'] > 0 ? round(($line['received'] / $line['case_count']) * 100) : 0;
                    $done   = $line['received'] >= $line['case_count'];
                    $mapped = $line['mapped'];
                @endphp

                {{-- Mobile card layout --}}
                <div class="sm:hidden px-4 py-3.5 border-b border-gray-100 dark:border-gray-800 last:border-b-0 space-y-2
                            {{ $done ? 'bg-green-50/40 dark:bg-green-950/20' : '' }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[11px] font-mono text-gray-400">L{{ $line['line_number'] }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug">{{ $line['description'] }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-400">
                                @if ($line['item_name'])
                                    <span class="text-violet-600 dark:text-violet-400">→ {{ $line['item_name'] }}</span>
                                @else
                                    <span class="text-amber-500 dark:text-amber-400">Needs mapping</span>
                                @endif
                                @if ($line['location'])
                                    <span>@ {{ $line['location'] }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            @if ($done)
                                <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900 px-2 py-0.5 text-[11px] font-semibold text-green-700 dark:text-green-300">
                                    <x-heroicon-o-check class="h-3 w-3 mr-0.5" /> Done
                                </span>
                            @elseif ($mapped)
                                <span class="text-sm font-bold text-gray-700 dark:text-gray-200 tabular-nums">{{ $line['received'] }}/{{ $line['case_count'] }}</span>
                                <button wire:click="receiveLine({{ $line['id'] }})" type="button"
                                    class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700">
                                    Receive All
                                </button>
                            @else
                                <span class="text-sm font-bold text-gray-400 tabular-nums">{{ $line['received'] }}/{{ $line['case_count'] }}</span>
                            @endif
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                        <div class="h-1.5 rounded-full {{ $done ? 'bg-green-500' : 'bg-violet-500' }} transition-all duration-300"
                             style="width: {{ $pct }}%"></div>
                    </div>
                </div>

                {{-- Desktop table row --}}
                <div class="hidden sm:grid grid-cols-12 gap-3 items-center px-6 py-3.5
                            border-b border-gray-100 dark:border-gray-800 last:border-b-0
                            {{ $done ? 'bg-green-50/40 dark:bg-green-950/20' : ($idx % 2 === 1 ? 'bg-gray-50/60 dark:bg-gray-800/30' : '') }}
                            hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <div class="col-span-1 text-xs font-mono text-gray-400">L{{ $line['line_number'] }}</div>
                    <div class="col-span-4 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug truncate">{{ $line['description'] }}</p>
                    </div>
                    <div class="col-span-2 min-w-0">
                        @if ($line['item_name'])
                            <p class="text-xs text-violet-700 dark:text-violet-400 truncate font-medium">{{ $line['item_name'] }}</p>
                        @else
                            <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                                Needs mapping
                            </span>
                        @endif
                        @if ($line['location'])
                            <p class="text-xs text-gray-400 truncate mt-0.5">@ {{ $line['location'] }}</p>
                        @endif
                    </div>
                    <div class="col-span-2">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-1.5">
                                <div class="h-1.5 rounded-full {{ $done ? 'bg-green-500' : 'bg-violet-500' }} transition-all"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-[11px] text-gray-400 tabular-nums shrink-0">{{ $pct }}%</span>
                        </div>
                    </div>
                    <div class="col-span-1 text-center text-sm text-gray-500 tabular-nums">{{ $line['case_count'] }}</div>
                    <div class="col-span-1 text-center">
                        <span class="text-sm font-bold {{ $done ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-200' }} tabular-nums">
                            {{ $line['received'] }}
                        </span>
                    </div>
                    <div class="col-span-1 flex justify-end">
                        @if ($done)
                            <x-heroicon-o-check-circle class="h-5 w-5 text-green-500" />
                        @elseif ($mapped)
                            <button wire:click="receiveLine({{ $line['id'] }})" type="button"
                                class="rounded-lg bg-gray-100 dark:bg-gray-700 px-2.5 py-1 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-violet-600 hover:text-white dark:hover:bg-violet-600 transition-colors whitespace-nowrap">
                                Receive All
                            </button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="px-6 py-10 text-center text-sm text-gray-400">
                    <x-heroicon-o-inbox class="h-8 w-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                    No manifest lines on this pallet yet. Add lines first.
                </div>
            @endforelse

            {{-- Footer totals --}}
            @if (count($lineProgress) > 0)
                <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between gap-4">
                    <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ count($lineProgress) }} line{{ count($lineProgress) !== 1 ? 's' : '' }} ·
                        {{ collect($lineProgress)->filter(fn ($l) => $l['received'] >= $l['case_count'] && $l['case_count'] > 0)->count() }}
                        complete
                    </span>
                    <span class="text-sm font-bold {{ $allDone ? 'text-green-600 dark:text-green-400' : 'text-gray-800 dark:text-gray-100' }} tabular-nums">
                        {{ $totalReceived }} / {{ $totalExpected }} boxes
                    </span>
                </div>
            @endif
        </div>

    </div>
</x-filament-panels::page>
