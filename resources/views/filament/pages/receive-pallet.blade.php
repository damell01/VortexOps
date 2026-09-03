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
        class="mx-auto max-w-7xl pb-24 sm:pb-8"
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
        {{-- Compact job header: identity + progress without making the user scroll through a dashboard first. --}}
        <section class="mb-4 rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[11px] font-bold uppercase tracking-[.14em] text-primary-600 dark:text-primary-400">Receiving</span>
                        <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold {{ $summaryDone ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-blue-100 text-blue-700 dark:bg-blue-950 dark:text-blue-200' }}">
                            {{ $summaryDone ? 'Ready to finish' : 'In progress' }}
                        </span>
                    </div>
                    <h1 class="mt-1 truncate text-xl font-bold text-gray-950 dark:text-white sm:text-2xl">{{ $this->record->displayName() }}</h1>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {{ $this->record->vendor?->name ?? 'No vendor' }}
                        @if($this->record->reference) <span class="mx-1">·</span> {{ $this->record->reference }} @endif
                    </p>
                </div>

                <div class="grid grid-cols-3 gap-2 lg:w-[390px]">
                    <div class="rounded-xl bg-gray-50 px-3 py-2.5 text-center dark:bg-gray-800">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Expected</div>
                        <div class="mt-0.5 text-xl font-bold text-gray-950 dark:text-white">{{ number_format($summaryExpected) }}</div>
                    </div>
                    <div class="rounded-xl bg-green-50 px-3 py-2.5 text-center dark:bg-green-950/30">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-green-600 dark:text-green-300">Received</div>
                        <div class="mt-0.5 text-xl font-bold text-green-700 dark:text-green-200">{{ number_format($summaryReceived) }}</div>
                    </div>
                    <div class="rounded-xl bg-amber-50 px-3 py-2.5 text-center dark:bg-amber-950/30">
                        <div class="text-[10px] font-semibold uppercase tracking-wide text-amber-600 dark:text-amber-300">Left</div>
                        <div class="mt-0.5 text-xl font-bold text-amber-700 dark:text-amber-200">{{ number_format($summaryRemaining) }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                    <div class="h-full rounded-full {{ $summaryDone ? 'bg-green-500' : 'bg-primary-600' }} transition-all" style="width: {{ $summaryPct }}%"></div>
                </div>
                <span class="shrink-0 text-xs font-semibold text-gray-500 dark:text-gray-300">{{ $summaryPct }}%</span>
            </div>
        </section>

        {{-- Workstation: scan on the left, manifest on the right. On phones scan stays first. --}}
        <div class="grid items-start gap-4 lg:grid-cols-[360px_minmax(0,1fr)] xl:grid-cols-[400px_minmax(0,1fr)]">
            <aside class="space-y-4 lg:sticky lg:top-24">
                <section class="overflow-hidden rounded-2xl border border-primary-200 bg-white shadow-sm dark:border-primary-900 dark:bg-gray-900">
                    <div class="border-b border-primary-100 bg-primary-50/70 p-4 dark:border-primary-900 dark:bg-primary-950/25">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary-600 text-white">
                                <x-heroicon-o-qr-code class="h-5 w-5" />
                            </div>
                            <div>
                                <h2 class="text-base font-bold text-gray-950 dark:text-white">Scan the next box</h2>
                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">Scan normally, or tap an item first if it needs its first barcode.</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4">
                        @if($targetLineId)
                            @php
                                $target = collect($lineProgress)->firstWhere('id', $targetLineId);
                            @endphp
                            @if($target)
                                <div class="mb-3 rounded-xl border border-primary-200 bg-primary-50 p-3 dark:border-primary-900 dark:bg-primary-950/25">
                                    <div class="text-[10px] font-bold uppercase tracking-wide text-primary-600 dark:text-primary-300">Scanning for</div>
                                    <div class="mt-1 text-sm font-semibold text-gray-950 dark:text-white">L{{ $target['line_number'] }} · {{ $target['description'] }}</div>
                                    <button type="button" wire:click="targetLine({{ $targetLineId }})" class="mt-1 text-xs font-semibold text-gray-500 hover:text-gray-800 dark:hover:text-white">Clear selection</button>
                                </div>
                            @endif
                        @endif

                        <label for="receiving-barcode-input" class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-200">Barcode / UPC / SKU</label>
                        <input
                            wire:model="barcodeInput"
                            wire:keydown.enter="submitBarcode"
                            id="receiving-barcode-input"
                            type="text"
                            autocomplete="off"
                            placeholder="Scan or type code…"
                            class="min-h-12 w-full rounded-xl border-gray-300 bg-white px-3 text-base font-mono text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" @click="openScanner()" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl border border-primary-300 bg-white px-3 text-sm font-bold text-primary-700 shadow-sm hover:bg-primary-50 dark:border-primary-800 dark:bg-gray-800 dark:text-primary-300">
                                <x-heroicon-o-camera class="h-5 w-5" /> Camera
                            </button>
                            <button type="button" wire:click="submitBarcode" wire:loading.attr="disabled" wire:target="submitBarcode" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-xl bg-primary-600 px-3 text-sm font-bold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60">
                                <span wire:loading.remove wire:target="submitBarcode">Receive</span>
                                <span wire:loading wire:target="submitBarcode">Checking…</span>
                            </button>
                        </div>

                        @if($lastScannedResult)
                            <div class="mt-3 rounded-xl px-3 py-3 text-sm font-semibold {{ $lastScanSuccess ? 'bg-green-100 text-green-800 dark:bg-green-950 dark:text-green-200' : 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-200' }}">
                                {{ $lastScannedResult }}
                            </div>
                        @endif

                        @if($pendingCode)
                            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/30">
                                <div class="text-sm font-bold text-amber-900 dark:text-amber-100">What item is <span class="font-mono">{{ $pendingCode }}</span>?</div>
                                <p class="mt-1 text-xs leading-5 text-amber-700 dark:text-amber-300">The scan worked. Pick the matching manifest item; no second scan is needed.</p>
                                <div class="mt-2 grid gap-2">
                                    @foreach($this->pendingChoices() as $choice)
                                        <button type="button" wire:click="assignPendingTo({{ $choice->id }})" class="min-h-10 rounded-lg bg-amber-600 px-3 text-left text-xs font-semibold text-white">L{{ $choice->line_number }} · {{ $choice->description }}</button>
                                    @endforeach
                                    <button type="button" wire:click="discardPending" class="min-h-10 rounded-lg border border-amber-300 px-3 text-xs font-semibold text-amber-700 dark:border-amber-800 dark:text-amber-300">This is not on the pallet</button>
                                </div>
                            </div>
                        @endif
                    </div>
                </section>

                <section class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-bold text-gray-950 dark:text-white">Finish details</h3>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Usually nothing to change here.</p>
                        </div>
                        <button type="button" wire:click="mountAction('add_attachments')" class="inline-flex min-h-9 items-center gap-1.5 rounded-lg border border-gray-300 px-2.5 text-xs font-semibold text-gray-700 dark:border-gray-600 dark:text-gray-200">
                            <x-heroicon-o-camera class="h-4 w-4" /> Add photo
                        </button>
                    </div>
                    <label class="mt-3 block text-[10px] font-bold uppercase tracking-wide text-gray-400">Received by</label>
                    <input wire:model="receivedByName" type="text" placeholder="Receiver name" class="mt-1 min-h-10 w-full rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white" />
                </section>
            </aside>

            <main class="space-y-4">
                @if($unmappedLines->isNotEmpty())
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-3 dark:border-amber-900 dark:bg-amber-950/25">
                        <div class="flex items-start gap-2.5">
                            <x-heroicon-o-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
                            <p class="text-xs leading-5 text-amber-800 dark:text-amber-200"><strong>{{ $unmappedLines->count() }} item(s) need a first barcode.</strong> Tap <strong>Scan this item</strong> on its row, then scan the box. That both links it to inventory and receives the first case.</p>
                        </div>
                    </div>
                @endif

                <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-100 px-4 py-3.5 dark:border-gray-800 sm:px-5">
                        <div>
                            <h2 class="text-base font-bold text-gray-950 dark:text-white">Items on this pallet</h2>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Work down the list. Completed items fade out automatically.</p>
                        </div>
                        <span class="shrink-0 rounded-full bg-gray-100 px-2.5 py-1 text-xs font-bold text-gray-600 dark:bg-gray-800 dark:text-gray-300">{{ $incompleteLines->count() }} open</span>
                    </div>

                    <div class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($lineProgress as $line)
                            @php
                                $done = $line['case_count'] > 0 && $line['received'] >= $line['case_count'];
                                $pct = $line['case_count'] > 0 ? min(100, round(($line['received'] / $line['case_count']) * 100)) : 0;
                                $remaining = max(0, $line['case_count'] - $line['received']);
                            @endphp
                            <article wire:key="receiving-line-{{ $line['id'] }}" class="p-4 transition {{ $done ? 'bg-green-50/40 dark:bg-green-950/10' : '' }}">
                                <div class="flex items-start gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $done ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : ($line['mapped'] ? 'bg-primary-50 text-primary-700 dark:bg-primary-950 dark:text-primary-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200') }}">
                                        @if($done)
                                            <x-heroicon-m-check class="h-5 w-5" />
                                        @elseif($line['mapped'])
                                            <x-heroicon-o-cube class="h-5 w-5" />
                                        @else
                                            <x-heroicon-o-qr-code class="h-5 w-5" />
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <div class="text-[10px] font-bold uppercase tracking-wide text-gray-400">Line {{ $line['line_number'] }}</div>
                                                <h3 class="mt-0.5 text-sm font-bold leading-snug text-gray-950 dark:text-white sm:text-base">{{ $line['description'] }}</h3>
                                                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                                    @if($line['item_name'])
                                                        <span>{{ $line['item_name'] }}</span>
                                                    @else
                                                        <span class="font-semibold text-amber-700 dark:text-amber-300">Not linked to inventory yet</span>
                                                    @endif
                                                    @if($line['location'])<span>· {{ $line['location'] }}</span>@endif
                                                </div>
                                            </div>
                                            <div class="shrink-0 text-right">
                                                <div class="text-lg font-bold text-gray-950 dark:text-white">{{ $line['received'] }} / {{ $line['case_count'] }}</div>
                                                <div class="text-[10px] font-medium text-gray-400">{{ $remaining }} remaining</div>
                                            </div>
                                        </div>

                                        <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                            <div class="h-full rounded-full {{ $done ? 'bg-green-500' : 'bg-primary-600' }}" style="width: {{ $pct }}%"></div>
                                        </div>

                                        @unless($done)
                                            <div class="mt-3 flex flex-wrap gap-2">
                                                <button type="button" wire:click="targetLine({{ $line['id'] }})" wire:loading.attr="disabled" class="inline-flex min-h-10 items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3.5 text-xs font-bold text-white shadow-sm hover:bg-primary-700 disabled:opacity-60 sm:text-sm">
                                                    <x-heroicon-o-camera class="h-4 w-4" />
                                                    {{ $line['mapped'] ? 'Scan this item' : 'Scan first barcode' }}
                                                </button>
                                                @if($line['mapped'])
                                                    <button type="button" wire:click="receiveLine({{ $line['id'] }})" wire:confirm="Receive every remaining box on this line? Only continue if you physically counted them." wire:loading.attr="disabled" class="inline-flex min-h-10 items-center justify-center rounded-lg border border-gray-300 bg-white px-3.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700 sm:text-sm">Receive all {{ $remaining }}</button>
                                                @endif
                                            </div>
                                        @else
                                            <div class="mt-2.5 inline-flex items-center gap-1.5 text-xs font-bold text-green-700 dark:text-green-300"><x-heroicon-m-check-circle class="h-4 w-4" /> Complete</div>
                                        @endunless
                                    </div>
                                </div>
                            </article>
                        @empty
                            <div class="px-5 py-12 text-center">
                                <x-heroicon-o-inbox class="mx-auto h-9 w-9 text-gray-300" />
                                <div class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-200">No manifest items yet</div>
                                <p class="mt-1 text-xs text-gray-500">Add the expected items before receiving.</p>
                            </div>
                        @endforelse
                    </div>
                </section>

                @if($incompleteLines->isNotEmpty())
                    <details class="group rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3.5 sm:px-5">
                            <div class="flex items-center gap-2.5">
                                <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-amber-500" />
                                <div>
                                    <div class="text-sm font-bold text-gray-950 dark:text-white">Missing or short items</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">Only use this when something actually did not arrive.</div>
                                </div>
                            </div>
                            <x-heroicon-o-chevron-down class="h-4 w-4 text-gray-400 transition group-open:rotate-180" />
                        </summary>
                        <div class="border-t border-gray-100 p-4 dark:border-gray-800 sm:p-5">
                            <div class="space-y-2">
                                @foreach($incompleteLines as $line)
                                    <div class="flex items-center justify-between gap-3 rounded-xl bg-gray-50 px-3 py-2.5 dark:bg-gray-800">
                                        <div class="min-w-0">
                                            <div class="truncate text-xs font-semibold text-gray-800 dark:text-gray-200">L{{ $line['line_number'] }} · {{ $line['description'] }}</div>
                                            <div class="text-[10px] text-gray-500">{{ max(0, $line['case_count'] - $line['received']) }} remaining</div>
                                        </div>
                                        <button type="button" wire:click="markLineAsShort({{ $line['id'] }})" wire:confirm="Mark the remaining quantity on this line as missing/short?" class="shrink-0 rounded-lg border border-amber-300 px-3 py-2 text-xs font-bold text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-300 dark:hover:bg-amber-950">Mark short</button>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </details>
                @endif

                @if($summaryDone)
                    <section class="rounded-2xl border border-green-200 bg-green-50 p-4 shadow-sm dark:border-green-900 dark:bg-green-950/20 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="flex items-start gap-3">
                                <x-heroicon-o-check-circle class="mt-0.5 h-6 w-6 shrink-0 text-green-600" />
                                <div>
                                    <h3 class="text-sm font-bold text-green-900 dark:text-green-100 sm:text-base">Everything on the manifest is received</h3>
                                    <p class="mt-1 text-xs text-green-700 dark:text-green-300">{{ $summaryReceived }} of {{ $summaryExpected }} cases are recorded. Finish the pallet when you are done at the receiving station.</p>
                                </div>
                            </div>
                            <button type="button" wire:click="finalizePallet" wire:confirm="Complete this pallet? This marks receiving finished." wire:loading.attr="disabled" @disabled(blank($receivedByName)) class="min-h-11 rounded-xl bg-green-600 px-5 text-sm font-bold text-white shadow-sm hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50">Complete receiving</button>
                        </div>
                    </section>
                @endif
            </main>
        </div>

        {{-- Mobile: the only actions that need to follow the worker are scan + remaining/complete. --}}
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden" data-vx-mobile-actions>
            <div class="flex gap-2">
                <button type="button" @click="openScanner()" class="inline-flex min-h-11 flex-1 items-center justify-center gap-2 rounded-lg bg-primary-600 px-3 text-sm font-bold text-white"><x-heroicon-o-camera class="h-5 w-5" /> Scan</button>
                @if($summaryDone)
                    <button type="button" wire:click="finalizePallet" @disabled(blank($receivedByName)) class="inline-flex min-h-11 flex-[1.2] items-center justify-center rounded-lg bg-green-600 px-3 text-sm font-bold text-white disabled:opacity-50">Complete</button>
                @else
                    <div class="inline-flex min-h-11 flex-[1.2] items-center justify-center rounded-lg bg-gray-100 px-3 text-sm font-bold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $summaryRemaining }} left</div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
