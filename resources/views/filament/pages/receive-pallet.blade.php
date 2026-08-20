<x-filament-panels::page>
{{-- Tapping Scan on a line opens the camera on it.

     Pressing "Scan" and then having to find the scanner yourself is the button
     not doing what it says — the whole point is that you are holding the box.
     This presses the page's own camera button, so the flow is identical to
     having pressed it directly, with the line already aimed at.

     Falls back to focusing the input when there is no camera button on screen:
     a desktop with a handheld scanner still wants the cursor in the box, and
     landing on nothing would be worse than the old behaviour. --}}
<div
    x-data
    @scan-line-targeted.window="
        const visible = (sel) => [...document.querySelectorAll(sel)].find(el => el.offsetParent !== null);
        const camera = visible('#camera-scan-btn, #camera-scan-btn-mobile');

        if (camera) {
            camera.click();
            return;
        }

        const box = visible('#barcode-input, #barcode-input-desktop');
        if (box) {
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            setTimeout(() => box.focus(), 250);
        }
    "
></div>

    <div class="space-y-6">

        {{-- ── Workflow Progress ────────────────────────────────────────── --}}
        <div class="rounded-xl border border-blue-200 dark:border-blue-800 bg-blue-50 dark:bg-blue-950 shadow-sm px-6 py-4">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-check-badge class="h-5 w-5 text-blue-600 dark:text-blue-400" />
                <h2 class="text-sm font-semibold text-blue-900 dark:text-blue-100">Pallet Receiving Workflow</h2>
            </div>
            <div class="flex items-center gap-2 text-sm flex-wrap">
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                    <x-heroicon-o-check-circle class="h-4 w-4" /> Stage 1: Manifest
                </span>
                <x-heroicon-o-arrow-right class="h-4 w-4 text-gray-400 hidden sm:inline" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300 font-medium">
                    <x-heroicon-o-inbox-arrow-down class="h-4 w-4" /> Stage 2: Receive
                </span>
                <x-heroicon-o-arrow-right class="h-4 w-4 text-gray-400 hidden sm:inline" />
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                    <x-heroicon-o-check class="h-4 w-4" /> Stage 3: Complete
                </span>
            </div>
        </div>

        {{-- ── Pallet Summary ──────────────────────────────────────────────── --}}
        @php
            $summaryExpected = collect($lineProgress)->sum('case_count');
            $summaryReceived = collect($lineProgress)->sum('received');
            $summaryPct      = $summaryExpected > 0 ? round(($summaryReceived / $summaryExpected) * 100) : 0;
            $summaryDone     = $summaryExpected > 0 && $summaryReceived >= $summaryExpected;
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-4 space-y-4">
            <div class="grid grid-cols-2 md:flex md:flex-wrap gap-4 md:gap-6">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Vendor</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->vendor?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Reference</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100 truncate">{{ $this->record->reference ?? '—' }}</p>
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
            </div>
            @if ($summaryExpected > 0)
                <div class="flex flex-col md:flex-row md:items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
                    <div>
                        <p class="text-xs text-gray-400 uppercase tracking-wide">Overall Progress</p>
                        <p class="text-base md:text-sm font-bold {{ $summaryDone ? 'text-green-600 dark:text-green-400' : 'text-gray-900 dark:text-gray-100' }} tabular-nums">
                            {{ $summaryPct }}% &nbsp;·&nbsp; {{ $summaryReceived }}/{{ $summaryExpected }} boxes
                        </p>
                    </div>
                    <div class="flex-1 bg-gray-100 dark:bg-gray-700 rounded-full h-3 md:h-2.5">
                        <div class="h-full rounded-full {{ $summaryDone ? 'bg-green-500' : 'bg-violet-500' }} transition-all"
                             style="width: {{ $summaryPct }}%"></div>
                    </div>
                </div>
            @endif
        </div>

        {{-- ── Barcode Scanner Input ────────────────────────────────────────── --}}
        <div class="rounded-xl border-2 border-violet-300 dark:border-violet-700 bg-violet-50 dark:bg-violet-950 shadow-sm px-6 py-5 space-y-3">
            <div class="flex items-center gap-3 flex-wrap">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Barcode Scanner</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400">📱 Use phone camera or manual entry</span>
                @php
                    $unmappedCount = collect($lineProgress)->filter(fn ($l) => !$l['mapped'])->count();
                @endphp
                @if ($unmappedCount > 0)
                    <span class="ml-auto inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-exclamation-circle class="h-3 w-3 mr-1" />
                        {{ $unmappedCount }} not in inventory yet
                    </span>
                @endif
            </div>

            {{-- Mobile layout (stacked buttons) --}}
            <div class="md:hidden space-y-2">
                <input
                    wire:model="barcodeInput"
                    wire:keydown.enter="submitBarcode"
                    id="barcode-input"
                    type="text"
                    placeholder="Scan barcode…"
                    autofocus
                    autocomplete="off"
                    class="w-full rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-3.5 text-base text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono"
                />
                <div class="flex gap-2">
                    <button type="button" id="camera-scan-btn-mobile" title="Scan with camera"
                        class="flex-1 rounded-lg border border-violet-300 dark:border-violet-600 px-4 py-3 text-violet-600 dark:text-violet-400 hover:bg-violet-100 dark:hover:bg-violet-900 focus:outline-none focus:ring-2 focus:ring-violet-500 font-medium flex items-center justify-center gap-2">
                        <x-heroicon-o-video-camera class="h-5 w-5" />
                        <span>Camera</span>
                    </button>
                    <button
                        wire:click="submitBarcode"
                        type="button"
                        class="flex-1 rounded-lg bg-violet-600 px-4 py-3 text-white hover:bg-violet-700 active:bg-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-500 font-medium flex items-center justify-center gap-2"
                    >
                        <x-heroicon-o-arrow-right class="h-5 w-5" />
                        <span>Submit</span>
                    </button>
                </div>
            </div>

            {{-- Desktop layout (inline) --}}
            <div class="hidden md:flex gap-2 items-center">
                <input
                    wire:model="barcodeInput"
                    wire:keydown.enter="submitBarcode"
                    id="barcode-input-desktop"
                    type="text"
                    placeholder="Scan barcode here…"
                    autofocus
                    autocomplete="off"
                    class="flex-1 min-w-0 rounded-lg border border-violet-300 dark:border-violet-600 bg-white dark:bg-gray-900 px-4 py-2.5 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-500 focus:outline-none font-mono"
                />
                <button
                    wire:click="submitBarcode"
                    type="button"
                    class="rounded-lg bg-violet-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-violet-700 active:bg-violet-800 focus:outline-none focus:ring-2 focus:ring-violet-500 flex-shrink-0"
                >
                    Receive
                </button>
                <button type="button" id="camera-scan-btn" title="Scan with camera"
                    class="flex-shrink-0 rounded-lg border border-violet-300 dark:border-violet-600 px-3 py-2.5 text-violet-600 dark:text-violet-400 hover:bg-violet-100 dark:hover:bg-violet-900 focus:outline-none focus:ring-2 focus:ring-violet-500 flex items-center justify-center">
                    <x-heroicon-o-video-camera class="h-5 w-5" />
                </button>
            </div>

            {{-- Camera View --}}
            <div id="camera-container" class="hidden space-y-2">
                <video id="camera-video" class="w-full rounded-lg border border-violet-300 dark:border-violet-600" autoplay playsinline muted></video>
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs text-violet-600 dark:text-violet-400">📷 Point camera at barcode. Detection is automatic.</p>
                    <button type="button" id="camera-stop-btn" class="text-xs font-medium text-red-600 dark:text-red-400 hover:underline">Stop camera</button>
                </div>
            </div>

            @if ($lastScannedResult)
                <div class="rounded-lg px-4 py-2.5 text-sm font-medium {{ $lastScanSuccess ? 'bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200' : 'bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200' }}">
                    {{ $lastScannedResult }}
                </div>
                @if ($lastScanDetails && $lastScanDetails['type'] === 'case')
                    <div class="rounded-lg bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 px-4 py-3 text-sm space-y-2">
                        <p class="font-semibold text-blue-900 dark:text-blue-100">📦 Case Contents</p>
                        <p class="text-blue-800 dark:text-blue-200">
                            <span class="font-medium">{{ $lastScanDetails['parent'] }}</span>
                            contains
                            <span class="font-medium">{{ $lastScanDetails['quantity'] }} × {{ $lastScanDetails['child'] }}</span>
                        </p>
                        <p class="text-xs text-blue-700 dark:text-blue-300">Scan the individual items inside, or use "Receive All" for the line above.</p>
                    </div>
                @endif
            @endif
        </div>

        {{-- ── Unmapped Lines Warning ───────────────────────────────────────── --}}
        @php
            $unmappedLines = collect($lineProgress)->filter(fn ($l) => !$l['mapped'])->values();
        @endphp
        @if ($unmappedLines->count() > 0)
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 shadow-sm px-6 py-4 space-y-3">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100">
                            {{ $unmappedLines->count() }} not in inventory yet
                        </h3>
                        <p class="text-xs text-amber-800 dark:text-amber-200 mt-0.5">
                            Nothing to do here — these were staged from the packing slip and do not exist in inventory yet. Tap Scan on the line when you have the box: the code you scan is what creates them.
                        </p>
                        <div class="mt-2 space-y-1">
                            @foreach ($unmappedLines as $line)
                                <div class="flex items-center justify-between gap-2 text-xs">
                                    <span class="text-amber-700 dark:text-amber-300">L{{ $line['line_number'] }}: {{ $line['description'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @endif

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
                <div class="sm:hidden px-4 py-4 border-b border-gray-100 dark:border-gray-800 last:border-b-0 space-y-3
                            {{ $done ? 'bg-green-50/40 dark:bg-green-950/20' : ($mapped ? '' : 'bg-amber-50/40 dark:bg-amber-950/20') }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[11px] font-mono text-gray-400">L{{ $line['line_number'] }}</span>
                                <span class="text-base font-medium text-gray-900 dark:text-gray-100 leading-snug">{{ $line['description'] }}</span>
                            </div>
                            <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-gray-400">
                                @if ($line['item_name'])
                                    <span class="text-violet-600 dark:text-violet-400">✓ {{ $line['item_name'] }}</span>
                                @else
                                    <span class="text-gray-500 dark:text-gray-400">New — scan to add</span>
                                @endif
                                @if ($line['location'])
                                    <span class="text-gray-500 dark:text-gray-400">@ {{ $line['location'] }}</span>
                                @endif
                            </div>
                        </div>
                        @if ($done)
                            <span class="inline-flex items-center rounded-full bg-green-100 dark:bg-green-900 px-2.5 py-1 text-xs font-semibold text-green-700 dark:text-green-300 shrink-0">
                                <x-heroicon-o-check class="h-4 w-4 mr-1" /> Done
                            </span>
                        @else
                            <div class="text-right shrink-0">
                                <p class="text-lg font-bold text-gray-700 dark:text-gray-200 tabular-nums">{{ $line['received'] }}/{{ $line['case_count'] }}</p>
                                <p class="text-xs text-gray-400">boxes</p>
                            </div>
                        @endif
                    </div>
                    <div class="w-full bg-gray-100 dark:bg-gray-700 rounded-full h-2">
                        <div class="h-2 rounded-full {{ $done ? 'bg-green-500' : 'bg-violet-500' }} transition-all duration-300"
                             style="width: {{ $pct }}%"></div>
                    </div>
                    @if ($mapped && !$done)
                        <button wire:click="receiveLine({{ $line['id'] }})" type="button"
                            class="w-full rounded-lg bg-violet-600 px-4 py-3 text-base font-medium text-white hover:bg-violet-700 active:bg-violet-800">
                            Receive All {{ $line['case_count'] }} Boxes
                        </button>
                    @endif
                </div>

                {{-- Desktop table row --}}
                <div class="hidden sm:grid grid-cols-12 gap-3 items-center px-6 py-3.5
                            border-b border-gray-100 dark:border-gray-800 last:border-b-0
                            {{ $done ? 'bg-green-50/40 dark:bg-green-950/20' : ($mapped ? ($idx % 2 === 1 ? 'bg-gray-50/60 dark:bg-gray-800/30' : '') : 'bg-amber-50/40 dark:bg-amber-950/20') }}
                            hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors">
                    <div class="col-span-1 text-xs font-mono text-gray-400">L{{ $line['line_number'] }}</div>
                    <div class="col-span-4 min-w-0">
                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug truncate">{{ $line['description'] }}</p>
                    </div>
                    <div class="col-span-2 min-w-0">
                        @if ($line['item_name'])
                            <p class="text-xs text-violet-700 dark:text-violet-400 truncate font-medium">✓ {{ $line['item_name'] }}</p>
                        @else
                            <span class="inline-flex items-center rounded-full bg-gray-100 dark:bg-gray-800 px-2 py-0.5 text-[11px] font-medium text-gray-600 dark:text-gray-400">
                                New — scan to add
                            </span>
                        @endif
                        @if ($line['location'])
                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">@ {{ $line['location'] }}</p>
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
                    <div class="col-span-1 text-center text-sm text-gray-500 tabular-nums font-medium">{{ $line['case_count'] }}</div>
                    <div class="col-span-1 text-center">
                        <span class="text-sm font-bold {{ $done ? 'text-green-600 dark:text-green-400' : 'text-gray-700 dark:text-gray-200' }} tabular-nums">
                            {{ $line['received'] }}
                        </span>
                    </div>
                    <div class="col-span-1 flex justify-end gap-2">
                        @if ($done)
                            <x-heroicon-o-check-circle class="h-5 w-5 text-green-500" />
                        @elseif ($mapped)
                            <button wire:click="receiveLine({{ $line['id'] }})" type="button"
                                class="rounded-lg bg-violet-600 px-3 py-1 text-xs font-medium text-white hover:bg-violet-700 active:bg-violet-800 transition-colors whitespace-nowrap">
                                Receive All
                            </button>
                        @else
                            {{-- "Map first" was a dead end on the one screen
                                 where you are holding the box. Tapping this
                                 aims the scanner at this line: the code you
                                 scan creates the item and counts the box, which
                                 is the whole point of having staged it. --}}
                            <button wire:click="targetLine({{ $line['id'] }})" type="button"
                                class="rounded-lg px-3 py-1 text-xs font-medium whitespace-nowrap transition-colors
                                    {{ $targetLineId === $line['id']
                                        ? 'bg-violet-600 text-white'
                                        : 'border border-violet-300 dark:border-violet-700 text-violet-700 dark:text-violet-300 hover:bg-violet-50 dark:hover:bg-violet-950' }}">
                                {{ $targetLineId === $line['id'] ? 'Scan now →' : 'Scan' }}
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

        {{-- ── Media & Signatures ───────────────────────────────────────────── --}}
        <div class="space-y-5 md:grid md:grid-cols-2 md:gap-6">
            {{-- Media Attachments --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-5 space-y-3">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-camera class="h-5 w-5 text-gray-500" />
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Media & Documents</h2>
                    @if ($this->record->attachments()->count() > 0)
                        <span class="ml-auto inline-flex items-center rounded-full bg-blue-100 dark:bg-blue-900 px-2 py-0.5 text-xs font-medium text-blue-700 dark:text-blue-300">
                            {{ $this->record->attachments()->count() }} file{{ $this->record->attachments()->count() !== 1 ? 's' : '' }}
                        </span>
                    @endif
                </div>
                <p class="text-xs text-gray-400">Photos, documents, and receipt images — add them here as you unload.</p>

                @if ($this->record->attachments()->count() > 0)
                    <div class="space-y-1.5">
                        @foreach ($this->record->attachments as $att)
                            <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                @if ($att->isImage())
                                    <x-heroicon-o-photo class="h-4 w-4 text-blue-500" />
                                @elseif ($att->isPdf())
                                    <x-heroicon-o-document-text class="h-4 w-4 text-red-500" />
                                @else
                                    <x-heroicon-o-link class="h-4 w-4 text-gray-400" />
                                @endif
                                <span class="text-xs text-gray-700 dark:text-gray-300 truncate flex-1">{{ $att->file_name }}</span>
                                <span class="text-[10px] text-gray-400">{{ \App\Models\PalletAttachment::typeLabels()[$att->type] ?? 'File' }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-3 px-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                        <x-heroicon-o-camera class="h-6 w-6 mx-auto text-gray-300 dark:text-gray-600 mb-1" />
                        <p class="text-xs text-gray-500 dark:text-gray-400">No attachments yet</p>
                    </div>
                @endif

                {{-- Opens the header action on this page rather than linking
                     away: the upload belongs where the pallet is being worked.
                     (This was a link to PalletResource::getUrl written
                     unqualified, and Blade compiles with no imports, so the
                     name resolved to the global namespace and fatalled the
                     whole page.) --}}
                <div class="flex flex-wrap gap-2 pt-1">
                    <button
                        type="button"
                        wire:click="mountAction('add_attachments')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-2 text-xs font-medium text-white hover:bg-blue-700 active:scale-95 transition-transform"
                    >
                        <x-heroicon-o-arrow-up-tray class="h-4 w-4" /> Upload
                    </button>
                    {{-- Opens the same modal, then trips the camera once the
                         modal's upload field exists — otherwise the capture
                         fires into a page with nowhere to put the photo and is
                         silently dropped. Waits for the field rather than
                         guessing at a delay. --}}
                    <button
                        type="button"
                        x-data
                        @click="
                            $wire.mountAction('add_attachments');
                            (async () => {
                                for (let i = 0; i < 40; i++) {
                                    if (document.querySelector('.fi-modal input[type=&quot;file&quot;]')) {
                                        window.dispatchEvent(new Event('open-photo-capture'));
                                        return;
                                    }
                                    await new Promise(r => setTimeout(r, 100));
                                }
                            })();
                        "
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 dark:border-gray-600 px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800 active:scale-95 transition-transform"
                    >
                        <x-heroicon-o-camera class="h-4 w-4" /> Take Photo
                    </button>
                </div>
            </div>

            {{-- Receiver Info --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-5 space-y-3">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-user class="h-5 w-5 text-gray-500" />
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Receiver Info</h2>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Name of Person Receiving
                    </label>
                    <input
                        wire:model="receivedByName"
                        type="text"
                        placeholder="Enter receiver name…"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-3 md:py-2 text-base md:text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    />
                </div>

                @if ($receivedByName)
                    <div class="text-xs text-gray-500 bg-blue-50 dark:bg-blue-950 rounded-lg p-2 border-l-2 border-blue-300 dark:border-blue-700">
                        ✓ Will save as receiver: <span class="font-semibold">{{ $receivedByName }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Missing Items Tracker ───────────────────────────────────────── --}}
        @php
            $missingLines = collect($lineProgress)->filter(fn ($l) => $l['received'] < $l['case_count'] && $l['case_count'] > 0);
            $totalMissing = $missingLines->sum(fn ($l) => $l['case_count'] - $l['received']);
        @endphp
        @if ($missingLines->count() > 0)
            <div class="rounded-xl border border-amber-200 dark:border-amber-800 bg-amber-50 dark:bg-amber-950/30 shadow-sm px-6 py-5 space-y-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-exclamation-circle class="h-6 w-6 text-amber-600 dark:text-amber-400 mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base md:text-sm font-semibold text-amber-900 dark:text-amber-100">
                            {{ $totalMissing }} Item{{ $totalMissing !== 1 ? 's' : '' }} Not Yet Received
                        </h2>
                        <p class="text-sm md:text-xs text-amber-800 dark:text-amber-200 mt-1">
                            Mark items as missing/short if they don't arrive. You can finalize the pallet even with missing items.
                        </p>
                    </div>
                </div>

                <div class="space-y-2">
                    @foreach ($missingLines as $line)
                        @if ($line['received'] < $line['case_count'])
                            <div class="flex items-center justify-between gap-3 p-3 bg-white dark:bg-gray-800 rounded-lg border border-amber-100 dark:border-amber-900">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                        L{{ $line['line_number'] }}: {{ $line['description'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                                        Expected {{ $line['case_count'] }}, received {{ $line['received'] }}
                                        <span class="font-semibold text-amber-600 dark:text-amber-400">
                                            ({{ $line['case_count'] - $line['received'] }} short)
                                        </span>
                                    </p>
                                </div>
                                <button
                                    wire:click="markLineAsShort({{ $line['id'] }})"
                                    type="button"
                                    class="shrink-0 rounded-lg bg-amber-600 px-3 py-2 text-xs font-medium text-white hover:bg-amber-700 active:bg-amber-800"
                                >
                                    Mark Short
                                </button>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- ── Finalize Actions ─────────────────────────────────────────────── --}}
        @if (collect($lineProgress)->sum('received') >= collect($lineProgress)->sum('case_count') && collect($lineProgress)->count() > 0)
            <div class="rounded-xl border-2 border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-950 shadow-sm px-6 py-5 space-y-4">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="h-6 w-6 text-green-500 mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <h2 class="text-base md:text-sm font-semibold text-green-900 dark:text-green-100">All cases received! 🎉</h2>
                        <p class="text-sm md:text-xs text-green-700 dark:text-green-200 mt-1">
                            You've received all expected cases for this pallet. Enter the receiver name above, then finalize to complete.
                        </p>
                    </div>
                </div>
                @if ($receivedByName)
                    <button
                        wire:click="finalizePallet"
                        type="button"
                        class="w-full rounded-lg bg-green-600 px-4 py-3 md:py-2 text-base md:text-sm font-medium text-white hover:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500"
                    >
                        ✓ Finalize Pallet & Complete
                    </button>
                @else
                    <button
                        disabled
                        type="button"
                        class="w-full rounded-lg bg-gray-200 dark:bg-gray-700 px-4 py-3 md:py-2 text-base md:text-sm font-medium text-gray-600 dark:text-gray-300 cursor-not-allowed opacity-60"
                    >
                        Complete receiver name above to finalize
                    </button>
                @endif
            </div>
        @endif

        {{-- ── Quick Tips ───────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-950/20 shadow-sm px-6 py-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" />
                <div class="flex-1 text-sm space-y-1">
                    <p class="font-semibold text-blue-900 dark:text-blue-100">Quick Tips & Shortcuts</p>
                    <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-0.5">
                        <li>📱 <strong>Use camera button</strong> for hands-free scanning (⌨️ Alt+C)</li>
                        <li>💡 <strong>Scan cases first</strong> to see what's inside (case barcodes show contents)</li>
                        <li>💡 <strong>Scan individual items</strong> to receive them one-by-one or use "Receive All" for a line</li>
                        <li>⌨️ <strong>Alt+M</strong> = Focus manual scanner · <strong>Esc</strong> = Close camera</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>

    {{-- ── Camera Scanner Script ───────────────────────────────────────── --}}
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const cameraButtons = document.querySelectorAll('#camera-scan-btn, #camera-scan-btn-mobile');
            const container = document.getElementById('camera-container');
            const video = document.getElementById('camera-video');
            const stopBtn = document.getElementById('camera-stop-btn');
            // Resolved when it is needed, not at load. Which of the two
            // layouts is on screen depends on the viewport now, and both exist
            // in the DOM at all times — so a reference taken once can be to the
            // hidden one.
            const currentInput = () => [...document.querySelectorAll('#barcode-input, #barcode-input-desktop')]
                .find(el => el.offsetParent !== null)
                ?? document.getElementById('barcode-input-desktop')
                ?? document.getElementById('barcode-input');
            let isScanning = false;
            let cameraStream = null;

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                // Alt+C = Open camera
                if (e.altKey && e.key === 'c') {
                    e.preventDefault();
                    cameraButtons[0]?.click();
                }
                // Escape = Close camera
                if (e.key === 'Escape' && isScanning) {
                    stopCamera();
                }
                // Alt+M = Focus manual input
                if (e.altKey && e.key === 'm') {
                    e.preventDefault();
                    currentInput()?.focus();
                }
            });

            // Delegated, not bound to the elements captured at load. Every
            // Livewire round trip on this page — a scan, a line targeted, a
            // count going up — morphs the DOM, and a listener attached to a
            // replaced button dies with it. That is a camera button that works
            // until the first scan and silently stops afterwards, which is the
            // hardest kind of failure to report.
            document.addEventListener('click', function (e) {
                if (e.target.closest('#camera-scan-btn, #camera-scan-btn-mobile')) {
                    startCamera();
                }
            });

            document.addEventListener('click', function (e) {
                if (e.target.closest('#camera-stop-btn')) {
                    stopCamera();
                }
            });

            async function startCamera() {
                if (isScanning || cameraStream) return;
                isScanning = true;

                try {
                    container.classList.remove('hidden');
                    const stream = await navigator.mediaDevices.getUserMedia({
                        video: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } }
                    });
                    cameraStream = stream;
                    video.srcObject = stream;
                    video.play();

                    // Vibrate when camera starts
                    if (navigator.vibrate) navigator.vibrate(100);

                    setTimeout(() => startBarcodeDetection(video), 500);
                } catch (error) {
                    console.error('Camera error:', error);
                    let message = 'Camera access not available.';
                    if (error.name === 'NotAllowedError') {
                        message = 'Camera permission denied. Please allow camera access in settings.';
                    } else if (error.name === 'NotFoundError') {
                        message = 'No camera found on this device. Use manual scanning instead.';
                    }
                    alert(message);
                    container.classList.add('hidden');
                    isScanning = false;
                }
            }

            function stopCamera() {
                isScanning = false;
                if (cameraStream) {
                    cameraStream.getTracks().forEach(track => track.stop());
                    cameraStream = null;
                }
                if (Quagga) Quagga.stop();
                video.srcObject = null;
                container.classList.add('hidden');
            }

            function startBarcodeDetection(videoElement) {
                Quagga.init({
                    inputStream: { type: 'LiveStream', constraints: { facingMode: 'environment', width: { ideal: 640 }, height: { ideal: 480 } } },
                    decoder: { workers: 2, debug: false, multiple: false }
                }, function(err) {
                    if (err) { console.error('Quagga error:', err); return; }
                    Quagga.start();
                });

                Quagga.onDetected(function(result) {
                    if (isScanning && result.codeResult) {
                        const barcode = result.codeResult.code;
                        console.log('📷 Detected barcode:', barcode);

                        // Haptic feedback: double vibration for success
                        if (navigator.vibrate) navigator.vibrate([50, 50, 50]);

                        // Play success sound using Web Audio API
                        try {
                            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
                            const oscillator = audioContext.createOscillator();
                            const gainNode = audioContext.createGain();
                            oscillator.connect(gainNode);
                            gainNode.connect(audioContext.destination);
                            oscillator.frequency.value = 1000;
                            oscillator.type = 'sine';
                            gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);
                            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.1);
                            oscillator.start(audioContext.currentTime);
                            oscillator.stop(audioContext.currentTime + 0.1);
                        } catch (e) { /* Audio not available */ }

                        const box = currentInput();
                        box.value = barcode;
                        box.dispatchEvent(new Event('input', { bubbles: true }));
                        stopCamera();

                        // Trigger submit
                        const submitBtn = box.parentElement?.querySelector('button[type="button"]:not(#camera-scan-btn):not(#camera-scan-btn-mobile)');
                        if (submitBtn) submitBtn.click();
                    }
                });
            }

            // Stop camera on page unload
            window.addEventListener('beforeunload', stopCamera);
        });
    </script>
</x-filament-panels::page>
