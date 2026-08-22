@php
    /** @var \App\Models\StreamerLogEntry|null $log */
    $log = $this->getLog();
    $problems = $this->problems;
    $statusColour = match ((string) ($log?->status ?? '')) {
        'admin_approved' => 'success',
        'streamer_reviewed' => 'warning',
        'changes_requested' => 'danger',
        default => 'gray',
    };
@endphp

<x-filament-widgets::widget>
    <div class="space-y-3" data-vx-page="show-report-review">
        <section data-vx-tour="show-report" class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="border-b border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Streamer Show Report</h2>
                            @if($log)
                                <x-filament::badge :color="$statusColour">{{ \App\Models\StreamerLogEntry::statusLabels()[$log->status] ?? str($log->status)->replace('_',' ')->title() }}</x-filament::badge>
                            @endif
                        </div>
                        @if($log)
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">
                                {{ $log->streamer?->name ?? 'Unknown streamer' }}
                                @if($log->submitted_at) · submitted {{ $log->submitted_at->format('M j, g:i A') }} @endif
                            </p>
                        @else
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">No streamer report has been started for this show yet.</p>
                        @endif
                    </div>

                    @if($log)
                        <div class="grid grid-cols-1 gap-2 xs:grid-cols-2 sm:flex sm:flex-wrap">
                            <a href="{{ \App\Filament\Pages\EndOfStreamForm::getUrl(['showId' => $log->show_id]) }}" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 px-3 py-2 text-xs font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200 sm:text-sm">Open Full Report</a>
                            @if($log->status === 'streamer_reviewed')
                                <button type="button" wire:click="approveReport" wire:confirm="Approve this streamer report?" class="inline-flex min-h-10 items-center justify-center rounded-lg bg-green-600 px-3 py-2 text-xs font-semibold text-white hover:bg-green-500 sm:text-sm">Approve</button>
                                <button type="button" wire:click="toggleRejectForm" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-red-300 px-3 py-2 text-xs font-medium text-red-700 dark:border-red-800 dark:text-red-300 sm:text-sm">Request Changes</button>
                            @endif
                        </div>
                    @endif
                </div>

                @if($log)
                    <div class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-[11px] leading-4 text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                        <strong class="text-gray-700 dark:text-gray-200">Admin flow:</strong> review exceptions → match any unlisted items → approve when the report is correct. Posted lines are already reflected in inventory and will not deduct twice.
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

                <div class="grid grid-cols-2 gap-px bg-gray-100 dark:bg-gray-800 sm:grid-cols-5">
                    @foreach ([
                        ['Units', (int)$items->sum('quantity')],
                        ['Sold', $sold],
                        ['Giveaways', $giveaways],
                        ['Promo', $promo],
                        ['Unmatched', $unmatched],
                    ] as [$label, $value])
                        <div class="bg-white px-3 py-3 dark:bg-gray-900 sm:p-4">
                            <div class="text-[10px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">{{ $label }}</div>
                            <div class="mt-0.5 text-lg font-semibold leading-none text-gray-950 dark:text-white sm:mt-1 sm:text-xl">{{ number_format($value) }}</div>
                        </div>
                    @endforeach
                </div>

                @if($this->showRejectForm)
                    <div class="border-t border-red-100 bg-red-50 p-4 dark:border-red-900 dark:bg-red-950/20 sm:p-5">
                        <label class="block text-sm font-medium text-red-900 dark:text-red-100">What should the streamer correct?</label>
                        <textarea wire:model="rejectionNotes" rows="3" class="mt-2 w-full rounded-xl border-red-200 text-sm dark:border-red-800 dark:bg-gray-900" placeholder="Be specific so they know exactly what to change…"></textarea>
                        <div class="mt-3 grid grid-cols-2 gap-2 sm:flex sm:justify-end">
                            <button type="button" wire:click="toggleRejectForm" class="min-h-10 rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-700">Cancel</button>
                            <button type="button" wire:click="rejectReport" class="min-h-10 rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white">Send Back</button>
                        </div>
                    </div>
                @endif

                @if($problems)
                    <div class="border-t border-amber-100 bg-amber-50 p-3.5 dark:border-amber-900 dark:bg-amber-950/20 sm:p-4">
                        <div class="flex items-center gap-2 text-sm font-semibold text-amber-900 dark:text-amber-100">
                            <x-heroicon-m-exclamation-triangle class="h-4 w-4 shrink-0" />
                            Inventory needs attention
                        </div>
                        <ul class="mt-2 space-y-1 text-xs leading-5 text-amber-700 dark:text-amber-300 sm:text-sm">
                            @foreach($problems as $problem)<li>• {{ $problem }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <div data-vx-tour="show-report-lines" class="space-y-2 p-3 sm:space-y-3 sm:p-5">
                    @forelse($items as $line)
                        <article class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="font-medium leading-5 text-gray-950 dark:text-white">{{ $line->item_name }}</div>
                                    <div class="mt-1 flex flex-wrap gap-1.5">
                                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-200 sm:text-xs">{{ $line->dispositionLabel() }}</span>
                                        @if(!$line->inventory_item_id)
                                            <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200 sm:text-xs">Needs match</span>
                                        @elseif((int)$line->deducted_quantity >= (int)$line->quantity)
                                            <span class="rounded-full bg-green-100 px-2 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-950 dark:text-green-200 sm:text-xs">Posted</span>
                                        @else
                                            <span class="rounded-full bg-blue-100 px-2 py-0.5 text-[10px] font-medium text-blue-700 dark:bg-blue-950 dark:text-blue-200 sm:text-xs">Matched</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="shrink-0 text-right">
                                    <div class="text-sm font-semibold text-gray-950 dark:text-white">${{ number_format($line->total_cost, 2) }}</div>
                                    <div class="mt-0.5 text-[10px] text-gray-400">line cost</div>
                                </div>
                            </div>

                            <div class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-gray-50 px-3 py-2 text-[11px] text-gray-500 dark:bg-gray-800/50 dark:text-gray-400 sm:flex sm:flex-wrap sm:gap-4">
                                <span><strong class="text-gray-700 dark:text-gray-200">Qty</strong> {{ $line->quantity }}</span>
                                <span><strong class="text-gray-700 dark:text-gray-200">Each</strong> ${{ number_format((float)$line->unit_cost, 2) }}</span>
                                @if($line->inventoryItem)<span class="col-span-2 truncate sm:col-auto"><strong class="text-gray-700 dark:text-gray-200">Inventory</strong> {{ $line->inventoryItem->name }}</span>@endif
                            </div>

                            @if(!$line->inventory_item_id)
                                @livewire('admin-match-show-item', ['line' => $line], key('admin-match-show-item-'.$line->id))
                            @endif
                        </article>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-700">
                            <div class="text-sm font-medium text-gray-700 dark:text-gray-200">No inventory lines yet</div>
                            <p class="mt-1 text-xs text-gray-500">The streamer has not added items to this report.</p>
                        </div>
                    @endforelse
                </div>

                @if($log->approval_notes)
                    <div class="border-t border-gray-100 p-4 text-sm dark:border-gray-800 sm:p-5">
                        <div class="text-[10px] font-bold uppercase tracking-wide text-gray-500 sm:text-xs">Review Notes</div>
                        <p class="mt-2 whitespace-pre-line text-xs leading-5 text-gray-700 dark:text-gray-300 sm:text-sm">{{ $log->approval_notes }}</p>
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-filament-widgets::widget>
