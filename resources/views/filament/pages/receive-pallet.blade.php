<x-filament-panels::page>
    @php
        $summaryExpected = collect($lineProgress)->sum('case_count');
        $summaryReceived = collect($lineProgress)->sum('received');
        $summaryRemaining = max(0, $summaryExpected - $summaryReceived);
        $summaryPct = $summaryExpected > 0 ? min(100, round(($summaryReceived / $summaryExpected) * 100)) : 0;
        $summaryDone = $summaryExpected > 0 && $summaryReceived >= $summaryExpected;
        $unmappedLines = collect($lineProgress)->filter(fn ($line) => ! $line['mapped'])->values();
        $incompleteLines = collect($lineProgress)->filter(fn ($line) => $line['received'] < $line['case_count'])->values();
    @endphp

    <div
        class="mx-auto max-w-7xl space-y-3 pb-24 sm:space-y-5 sm:pb-0"
        data-vx-page="pallet-receive"
        x-data="{
            openScanner() { window.dispatchEvent(new CustomEvent('open-camera-scanner')); },
            submitCameraScan(event) {
                const value = event?.detail?.value;
                if (!value) return;
                $wire.set('barcodeInput', value).then(() => $wire.submitBarcode());
            }
        }"
        x-on:scan-line-targeted.window="openScanner()"
        x-on:barcode-scanned.window="submitCameraScan($event)"
    >
        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="p-4 sm:p-5">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Receiving Station</div>
                        <h2 class="mt-1 truncate text-lg font-semibold text-gray-950 dark:text-white sm:text-xl">{{ $this->record->displayName() }}</h2>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                            {{ $this->record->vendor?->name ?? 'No vendor' }}
                            @if($this->record->reference) · {{ $this->record->reference }} @endif
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full px-2.5 py-1 text-[10px] font-semibold sm:text-xs {{ $summaryDone ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200' }}">
                        {{ $summaryDone ? 'Ready to complete' : 'Receiving' }}
                    </span>
                </div>

                <div class="mt-4 grid grid-cols-3 gap-2 sm:mt-5 sm:gap-3">
                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800 sm:rounded-xl sm:p-4">
                        <div class="text-[9px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Expected</div>
                        <div class="mt-0.5 text-xl font-semibold text-gray-950 dark:text-white sm:text-2xl">{{ number_format($summaryExpected) }}</div>
                    </div>
                    <div class="rounded-lg bg-green-50 p-3 dark:bg-green-950/30 sm:rounded-xl sm:p-4">
                        <div class="text-[9px] font-medium uppercase tracking-wide text-green-600 sm:text-xs sm:normal-case sm:tracking-normal">Received</div>
                        <div class="mt-0.5 text-xl font-semibold text-green-700 dark:text-green-200 sm:text-2xl">{{ number_format($summaryReceived) }}</div>
                    </div>
                    <div class="rounded-lg bg-amber-50 p-3 dark:bg-amber-950/30 sm:rounded-xl sm:p-4">
                        <div class="text-[9px] font-medium uppercase tracking-wide text-amber-600 sm:text-xs sm:normal-case sm:tracking-normal">Remaining</div>
                        <div class="mt-0.5 text-xl font-semibold text-amber-700 dark:text-amber-200 sm:text-2xl">{{ number_format($summaryRemaining) }}</div>
                    </div>
                </div>

                <div class="mt-3 sm:mt-4">
                    <div class="flex items-center justify-between text-[10px] text-gray-500 sm:text-xs">
                        <span>{{ $summaryPct }}% complete</span>
                        <span>{{ $this->record->lines->count() }} manifest {{ \Illuminate\Support\Str::plural('line', $this->record->lines->count()) }}</span>
                    </div>
                    <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-full rounded-full {{ $summaryDone ? 'bg-green-500' : 'bg-primary-600' }} transition-all" style="width: {{ $summaryPct }}%"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 dark:border-primary-900 dark:bg-primary-950/20 sm:rounded-2xl sm:p-5">
            <div class="flex items-start gap-3">
                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-600 text-white"><x-heroicon-o-qr-code class="h-5 w-5" /></div>
                <div class="min-w-0 flex-1">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Scan what is in your hand</h3>
                    <p class="mt-0.5 text-[11px] leading-5 text-gray-600 dark:text-gray-300 sm:text-xs">For a new manifest line, tap that line's <strong>Scan</strong> button first. The barcode becomes its inventory mapping and the box is received in the same step.</p>
                </div>
            </div>

            <div class="mt-3 grid gap-2 sm:mt-4 sm:grid-cols-[minmax(0,1fr)_auto_auto]">
                <input wire:model="barcodeInput" wire:keydown.enter="submitBarcode" id="receiving-barcode-input" type="text" inputmode="numeric" autocomplete="off" placeholder="Scan or type UPC / barcode…" class="min-h-11 w-full rounded-lg border-gray-300 bg-white px-3 text-base font-mono dark:border-gray-600 dark:bg-gray-900 sm:text-sm" />
                <button type="button" @click="openScanner()" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg border border-primary-300 bg-white px-4 text-sm font-semibold text-primary-700 dark:border-primary-800 dark:bg-gray-900 dark:text-primary-300"><x-heroicon-o-camera class="h-5 w-5" /> Camera</button>
                <button type="button" wire:click="submitBarcode" wire:loading.attr="disabled" wire:target="submitBarcode" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white disabled:opacity-60"><span wire:loading.remove wire:target="submitBarcode">Receive Scan</span><span wire:loading wire:target="submitBarcode">Checking…</span></button>
            </div>

            @if($targetLineId)
                {{--
                    Block form deliberately. The inline php() directive is
                    collected by the same raw-block pass that handles the block
                    form, so written inline here it paired itself with the
                    closing tag of the manifest loop forty lines below and
                    swallowed everything in between — the closing tag of the
                    conditional above included. That is why this view compiled
                    to PHP with an unclosed if, and the receiving page 500'd.

                    Note also that directive names cannot be written literally
                    in a comment inside the block form: the raw-block pass is
                    text, not syntax, and a closing tag mentioned in a comment
                    ends the block exactly as a real one would.
                --}}
                @php
                    $target = collect($lineProgress)->firstWhere('id', $targetLineId);
                @endphp
                @if($target)
                    <div class="mt-3 flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-xs dark:bg-gray-900">
                        <span class="min-w-0 truncate text-gray-600 dark:text-gray-300"><strong class="text-primary-700 dark:text-primary-300">Next scan:</strong> L{{ $target['line_number'] }} · {{ $target['description'] }}</span>
                        <button type="button" wire:click="targetLine({{ $targetLineId }})" class="shrink-0 font-semibold text-gray-400">Clear</button>
                    </div>
                @endif
            @endif

            @if($lastScannedResult)
                <div class="mt-3 rounded-lg px-3 py-2.5 text-xs font-medium sm:text-sm {{ $lastScanSuccess ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' }}">{{ $lastScannedResult }}</div>
            @endif

            @if($pendingCode)
                <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                    <div class="text-xs font-semibold text-amber-900 dark:text-amber-100 sm:text-sm">Which manifest line is <span class="font-mono">{{ $pendingCode }}</span>?</div>
                    <p class="mt-1 text-[10px] leading-4 text-amber-700 dark:text-amber-300 sm:text-xs">The scan worked. Pick the correct line once; you do not need to scan the box again.</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach($this->pendingChoices() as $choice)
                            <button type="button" wire:click="assignPendingTo({{ $choice->id }})" class="min-h-10 rounded-lg bg-amber-600 px-3 text-xs font-semibold text-white sm:text-sm">L{{ $choice->line_number }} · {{ $choice->description }}</button>
                        @endforeach
                        <button type="button" wire:click="discardPending" class="min-h-10 rounded-lg border border-amber-300 px-3 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:text-amber-300">None</button>
                    </div>
                </div>
            @endif
        </section>

        @if($unmappedLines->isNotEmpty())
            <section class="rounded-xl border border-amber-200 bg-amber-50/50 p-3.5 dark:border-amber-900 dark:bg-amber-950/20 sm:p-4">
                <div class="flex items-start gap-2.5"><x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" /><div><div class="text-xs font-semibold text-amber-900 dark:text-amber-100 sm:text-sm">{{ $unmappedLines->count() }} {{ \Illuminate\Support\Str::plural('line', $unmappedLines->count()) }} need a first barcode</div><p class="mt-0.5 text-[10px] leading-4 text-amber-700 dark:text-amber-300 sm:text-xs">This is normal for products staged from paperwork. Tap <strong>Scan</strong> on the line while holding its box; the first scan links/creates the inventory item and receives one box.</p></div></div>
            </section>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
            <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5 sm:py-4">
                <div><h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Manifest</h3><p class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400 sm:text-xs">Scan one box at a time, or Receive All only after physically counting the entire mapped line.</p></div>
                <span class="shrink-0 rounded-full bg-gray-100 px-2 py-1 text-[10px] font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300 sm:text-xs">{{ $incompleteLines->count() }} open</span>
            </div>

            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse($lineProgress as $line)
                    @php
                        $done = $line['case_count'] > 0 && $line['received'] >= $line['case_count'];
                        $pct = $line['case_count'] > 0 ? min(100, round(($line['received'] / $line['case_count']) * 100)) : 0;
                    @endphp
                    <article wire:key="receiving-line-{{ $line['id'] }}" class="p-3.5 sm:p-4 {{ $done ? 'bg-green-50/30 dark:bg-green-950/10' : '' }}">
                        <div class="flex items-start gap-3">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $done ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : ($line['mapped'] ? 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200') }}">
                                @if($done)<x-heroicon-m-check class="h-5 w-5" />@elseif($line['mapped'])<x-heroicon-o-cube class="h-5 w-5" />@else<x-heroicon-o-qr-code class="h-5 w-5" />@endif
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="text-[10px] font-medium text-gray-400">LINE {{ $line['line_number'] }}</div>
                                        <div class="mt-0.5 text-sm font-semibold leading-snug text-gray-950 dark:text-white sm:text-base">{{ $line['description'] }}</div>
                                        <div class="mt-1 flex flex-wrap gap-x-2 gap-y-1 text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">
                                            @if($line['item_name'])<span class="font-medium text-primary-600">{{ $line['item_name'] }}</span>@else<span class="font-medium text-amber-600">UPC needed — first scan will add/link it</span>@endif
                                            @if($line['location'])<span>· {{ $line['location'] }}</span>@endif
                                        </div>
                                    </div>
                                    <div class="shrink-0 text-right"><div class="text-base font-bold text-gray-950 dark:text-white sm:text-lg">{{ $line['received'] }}/{{ $line['case_count'] }}</div><div class="text-[9px] text-gray-400 sm:text-[10px]">boxes</div></div>
                                </div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800"><div class="h-full rounded-full {{ $done ? 'bg-green-500' : 'bg-primary-600' }}" style="width: {{ $pct }}%"></div></div>
                                @unless($done)
                                    <div class="mt-3 grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                                        <button type="button" wire:click="targetLine({{ $line['id'] }})" wire:loading.attr="disabled" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 text-xs font-semibold text-white disabled:opacity-60 sm:text-sm"><x-heroicon-o-camera class="h-4 w-4" /> Scan</button>
                                        @if($line['mapped'])
                                            <button type="button" wire:click="receiveLine({{ $line['id'] }})" wire:confirm="Receive every remaining box on this line? Only continue if you physically counted them." wire:loading.attr="disabled" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-xs font-semibold text-gray-700 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 sm:text-sm">Receive All</button>
                                        @else
                                            <span class="inline-flex min-h-10 items-center justify-center rounded-lg bg-amber-50 px-3 text-center text-[10px] font-medium text-amber-700 dark:bg-amber-950/30 dark:text-amber-200 sm:text-xs">Scan UPC first</span>
                                        @endif
                                    </div>
                                @endunless
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="px-4 py-10 text-center"><x-heroicon-o-inbox class="mx-auto h-8 w-8 text-gray-300" /><div class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-200">No manifest lines yet</div><p class="mt-1 text-xs text-gray-500">Add the expected shipment lines before receiving.</p></div>
                @endforelse
            </div>
        </section>

        @if($incompleteLines->isNotEmpty())
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:p-5">
                <div class="flex items-start gap-3"><x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-500" /><div class="min-w-0 flex-1"><h3 class="text-sm font-semibold text-gray-950 dark:text-white">Something did not arrive?</h3><p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400">Do not change the received count just to finish. Mark the remaining quantity short so the pallet keeps an accurate exception record.</p><div class="mt-3 space-y-2">@foreach($incompleteLines as $line)<div class="flex items-center justify-between gap-3 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800"><div class="min-w-0"><div class="truncate text-xs font-medium text-gray-800 dark:text-gray-200">L{{ $line['line_number'] }} · {{ $line['description'] }}</div><div class="text-[10px] text-gray-500">{{ max(0, $line['case_count'] - $line['received']) }} remaining</div></div><button type="button" wire:click="markLineAsShort({{ $line['id'] }})" wire:confirm="Mark the remaining quantity on this line as missing/short?" class="shrink-0 rounded-lg border border-amber-300 px-2.5 py-2 text-[10px] font-semibold text-amber-700 dark:border-amber-800 dark:text-amber-300 sm:text-xs">Mark Short</button></div>@endforeach</div></div></div>
            </section>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 sm:gap-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-center gap-2"><x-heroicon-o-user class="h-4 w-4 text-gray-400" /><h3 class="text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Received By</h3></div><input wire:model="receivedByName" type="text" placeholder="Receiver name" class="mt-3 min-h-11 w-full rounded-lg border-gray-300 text-base dark:border-gray-600 dark:bg-gray-800 sm:text-sm" /><p class="mt-1.5 text-[10px] text-gray-500 sm:text-xs">Defaults to the signed-in user; edit only when receiving on behalf of somebody else.</p></div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"><div class="flex items-center gap-2"><x-heroicon-o-camera class="h-4 w-4 text-gray-400" /><h3 class="text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Photos & Paperwork</h3></div><p class="mt-2 text-[10px] leading-4 text-gray-500 sm:text-xs">Keep damage photos, packing slips, receipts, or other evidence with this delivery.</p><div class="mt-3 flex gap-2"><button type="button" wire:click="mountAction('add_attachments')" class="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><x-heroicon-o-arrow-up-tray class="h-4 w-4" /> Upload</button><button type="button" x-data @click="$wire.mountAction('add_attachments'); setTimeout(() => window.dispatchEvent(new Event('open-photo-capture')), 250)" class="inline-flex min-h-10 flex-1 items-center justify-center gap-1.5 rounded-lg border border-gray-300 px-3 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200"><x-heroicon-o-camera class="h-4 w-4" /> Photo</button></div></div>
        </section>

        @if($summaryDone)
            <section class="rounded-xl border border-green-200 bg-green-50 p-4 dark:border-green-900 dark:bg-green-950/20 sm:p-5"><div class="flex items-start gap-3"><x-heroicon-o-check-circle class="mt-0.5 h-6 w-6 shrink-0 text-green-600" /><div class="min-w-0 flex-1"><h3 class="text-sm font-semibold text-green-900 dark:text-green-100 sm:text-base">Receiving matches the manifest</h3><p class="mt-1 text-xs leading-5 text-green-700 dark:text-green-300">{{ $summaryReceived }} of {{ $summaryExpected }} boxes are recorded. Complete the pallet when the receiver and supporting information are correct.</p><button type="button" wire:click="finalizePallet" wire:confirm="Complete this pallet? This marks receiving finished." wire:loading.attr="disabled" @disabled(blank($receivedByName)) class="mt-3 min-h-11 w-full rounded-lg bg-green-600 px-4 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto">Complete Receiving</button></div></div></section>
        @endif

        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden" data-vx-mobile-actions>
            <div class="flex gap-2"><button type="button" @click="openScanner()" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white"><x-heroicon-o-camera class="h-5 w-5" /> Scan</button>@if($summaryDone)<button type="button" wire:click="finalizePallet" @disabled(blank($receivedByName)) class="inline-flex min-h-11 flex-[1.2] items-center justify-center rounded-lg bg-green-600 px-3 text-sm font-semibold text-white disabled:opacity-50">Complete</button>@else<div class="inline-flex min-h-11 flex-[1.2] items-center justify-center rounded-lg bg-gray-100 px-3 text-sm font-semibold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $summaryRemaining }} remaining</div>@endif</div>
        </div>
    </div>
</x-filament-panels::page>
