<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Pallet Summary ──────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 flex flex-wrap gap-6">
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
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ ucfirst($this->record->status) }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Lines</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->lines->count() }}</p>
            </div>
        </div>

        {{-- ── Barcode Scanner Input ────────────────────────────────────────── --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 px-6 py-5 space-y-3">
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
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 divide-y divide-gray-100 dark:divide-gray-800">
            <div class="px-6 py-4">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Manifest Lines</h2>
                <p class="text-xs text-gray-400 mt-0.5">Click "Receive All" on a line to bulk-receive its cases without scanning.</p>
            </div>

            @forelse ($lineProgress as $line)
                @php
                    $pct     = $line['case_count'] > 0 ? round(($line['received'] / $line['case_count']) * 100) : 0;
                    $done    = $line['received'] >= $line['case_count'];
                    $mapped  = $line['mapped'];
                @endphp
                <div class="px-6 py-4 space-y-2">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-xs font-mono text-gray-400">L{{ $line['line_number'] }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $line['description'] }}</span>
                                @if ($done)
                                    <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900 px-2 py-0.5 text-[11px] font-medium text-green-700 dark:text-green-300">Done</span>
                                @elseif (! $mapped)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-300">Needs mapping</span>
                                @endif
                            </div>
                            <div class="mt-1 flex items-center gap-3 text-xs text-gray-400">
                                <span>{{ $line['case_count'] }} cases</span>
                                @if ($line['item_name'])
                                    <span>→ {{ $line['item_name'] }}</span>
                                @endif
                                @if ($line['location'])
                                    <span>@ {{ $line['location'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-3 flex-shrink-0">
                            <span class="text-sm font-semibold {{ $done ? 'text-green-600' : 'text-gray-600 dark:text-gray-300' }}">
                                {{ $line['received'] }}/{{ $line['case_count'] }}
                            </span>
                            @if (! $done && $mapped)
                                <button
                                    wire:click="receiveLine({{ $line['id'] }})"
                                    type="button"
                                    class="rounded-lg bg-gray-100 dark:bg-gray-800 px-3 py-1.5 text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-700 focus:outline-none"
                                >
                                    Receive All
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Progress bar --}}
                    <div class="w-full bg-gray-100 dark:bg-gray-800 rounded-full h-1.5">
                        <div
                            class="h-1.5 rounded-full {{ $done ? 'bg-green-500' : 'bg-violet-500' }} transition-all duration-300"
                            style="width: {{ $pct }}%"
                        ></div>
                    </div>
                </div>
            @empty
                <div class="px-6 py-8 text-center text-sm text-gray-400">
                    No manifest lines on this pallet yet. Add lines first.
                </div>
            @endforelse
        </div>

    </div>
</x-filament-panels::page>
