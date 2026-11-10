<x-filament-panels::page>
    @php
        ['show' => $show, 'run' => $run, 'buyers' => $buyers, 'packers' => $packers, 'stations' => $stations] = $this->boardData();
        $current = $buyers->first(fn ($buyer) => $buyer->status === 'packing')
            ?? $buyers->first(fn ($buyer) => $buyer->status === 'waiting');
        $packed = $buyers->where('status', 'packed')->count();
        $total = $buyers->count();
        $held = $buyers->where('status', 'held')->count();
        $remaining = max(0, $total - $packed);
    @endphp
    <div class="rounded-[20px] bg-[#0b1020] p-4 text-white shadow-xl sm:p-6" wire:poll.visible.15s>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-white/10 bg-[#121833] p-4 sm:p-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[.16em] text-violet-300">Vortex Ops · Fulfillment Station</p>
                <h2 class="mt-1 text-xl font-bold leading-tight sm:text-2xl">{{ $show->title }}</h2>
                <p class="mt-1 text-sm text-slate-300">{{ $show->show_date?->format('M j, Y') }} · {{ $run?->station_name ?? 'Not started' }}</p>
            </div>
            <div class="text-right text-sm text-slate-300">
                <span class="inline-flex items-center gap-2 rounded-full border border-emerald-700/50 bg-emerald-950 px-3 py-1 font-semibold text-emerald-300"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>{{ $run ? 'Packing in progress' : 'Ready to start' }}</span>
                @if($run)
                    <p class="mt-2">Started {{ \Illuminate\Support\Carbon::parse($run->started_at)->format('g:i A') }}</p>
                    <p>{{ implode(', ', $packers) }}</p>
                @endif
            </div>
        </div>
        @if($run && $run->status === 'completed')
            <section class="rounded-2xl border border-violet-400/40 bg-[#121833] p-5" aria-label="Completed packing worksheet">
                <h3 class="text-xl font-bold">Packing worksheet and payroll handoff</h3>
                <p class="mt-2 text-sm text-slate-300">Completed {{ $run->finished_at }}. Packing records remain available for review. Payroll stays in the existing Vortex Ops system.</p>
                <p class="mt-3 text-sm text-slate-300">Assigned packers: {{ implode(', ', $packers) }}</p>
                @if($this->canManageHandoff())
                    @if(!$run->verified_at)
                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            <label class="text-sm font-semibold">Total shipments
                                <input type="number" min="0" wire:model="reportedShipments" class="mt-2 w-full rounded-lg border-slate-600 bg-[#0b1020] text-white" />
                                @error('reportedShipments') <span class="text-amber-300">{{ $message }}</span> @enderror
                            </label>
                            <label class="text-sm font-semibold">Total PWEs
                                <input type="number" min="0" wire:model="reportedPwes" class="mt-2 w-full rounded-lg border-slate-600 bg-[#0b1020] text-white" />
                                @error('reportedPwes') <span class="text-amber-300">{{ $message }}</span> @enderror
                            </label>
                        </div>
                        <label class="mt-4 flex items-center gap-3 text-sm"><input type="checkbox" wire:model="hardCopyVerified" class="rounded border-slate-600" /> Hard-copy worksheet checked</label>
                        <button type="button" wire:click="verifyWorksheet" wire:loading.attr="disabled" class="mt-4 min-h-12 rounded-lg bg-violet-600 px-5 font-semibold">Verify worksheet</button>
                    @else
                        <p class="mt-4 text-sm text-emerald-300">Verified {{ $run->verified_at }} · {{ $run->reported_shipments }} shipments · {{ $run->reported_pwes }} PWEs</p>
                        @if(!$run->payroll_handoff_at)
                            <label class="mt-4 block text-sm font-semibold">Packer pay allocation
                                <select wire:model="splitMethod" class="mt-2 w-full rounded-lg border-slate-600 bg-[#0b1020] text-white">
                                    <option value="single">Single packer</option><option value="even">Even split</option><option value="custom">Custom split</option>
                                </select>
                            </label>
                            <label class="mt-3 block text-sm font-semibold">Split notes for Micha
                                <textarea wire:model="splitNotes" rows="3" class="mt-2 w-full rounded-lg border-slate-600 bg-[#0b1020] text-white" placeholder="Document agreed custom allocation when applicable"></textarea>
                            </label>
                            <button type="button" wire:click="handoffToPayroll" wire:loading.attr="disabled" class="mt-4 min-h-12 rounded-lg bg-emerald-600 px-5 font-semibold">Mark ready for payroll review</button>
                        @else
                            <p class="mt-4 font-semibold text-emerald-300">Ready for Micha · Handoff {{ $run->payroll_handoff_at }}</p>
                        @endif
                    @endif
                @else
                    <p class="mt-4 text-sm text-slate-300">A fulfillment manager will verify the worksheet and prepare the payroll handoff.</p>
                @endif
            </section>
        @elseif(!$run)
            <div class="mx-auto max-w-xl rounded-2xl border border-white/10 bg-[#121833] p-6">
                <h3 class="text-lg font-bold">Choose a packing station</h3>
                <p class="mt-2 text-sm text-slate-300">A station can pack only one show at a time.</p>
                <select wire:model="stationId" class="mt-4 min-h-12 w-full rounded-xl bg-[#0b1020] text-white">
                    <option value="">Select station</option>
                    @foreach($stations as $station)
                        <option value="{{ $station->id }}">{{ $station->name }}</option>
                    @endforeach
                </select>
                @error('stationId') <p class="mt-2 text-sm text-orange-300">{{ $message }}</p> @enderror
                <button wire:click="startPacking" class="mt-4 min-h-14 w-full rounded-xl bg-violet-600 px-5 font-bold text-white">Start packing</button>
                @if($stations->isEmpty()) <p class="mt-3 text-sm text-orange-300">No active stations are configured. Ask a fulfillment admin to configure stations.</p> @endif
            </div>
        @else
            <div class="mb-5 grid gap-3 sm:grid-cols-3">
                <div class="rounded-2xl border border-white/10 bg-[#121833] p-4"><p class="text-xs font-medium uppercase tracking-wider text-slate-400">Packed buyers</p><p class="mt-2 text-3xl font-bold tabular-nums text-emerald-300">{{ $packed }}</p></div>
                <div class="rounded-2xl border border-white/10 bg-[#121833] p-4"><p class="text-xs font-medium uppercase tracking-wider text-slate-400">Remaining</p><p class="mt-2 text-3xl font-bold tabular-nums">{{ $remaining }}</p></div>
                <div class="rounded-2xl border border-white/10 bg-[#121833] p-4"><p class="text-xs font-medium uppercase tracking-wider text-slate-400">On hold</p><p class="mt-2 text-3xl font-bold tabular-nums {{ $held ? 'text-amber-300' : 'text-slate-300' }}">{{ $held }}</p></div>
            </div>
            <div class="mb-5">
                <div class="mb-2 flex justify-between text-sm"><span>Packing progress</span><span>{{ $packed }} / {{ $total }} buyers</span></div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-700"><div class="h-full bg-emerald-500" style="width: {{ $total ? round($packed / $total * 100) : 0 }}%"></div></div>
            </div>
            <div class="grid gap-5 lg:grid-cols-[minmax(260px,1fr)_minmax(0,2fr)]">
                <div class="max-h-[70vh] space-y-2 overflow-auto rounded-2xl border border-white/10 bg-[#121833] p-3" aria-label="Buyer packing queue">
                    <p class="px-2 pb-2 pt-1 text-xs font-semibold uppercase tracking-wider text-slate-400">Buyer queue · alphabetical</p>
                    @foreach($buyers as $buyer)
                        <div class="flex items-center gap-3 rounded-xl border {{ $current?->id === $buyer->id ? 'border-violet-400' : 'border-slate-700' }} p-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-700 font-mono text-xl font-bold">{{ $buyer->letter }}</span>
                            <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $buyer->buyer_username }}</p><p class="text-xs text-slate-300">{{ $buyer->item_count }} items</p></div>
                            <span class="text-xs {{ $buyer->status === 'packed' ? 'text-emerald-300' : 'text-slate-300' }}">{{ ucfirst($buyer->status) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="rounded-2xl border border-white/10 bg-[#121833] p-5 sm:p-7">
                    @if($current)
                        <div class="flex flex-wrap items-center gap-5">
                            <span class="flex h-36 min-w-36 items-center justify-center rounded-2xl border border-violet-400/50 bg-violet-700 px-4 font-mono text-7xl font-black tracking-tight shadow-lg shadow-violet-950/40 sm:h-48 sm:min-w-48 sm:text-8xl" aria-label="Camera reference letter {{ $current->letter }}">{{ $current->letter }}</span>
                            <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wider text-violet-300">Current buyer · {{ $current->letter }}</p><h3 class="mt-2 break-all text-2xl font-bold sm:text-4xl">{{ $current->buyer_username }}</h3><p class="mt-2 text-slate-300">{{ $current->item_count }} items · {{ ucfirst($current->status) }}</p><p class="mt-2 text-xs text-slate-400">Camera reference: show + station + letter + packing time</p></div>
                        </div>
                        @php $coverage = $this->buyerCoverage($current->id); @endphp
                        <div class="mt-5 rounded-xl border border-white/15 bg-white/5 p-4" aria-live="polite">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <p class="font-semibold text-white">Item verification</p>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $coverage['complete'] ? 'bg-emerald-900 text-emerald-200' : 'bg-slate-700 text-slate-200' }}">
                                    {{ $coverage['complete'] ? 'All items verified' : ($coverage['supported'] ? 'Scanning in progress' : 'Manual verification required') }}
                                </span>
                            </div>
                            @if($coverage['supported'])
                                <p class="mt-2 text-sm text-slate-300">{{ $coverage['scanned'] }} of {{ $coverage['expected'] }} expected units scanned</p>
                                <div class="mt-2 h-2 overflow-hidden rounded-full bg-slate-700"><div class="h-full bg-emerald-500" style="width: {{ $coverage['expected'] ? min(100, round($coverage['scanned'] / $coverage['expected'] * 100)) : 0 }}%"></div></div>
                            @else
                                <p class="mt-2 text-sm text-amber-200">This buyer's order lacks complete inventory product mappings. Scanning cannot certify the order; use the existing manual review process.</p>
                            @endif
                        </div>
                        <div class="mt-6 space-y-4 rounded-xl border border-violet-400/30 bg-[#0b1020] p-4">
                            <h4 class="font-semibold text-violet-200">Scan an item for this buyer</h4>
                            <form wire:submit.prevent="scanBuyerItem({{ $current->id }})" class="flex flex-col gap-2 sm:flex-row">
                                <label for="fulfillment-barcode" class="sr-only">Barcode or SKU</label>
                                <input id="fulfillment-barcode" wire:model="scanCode" autocomplete="off" placeholder="Scan barcode or enter SKU" class="min-h-12 flex-1 rounded-lg border border-slate-600 bg-[#121833] px-3 text-white" />
                                <button type="submit" class="min-h-12 rounded-lg bg-violet-600 px-5 font-semibold text-white">Verify scan</button>
                            </form>
                            @error('scanCode') <p class="text-sm text-amber-300">{{ $message }}</p> @enderror
                            <p class="text-xs text-slate-400">Scans are recorded with the employee and buyer. A match does not yet certify all expected quantities.</p>
                            <button type="button" wire:click="$set('selectedBuyerId', {{ $current->id }})" class="rounded-lg border border-amber-500/60 px-4 py-2 text-sm font-semibold text-amber-200">Report missing, damaged or wrong item</button>
                            @if($selectedBuyerId === (int) $current->id)
                                <div class="space-y-2">
                                    <label for="fulfillment-flag-type" class="block text-sm">Issue type</label>
                                    <select id="fulfillment-flag-type" wire:model="flagType" class="w-full rounded-lg bg-[#121833] text-white"><option value="missing_item">Missing item</option><option value="damaged">Damaged</option><option value="wrong_item">Wrong item</option><option value="wrong_count">Wrong count</option><option value="cancelled">Cancelled</option><option value="other">Other</option></select>
                                    <label for="fulfillment-flag-details" class="block text-sm">What happened?</label>
                                    <textarea id="fulfillment-flag-details" wire:model="flagDetails" rows="3" class="w-full rounded-lg bg-[#121833] text-white"></textarea>
                                    @error('flagDetails') <p class="text-sm text-amber-300">{{ $message }}</p> @enderror
                                    <button type="button" wire:click="reportIssue({{ $current->id }})" class="min-h-12 rounded-lg bg-amber-600 px-4 font-semibold text-white">Submit issue and hold buyer</button>
                                </div>
                            @endif
                        </div>
                        <div role="status" class="mt-6 flex gap-3 rounded-xl border border-amber-600/50 bg-amber-950/80 p-4 text-sm text-amber-100"><span aria-hidden="true">⚠</span><p><strong>Manual packing confirmation</strong><span class="mt-1 block text-amber-200">Barcode verification and item-level pick lists are not enabled yet. Check the existing fulfillment show details before marking a buyer packed. This action does not certify scanned items.</span></p></div>
                        <button wire:click="packBuyer({{ $current->id }})" wire:confirm="Confirm you manually checked this buyer against the existing fulfillment show details? Scanning is not enabled." class="mt-6 min-h-16 w-full rounded-xl bg-violet-600 px-5 text-lg font-bold transition hover:bg-violet-500 focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-violet-400 disabled:opacity-50" wire:loading.attr="disabled" wire:target="packBuyer">Confirm manually packed · next buyer</button>
                    @else
                        <h3 class="text-xl font-bold">All buyers processed</h3>
                        <p class="mt-2 text-slate-300">Finish this run to release the station.</p>
                        <button wire:click="finishPacking" wire:confirm="Finish this packing run?" class="mt-6 min-h-16 w-full rounded-xl bg-emerald-600 px-5 text-lg font-bold">Finish show</button>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
