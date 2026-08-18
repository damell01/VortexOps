<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Workflow Progress ────────────────────────────────────────── --}}
        @php
            $phases = App\Models\Pallet::statusPhases();
            $currentPhase = $phases[$this->record->status]['number'] ?? 0;
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-4">
            <div class="flex items-center gap-3 mb-4">
                <x-heroicon-o-check-badge class="h-5 w-5 text-gray-500" />
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Pallet Status</h2>
            </div>
            <div class="space-y-3">
                <div class="flex items-center gap-2 text-sm flex-wrap">
                    @foreach ($phases as $status => $phase)
                        @php
                            $isActive = $status === $this->record->status;
                            $isComplete = $phase['number'] < $currentPhase;
                        @endphp
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium
                            {{ $isActive ? 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300' : ($isComplete ? 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300' : 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400') }}">
                            @if ($isComplete)
                                <x-heroicon-o-check-circle class="h-4 w-4" />
                            @elseif ($isActive)
                                <x-heroicon-o-ellipsis-horizontal-circle class="h-4 w-4 animate-pulse" />
                            @else
                                <x-heroicon-o-check-circle class="h-4 w-4" />
                            @endif
                            {{ $phase['label'] }}
                        </span>
                        @if (!$loop->last)
                            <x-heroicon-o-arrow-right class="h-4 w-4 text-gray-400 hidden sm:inline" />
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ── Pallet Header Summary ──────────────────────────────────────────── --}}
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
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Status</p>
                    @php
                        $statusColors = [
                            'pending'   => 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400',
                            'shipped'   => 'bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300',
                            'receiving' => 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300',
                            'received'  => 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300',
                            'processed' => 'bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300',
                        ];
                    @endphp
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {{ $statusColors[$this->record->status] ?? 'bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400' }}">
                        {{ \App\Models\Pallet::statusLabels()[$this->record->status] ?? ucfirst($this->record->status) }}
                    </span>
                </div>
                <div class="ml-auto">
                    <p class="text-xs text-gray-400 uppercase tracking-wide">Total Cost</p>
                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                        {{ $this->record->total_cost ? '$' . number_format($this->record->total_cost, 2) : '—' }}
                    </p>
                </div>
            </div>
        </div>

        {{-- ── Main Pallet Details (from form) ──────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm px-6 py-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Received Date</p>
                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ $this->record->received_date?->format('M d, Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Shipping Cost</p>
                    <p class="text-sm text-gray-900 dark:text-gray-100">
                        {{ $this->record->shipping_cost ? '$' . number_format($this->record->shipping_cost, 2) : '—' }}
                    </p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Carrier</p>
                    <p class="text-sm text-gray-900 dark:text-gray-100">{{ $this->record->carrier ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Tracking #</p>
                    <p class="text-sm text-gray-900 dark:text-gray-100 font-mono">{{ $this->record->tracking_number ?? '—' }}</p>
                </div>
            </div>
            @if ($this->record->notes)
                <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-800">
                    <p class="text-xs text-gray-400 uppercase tracking-wide mb-1">Notes</p>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $this->record->notes }}</p>
                </div>
            @endif
        </div>

        {{-- ── Expected items ───────────────────────────────────────────────────
             What this pallet is meant to contain, and how much of it has been
             confirmed. Staging builds the list before the pallet lands; each
             scan on arrival ticks one case off it, so a part delivery reads as
             "three of five" rather than as either done or not started. --}}
        @php
            $vxLines = $this->record->lines;
            $vxCasesExpected = $vxLines->sum(fn ($l) => (int) $l->case_count);
            $vxCasesIn       = $vxLines->sum(fn ($l) => $l->cases->where('status', '!=', 'expected')->count());
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                    Expected Items ({{ $vxLines->count() }})
                </h2>
                @if ($vxCasesExpected > 0)
                    <span class="text-xs {{ $vxCasesIn >= $vxCasesExpected ? 'text-green-600 dark:text-green-400 font-medium' : 'text-gray-400' }}">
                        {{ $vxCasesIn }} of {{ $vxCasesExpected }} cases confirmed
                    </span>
                @endif
            </div>

            @if ($vxLines->isEmpty())
                <div class="px-4 py-8 text-center">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Nothing staged on this pallet yet.</p>
                    <p class="text-xs text-gray-400 mt-1">
                        Use <span class="font-medium">Add Expected Item</span> to build the list, or upload a manifest.
                    </p>
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm vx-cardify">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-400 border-b border-gray-100 dark:border-gray-800">
                                <th class="px-4 py-2 font-medium">Item</th>
                                <th class="px-4 py-2 font-medium">Location</th>
                                <th class="px-4 py-2 font-medium">Progress</th>
                                <th class="px-4 py-2 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vxLines as $vxLine)
                                @php
                                    $vxIn       = $vxLine->cases->where('status', '!=', 'expected')->count();
                                    $vxTotal    = (int) $vxLine->case_count;
                                    $vxMapped   = $vxLine->isFullyMapped();
                                    $vxComplete = $vxTotal > 0 && $vxIn >= $vxTotal;
                                @endphp
                                <tr class="border-b border-gray-50 dark:border-gray-800/60 last:border-0">
                                    <td class="px-4 py-2.5">
                                        <p class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ $vxLine->inventoryItem?->name ?? $vxLine->description }}
                                        </p>
                                        <p class="text-xs text-gray-400">
                                            {{ $vxLine->inventoryItem?->sku ?: 'No SKU' }}
                                            · {{ number_format($vxLine->quantity_per_case) }}/case
                                            · ${{ number_format((float) $vxLine->unit_cost, 2) }} each
                                        </p>
                                    </td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-300">
                                        {{ $vxLine->location?->name ?? '—' }}
                                    </td>
                                    <td class="px-4 py-2.5">
                                        <span class="text-gray-900 dark:text-gray-100 font-medium">{{ $vxIn }}</span>
                                        <span class="text-gray-400">/ {{ $vxTotal }} cases</span>
                                    </td>
                                    <td class="px-4 py-2.5">
                                        @if (! $vxMapped)
                                            {{-- Unmapped cannot be scanned in: there is nowhere to put it. --}}
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-amber-100 dark:bg-amber-900 text-amber-700 dark:text-amber-300">
                                                Needs mapping
                                            </span>
                                        @elseif ($vxComplete)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-300">
                                                Complete
                                            </span>
                                        @elseif ($vxIn > 0)
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300">
                                                Partial
                                            </span>
                                        @else
                                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400">
                                                Awaiting
                                            </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        {{-- ── Landed cost ──────────────────────────────────────────────────────
             What the stock on this pallet actually costs. Goods are what the
             lines add up to; shipping and fees are spread across the units by
             quantity at receiving, so they belong in the same total rather
             than sitting somewhere else as a separate expense. --}}
        @php
            $vxGoods    = $this->record->lines->sum(fn ($l) => (float) $l->unit_cost * (float) $l->quantity_per_case * (int) $l->case_count);
            $vxUnits    = $this->record->lines->sum(fn ($l) => (float) $l->quantity_per_case * (int) $l->case_count);
            $vxExtras   = $this->record->landedCostExtras();
            $vxLanded   = $vxGoods + $vxExtras;
        @endphp
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 dark:border-gray-800 flex items-center justify-between">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Landed Cost</h2>
                @if ($vxUnits > 0)
                    <span class="text-xs text-gray-400">
                        {{ number_format($vxUnits) }} units · ${{ number_format($vxLanded / $vxUnits, 2) }} each
                    </span>
                @endif
            </div>
            <dl class="px-4 py-3 space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Goods</dt>
                    <dd class="text-gray-900 dark:text-gray-100">${{ number_format($vxGoods, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Shipping</dt>
                    <dd class="text-gray-900 dark:text-gray-100">${{ number_format((float) $this->record->shipping_cost, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Payment fees</dt>
                    <dd class="text-gray-900 dark:text-gray-100">${{ number_format((float) $this->record->payment_fees, 2) }}</dd>
                </div>
                <div class="flex justify-between pt-2 border-t border-gray-100 dark:border-gray-800 font-semibold">
                    <dt class="text-gray-900 dark:text-gray-100">Total</dt>
                    <dd class="text-gray-900 dark:text-gray-100">${{ number_format($vxLanded, 2) }}</dd>
                </div>
            </dl>
            @if ($vxExtras > 0 && $this->record->status !== 'received')
                <p class="px-4 pb-3 text-xs text-gray-400">
                    Shipping and fees are added to each item's cost when this pallet is received.
                </p>
            @endif
        </div>

        {{-- ── Media Attachments Section ───────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-800">
                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                    <x-heroicon-o-camera class="h-5 w-5 text-gray-500" />
                    Media & Attachments
                </h2>
                <p class="text-xs text-gray-400 mt-1">Photos, documents, and signatures tied to this pallet receipt</p>
            </div>

            @if ($this->record->attachments()->count() > 0)
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($this->record->attachments as $attachment)
                        <div class="px-6 py-4 hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                @if ($attachment->isImage())
                                    <x-heroicon-o-photo class="h-5 w-5 text-blue-500 shrink-0" />
                                @elseif ($attachment->isPdf())
                                    <x-heroicon-o-document-text class="h-5 w-5 text-red-500 shrink-0" />
                                @else
                                    <x-heroicon-o-link class="h-5 w-5 text-gray-400 shrink-0" />
                                @endif
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100 truncate">
                                        {{ $attachment->file_name }}
                                    </p>
                                    <p class="text-xs text-gray-400 mt-0.5">
                                        {{ \App\Models\PalletAttachment::typeLabels()[$attachment->type] ?? 'File' }}
                                        · {{ number_format($attachment->file_size / 1024, 1) }}KB
                                        · {{ $attachment->uploaded_at?->format('M d, Y g:i A') }}
                                    </p>
                                </div>
                            </div>
                            @if ($attachment->isImage())
                                <a href="{{ $attachment->getFileUrl() }}" target="_blank" class="text-xs font-medium text-blue-600 dark:text-blue-400 hover:underline whitespace-nowrap">
                                    View
                                </a>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="px-6 py-10 text-center">
                    <x-heroicon-o-camera class="h-8 w-8 mx-auto text-gray-300 dark:text-gray-600 mb-2" />
                    <p class="text-sm text-gray-500 dark:text-gray-400">No attachments yet</p>
                    <p class="text-xs text-gray-400 mt-1">Upload photos and documents to track receiving evidence</p>
                </div>
            @endif

            <div class="px-6 py-3 bg-gray-50 dark:bg-gray-800 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500">
                📷 Use <span class="font-medium">Add Photos / Documents</span> at the top of this page — it can open your camera.
            </div>
        </div>

        {{-- ── Receiving Details (if received) ───────────────────────────────── --}}
        @if (in_array($this->record->status, ['received', 'processed']))
            <div class="rounded-xl border border-green-200 dark:border-green-800 bg-green-50 dark:bg-green-950/30 shadow-sm px-6 py-4 space-y-3">
                <div class="flex items-start gap-3">
                    <x-heroicon-o-check-circle class="h-5 w-5 text-green-600 dark:text-green-400 mt-0.5 shrink-0" />
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-green-900 dark:text-green-100">Pallet Received</h3>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-green-800 dark:text-green-200">
                            @if ($this->record->received_by_name)
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Received by:</p>
                                    <p class="font-medium">{{ $this->record->received_by_name }}</p>
                                </div>
                            @endif
                            @if ($this->record->signature_timestamp)
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Signed at:</p>
                                    <p class="font-medium">{{ $this->record->signature_timestamp->format('M d, Y g:i A') }}</p>
                                </div>
                            @endif
                            @if ($this->record->attachments_count > 0)
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Attachments:</p>
                                    <p class="font-medium">{{ $this->record->attachments_count }} file{{ $this->record->attachments_count !== 1 ? 's' : '' }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

    </div>
</x-filament-panels::page>
