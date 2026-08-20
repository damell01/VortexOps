<x-filament-panels::page>
@php($rows = $this->rows)
@php($isAdjust = $operation === \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::ADJUST)
@php($isSend = $operation === \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND)

<div class="grid gap-5 lg:grid-cols-3">

    {{-- Where the stock is. This is the thing the modals used to cover up:
         every decision on the right is made against these numbers, so they
         stay on screen while it is made. --}}
    <div class="lg:col-span-1 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
        <div class="px-5 py-3 border-b border-gray-100 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Where it is now</h2>
        </div>

        @forelse ($rows as $row)
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-50 dark:border-gray-800/60 last:border-0
                {{ $row['location_id'] === $fromLocationId ? 'bg-violet-50/60 dark:bg-violet-950/30' : '' }}">
                <span class="text-sm text-gray-700 dark:text-gray-300 truncate">{{ $row['location'] }}</span>
                <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($row['quantity']) }}</span>
            </div>
        @empty
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">None of this item is in stock anywhere you can see.</p>
            </div>
        @endforelse

        @if (count($rows) > 1)
            <div class="flex items-center justify-between px-5 py-3 bg-gray-50 dark:bg-gray-800/50">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</span>
                <span class="text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ number_format(collect($rows)->sum('quantity')) }}
                </span>
            </div>
        @endif
    </div>

    <div class="lg:col-span-2 space-y-4">

        {{-- Three names for one act: stock at one place becoming stock at
             another, or a different amount of it. --}}
        <div class="flex gap-1 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 p-1">
            @php($ops = [
                \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::ADJUST   => 'Correct the count',
                \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::TRANSFER => 'Move somewhere else',
            ] + (count($this->destinationOptions) && $isSend ? [] : []))
            @if (\App\Support\InventoryVisibility::destinationFor(auth()->user()))
                @php($ops[\App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND] = 'Send to my inventory')
            @endif

            @foreach ($ops as $key => $label)
                <button type="button" wire:click="setOperation('{{ $key }}')"
                    class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors
                        {{ $operation === $key
                            ? 'bg-violet-600 text-white shadow'
                            : 'text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-5 py-5 space-y-4">

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                        {{ $isAdjust ? 'Which location' : 'Take it from' }}
                    </span>
                    <select wire:model.live="fromLocationId"
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm">
                        <option value="">Choose…</option>
                        @foreach ($this->sourceOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                        {{ number_format($this->available) }} units there now
                    </span>
                </label>

                @if ($isAdjust)
                    <label class="block">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Set quantity to</span>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="newQuantity"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm tabular-nums" />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                            The amount that should be there afterwards, not the amount to add or remove.
                        </span>
                    </label>
                @else
                    <label class="block">
                        <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">How many</span>
                        <input type="number" step="0.01" min="0.01" max="{{ $this->available ?: null }}"
                            wire:model.live.debounce.400ms="moveQuantity"
                            class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm tabular-nums" />
                    </label>
                @endif
            </div>

            @unless ($isAdjust)
                <label class="block md:w-1/2 md:pr-2">
                    <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Send it to</span>
                    <select wire:model.live="toLocationId" @disabled($isSend)
                        class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm disabled:opacity-70">
                        <option value="">Choose…</option>
                        @foreach ($this->destinationOptions as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @if ($isSend)
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Your own inventory.</span>
                    @endif
                </label>
            @endunless

            <label class="block">
                <span class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                    Reason {{ $isAdjust ? '' : '(optional)' }}
                </span>
                <input type="text" wire:model="reason"
                    placeholder="{{ $isAdjust ? 'e.g. two boxes were crushed' : 'e.g. for Friday night break' }}"
                    class="w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 px-3 py-2 text-sm" />
            </label>

            {{-- Said before it happens, not reported after. --}}
            <div class="rounded-lg bg-gray-50 dark:bg-gray-800/60 px-4 py-3">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-0.5">This will</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $this->effect }}</p>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $this->record]) }}"
                    class="rounded-lg border border-gray-300 dark:border-gray-600 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200">
                    Cancel
                </a>
                <button type="button" wire:click="submit" wire:loading.attr="disabled"
                    class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ $isAdjust ? 'Save the correction' : 'Move it' }}</span>
                    <span wire:loading wire:target="submit">Working…</span>
                </button>
            </div>
        </div>
    </div>
</div>
</x-filament-panels::page>
