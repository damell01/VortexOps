<x-filament-panels::page>
    @php
        ['show' => $show, 'run' => $run, 'buyers' => $buyers, 'packers' => $packers, 'stations' => $stations] = $this->boardData();
        $current = $buyers->first(fn ($buyer) => $buyer->status === 'packing')
            ?? $buyers->first(fn ($buyer) => $buyer->status === 'waiting');
        $packed = $buyers->where('status', 'packed')->count();
        $total = $buyers->count();
    @endphp
    <div class="rounded-[20px] bg-[#0b1020] p-4 text-white shadow-xl sm:p-6" wire:poll.15s>
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-[#121833] p-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-violet-300">Fulfillment station</p>
                <h2 class="mt-1 text-xl font-bold">{{ $show->title }}</h2>
                <p class="mt-1 text-sm text-slate-300">{{ $show->show_date?->format('M j, Y') }} · {{ $run?->station_name ?? 'Not started' }}</p>
            </div>
            <div class="text-right text-sm text-slate-300">
                <span class="rounded-full bg-emerald-950 px-3 py-1 font-semibold text-emerald-300">{{ $run ? 'Packing live' : 'Ready' }}</span>
                @if($run)
                    <p class="mt-2">Started {{ \Illuminate\Support\Carbon::parse($run->started_at)->format('g:i A') }}</p>
                    <p>{{ implode(', ', $packers) }}</p>
                @endif
            </div>
        </div>
        @if(!$run)
            <div class="mx-auto max-w-xl rounded-2xl bg-[#121833] p-6">
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
            <div class="mb-5">
                <div class="mb-2 flex justify-between text-sm"><span>Packing progress</span><span>{{ $packed }} / {{ $total }} buyers</span></div>
                <div class="h-3 overflow-hidden rounded-full bg-slate-700"><div class="h-full bg-emerald-500" style="width: {{ $total ? round($packed / $total * 100) : 0 }}%"></div></div>
            </div>
            <div class="grid gap-5 lg:grid-cols-[minmax(230px,1fr)_minmax(0,2fr)]">
                <div class="max-h-[65vh] space-y-2 overflow-auto rounded-2xl bg-[#121833] p-3">
                    @foreach($buyers as $buyer)
                        <div class="flex items-center gap-3 rounded-xl border {{ $current?->id === $buyer->id ? 'border-violet-400' : 'border-slate-700' }} p-3">
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-violet-700 font-mono text-xl font-bold">{{ $buyer->letter }}</span>
                            <div class="min-w-0 flex-1"><p class="truncate font-semibold">{{ $buyer->buyer_username }}</p><p class="text-xs text-slate-300">{{ $buyer->item_count }} items</p></div>
                            <span class="text-xs {{ $buyer->status === 'packed' ? 'text-emerald-300' : 'text-slate-300' }}">{{ ucfirst($buyer->status) }}</span>
                        </div>
                    @endforeach
                </div>
                <div class="rounded-2xl bg-[#121833] p-5">
                    @if($current)
                        <div class="flex flex-wrap items-center gap-5">
                            <span class="flex h-32 w-32 items-center justify-center rounded-2xl bg-violet-700 font-mono text-7xl font-bold">{{ $current->letter }}</span>
                            <div><h3 class="break-all text-2xl font-bold">{{ $current->buyer_username }}</h3><p class="mt-2 text-slate-300">{{ $current->item_count }} items · {{ ucfirst($current->status) }}</p></div>
                        </div>
                        <p class="mt-6 rounded-xl border border-amber-700 bg-amber-950 p-3 text-sm text-amber-200">Barcode verification and item-level pick lists are not yet enabled. Confirm items using the existing fulfillment show details before marking packed.</p>
                        <button wire:click="packBuyer({{ $current->id }})" wire:confirm="Confirm this buyer has been packed and checked?" class="mt-6 min-h-16 w-full rounded-xl bg-violet-600 px-5 text-lg font-bold">Packed · next buyer</button>
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
