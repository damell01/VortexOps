@php
    /** @var \App\Models\StreamerLogEntry|null $log */
    $log = $this->getLog();
    $problems = $this->problems;
    $statusColour = match ((string) ($log?->status ?? '')) {
        'admin_approved' => 'success',
        'streamer_reviewed' => 'warning',
        default => 'gray',
    };
@endphp

<x-filament-widgets::widget>
    <div class="space-y-4">
        <section class="rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-4 border-b border-gray-100 p-5 sm:flex-row sm:items-start sm:justify-between dark:border-gray-800">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="font-semibold text-gray-950 dark:text-white">Streamer Show Report</h2>
                        @if($log)
                            <x-filament::badge :color="$statusColour">{{ \App\Models\StreamerLogEntry::statusLabels()[$log->status] ?? str($log->status)->replace('_',' ')->title() }}</x-filament::badge>
                        @endif
                    </div>
                    @if($log)
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $log->streamer?->name ?? 'Unknown streamer' }}
                            @if($log->submitted_at) · submitted {{ $log->submitted_at->format('M j, Y g:i A') }} @endif
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No streamer report has been started for this show yet.</p>
                    @endif
                </div>

                @if($log)
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $log->show_id]) }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">Open Full Report</a>
                        @if($log->status === 'streamer_reviewed')
                            <button type="button" wire:click="approveReport" wire:confirm="Approve this streamer report?" class="rounded-lg bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-500">Approve</button>
                            <button type="button" wire:click="toggleRejectForm" class="rounded-lg border border-red-300 px-3 py-2 text-sm font-medium text-red-700 dark:border-red-800 dark:text-red-300">Request Changes</button>
                        @endif
                    </div>
                @endif
            </div>

            @if($log)
                @php
                    $items = $log->items;
                    $sold = (int)$items->where('disposition','sold')->sum('quantity');
                    $giveaways = (int)$items->where('disposition','giveaway')->sum('quantity');
                    $promo = (int)$items->where('disposition','promo')->sum('quantity');
                    $unmatched = $items->whereNull('inventory_item_id')->count();
                @endphp

                <div class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-5 dark:bg-gray-800">
                    @foreach ([
                        ['Reported Units', (int)$items->sum('quantity')],
                        ['Sold', $sold],
                        ['Giveaways', $giveaways],
                        ['Promo', $promo],
                        ['Unmatched', $unmatched],
                    ] as [$label, $value])
                        <div class="bg-white p-4 dark:bg-gray-900">
                            <div class="text-xs text-gray-500">{{ $label }}</div>
                            <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                        </div>
                    @endforeach
                </div>

                @if($this->showRejectForm)
                    <div class="border-t border-red-100 bg-red-50 p-5 dark:border-red-900 dark:bg-red-950/20">
                        <label class="block text-sm font-medium text-red-900 dark:text-red-100">What needs to be corrected?</label>
                        <textarea wire:model="rejectionNotes" rows="3" class="mt-2 w-full rounded-xl border-red-200 dark:border-red-800 dark:bg-gray-900" placeholder="Tell the streamer exactly what needs to change…"></textarea>
                        <div class="mt-3 flex justify-end gap-2">
                            <button type="button" wire:click="toggleRejectForm" class="rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700">Cancel</button>
                            <button type="button" wire:click="rejectReport" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white">Send Back</button>
                        </div>
                    </div>
                @endif

                @if($problems)
                    <div class="border-t border-amber-100 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                        <div class="text-sm font-semibold text-amber-900 dark:text-amber-100">Inventory reconciliation</div>
                        <ul class="mt-2 space-y-1 text-sm text-amber-700 dark:text-amber-300">
                            @foreach($problems as $problem)<li>• {{ $problem }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div class="space-y-3 p-5">
                    @forelse($items as $line)
                        <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $line->item_name }}</div>
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $line->dispositionLabel() }}</span>
                                        @if(!$line->inventory_item_id)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200">Unmatched</span>
                                        @elseif((int)$line->deducted_quantity >= (int)$line->quantity)
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700 dark:bg-green-950 dark:text-green-200">Posted</span>
                                        @endif
                                    </div>
                                    <div class="mt-1 text-xs text-gray-500">
                                        Qty {{ $line->quantity }} · Cost ${{ number_format((float)$line->unit_cost, 2) }} each
                                        @if($line->inventoryItem) · matched to {{ $line->inventoryItem->name }} @endif
                                    </div>
                                </div>
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">${{ number_format($line->total_cost, 2) }}</div>
                            </div>

                            @if(!$line->inventory_item_id)
                                @livewire('admin-match-show-item', ['line' => $line], key('admin-match-show-item-'.$line->id))
                            @endif
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700">The report does not contain any inventory lines yet.</div>
                    @endforelse
                </div>

                @if($log->approval_notes)
                    <div class="border-t border-gray-100 p-5 text-sm dark:border-gray-800">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Review Notes</div>
                        <p class="mt-2 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $log->approval_notes }}</p>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-filament-widgets::widget>
