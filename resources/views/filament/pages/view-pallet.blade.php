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
                                <x-heroicon-o-circle class="h-4 w-4" />
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
                                    <x-heroicon-o-paperclip class="h-5 w-5 text-gray-400 shrink-0" />
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
                📝 Use the Edit view to upload new attachments
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
