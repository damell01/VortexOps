<x-filament-panels::page>
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
            <div class="flex items-center gap-3 flex-wrap">
                <x-heroicon-o-qr-code class="h-5 w-5 text-violet-500" />
                <h2 class="text-sm font-semibold text-violet-900 dark:text-violet-100">Barcode Scanner</h2>
                <span class="text-xs text-violet-600 dark:text-violet-400">Scan a case or item barcode below</span>
                @php
                    $unmappedCount = collect($lineProgress)->filter(fn ($l) => !$l['mapped'])->count();
                @endphp
                @if ($unmappedCount > 0)
                    <span class="ml-auto inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2.5 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                        <x-heroicon-o-exclamation-circle class="h-3 w-3 mr-1" />
                        {{ $unmappedCount }} line{{ $unmappedCount !== 1 ? 's' : '' }} need mapping
                    </span>
                @endif
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
                            {{ $unmappedLines->count() }} line{{ $unmappedLines->count() !== 1 ? 's' : '' }} need mapping
                        </h3>
                        <p class="text-xs text-amber-800 dark:text-amber-200 mt-0.5">
                            These lines must be mapped to an inventory item and location before you can receive their cases.
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
                <div class="sm:hidden px-4 py-3.5 border-b border-gray-100 dark:border-gray-800 last:border-b-0 space-y-2
                            {{ $done ? 'bg-green-50/40 dark:bg-green-950/20' : ($mapped ? '' : 'bg-amber-50/40 dark:bg-amber-950/20') }}">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <span class="text-[11px] font-mono text-gray-400">L{{ $line['line_number'] }}</span>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100 leading-snug">{{ $line['description'] }}</span>
                            </div>
                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-gray-400">
                                @if ($line['item_name'])
                                    <span class="text-violet-600 dark:text-violet-400">✓ {{ $line['item_name'] }}</span>
                                @else
                                    <span class="text-amber-600 dark:text-amber-400 font-medium">⚠ Needs mapping</span>
                                @endif
                                @if ($line['location'])
                                    <span class="text-gray-500 dark:text-gray-400">@ {{ $line['location'] }}</span>
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
                                    class="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-violet-700 active:bg-violet-800">
                                    Receive All
                                </button>
                            @else
                                <span class="text-sm font-bold text-amber-600 dark:text-amber-400 tabular-nums">{{ $line['received'] }}/{{ $line['case_count'] }}</span>
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
                            <span class="inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900 px-2 py-0.5 text-[11px] font-medium text-amber-700 dark:text-amber-400">
                                <x-heroicon-o-exclamation-circle class="h-3 w-3 mr-1" /> Needs mapping
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
                    <div class="col-span-1 text-center text-sm {{ $mapped ? 'text-gray-500' : 'text-amber-600 dark:text-amber-400' }} tabular-nums font-medium">{{ $line['case_count'] }}</div>
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
                            <span class="text-xs text-amber-600 dark:text-amber-400 font-medium">⚠ Map first</span>
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
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
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
                <p class="text-xs text-gray-400">Photos, documents, and receipt images. You can edit the pallet to upload files.</p>

                @if ($this->record->attachments()->count() > 0)
                    <div class="space-y-1.5">
                        @foreach ($this->record->attachments as $att)
                            <div class="flex items-center gap-2 p-2 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                @if ($att->isImage())
                                    <x-heroicon-o-photo class="h-4 w-4 text-blue-500" />
                                @elseif ($att->isPdf())
                                    <x-heroicon-o-document-text class="h-4 w-4 text-red-500" />
                                @else
                                    <x-heroicon-o-paperclip class="h-4 w-4 text-gray-400" />
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

                <a href="{{ PalletResource::getUrl('edit', ['record' => $this->record]) }}" class="inline-flex items-center gap-2 text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline">
                    <x-heroicon-o-pencil class="h-3 w-3" /> Add files
                </a>
            </div>

            {{-- Receiver Info --}}
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-5 space-y-3">
                <div class="flex items-center gap-3">
                    <x-heroicon-o-user class="h-5 w-5 text-gray-500" />
                    <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Receiver Info</h2>
                </div>

                <div>
                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Name of Person Receiving
                    </label>
                    <input
                        wire:model="receivedByName"
                        type="text"
                        placeholder="Enter receiver name…"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-900 px-4 py-2 text-sm text-gray-900 dark:text-gray-100 placeholder-gray-400 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 focus:outline-none"
                    />
                </div>

                @if ($receivedByName)
                    <div class="text-xs text-gray-500 bg-blue-50 dark:bg-blue-950 rounded-lg p-2 border-l-2 border-blue-300 dark:border-blue-700">
                        ✓ Will save as receiver: <span class="font-semibold">{{ $receivedByName }}</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- ── Finalize Actions ─────────────────────────────────────────────── --}}
        @if (collect($lineProgress)->sum('received') >= collect($lineProgress)->sum('case_count') && collect($lineProgress)->count() > 0)
            <div class="rounded-xl border-2 border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-950 shadow-sm px-6 py-5 space-y-3">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-green-500 mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <h2 class="text-sm font-semibold text-green-900 dark:text-green-100">All cases received! 🎉</h2>
                        <p class="text-xs text-green-700 dark:text-green-200 mt-0.5">
                            You've received all expected cases for this pallet. Enter the receiver name above, then finalize to complete.
                        </p>
                    </div>
                    @if ($receivedByName)
                        <button
                            wire:click="finalizePallet"
                            type="button"
                            class="shrink-0 rounded-lg bg-green-600 px-4 py-2 text-sm font-medium text-white hover:bg-green-700 active:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-500"
                        >
                            Finalize Pallet
                        </button>
                    @else
                        <span class="shrink-0 rounded-lg bg-gray-200 dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                            Complete name above
                        </span>
                    @endif
                </div>
            </div>
        @endif

        {{-- ── Quick Tips ───────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-blue-50 dark:bg-blue-950/20 shadow-sm px-6 py-4">
            <div class="flex items-start gap-3">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-blue-600 dark:text-blue-400 mt-0.5 shrink-0" />
                <div class="flex-1 text-sm space-y-1">
                    <p class="font-semibold text-blue-900 dark:text-blue-100">Quick Tips</p>
                    <ul class="text-xs text-blue-800 dark:text-blue-200 space-y-0.5">
                        <li>💡 <strong>Scan cases first</strong> to see what's inside (case barcodes show contents)</li>
                        <li>💡 <strong>Scan individual items</strong> to receive them one-by-one or use "Receive All" for a line</li>
                        <li>💡 <strong>Map lines early</strong> — unmapped lines block receiving</li>
                        <li>💡 <strong>Mobile friendly</strong> — use your phone's camera with a barcode app for scanning</li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
