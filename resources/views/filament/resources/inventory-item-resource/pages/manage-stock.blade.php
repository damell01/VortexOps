<x-filament-panels::page>
@php($rows = $this->rows)
@php($isAdjust = $operation === \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::ADJUST)
@php($isSend = $operation === \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND)

<div class="grid gap-3 pb-24 sm:gap-5 sm:pb-0 lg:grid-cols-3">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white lg:col-span-1 dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-800 sm:px-5">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Where it is now</h2>
        </div>

        @forelse ($rows as $row)
            <div class="flex items-center justify-between border-b border-gray-50 px-4 py-2.5 last:border-0 dark:border-gray-800/60 sm:px-5 sm:py-3 {{ $row['location_id'] === $fromLocationId ? 'bg-violet-50/60 dark:bg-violet-950/30' : '' }}">
                <span class="truncate text-xs text-gray-700 dark:text-gray-300 sm:text-sm">{{ $row['location'] }}</span>
                <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($row['quantity']) }}</span>
            </div>
        @empty
            <div class="px-4 py-7 text-center sm:px-5 sm:py-8">
                <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">None of this item is in stock anywhere you can see.</p>
            </div>
        @endforelse

        @if (count($rows) > 1)
            <div class="flex items-center justify-between bg-gray-50 px-4 py-2.5 dark:bg-gray-800/50 sm:px-5 sm:py-3">
                <span class="text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Total</span>
                <span class="text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format(collect($rows)->sum('quantity')) }}</span>
            </div>
        @endif
    </div>

    <div class="space-y-3 sm:space-y-4 lg:col-span-2">
        <div class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
            @php($ops = [
                \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::ADJUST => 'Correct / remove',
                \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::TRANSFER => 'Move stock',
            ])
            @if (\App\Support\InventoryVisibility::destinationFor(auth()->user()))
                @php($ops[\App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND] = 'Send to me')
            @endif

            @foreach ($ops as $key => $label)
                <button type="button" wire:click="setOperation('{{ $key }}')"
                    class="min-h-10 flex-1 rounded-lg px-2 py-2 text-[11px] font-medium transition-colors sm:px-3 sm:text-sm {{ $operation === $key ? 'bg-violet-600 text-white shadow' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="space-y-3 rounded-xl border border-gray-200 bg-white px-4 py-4 dark:border-gray-700 dark:bg-gray-900 sm:space-y-4 sm:px-5 sm:py-5">
            <div class="grid gap-3 md:grid-cols-2 sm:gap-4">
                <label class="block">
                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">{{ $isAdjust ? 'Which location' : 'Take it from' }}</span>
                    <select wire:model.live="fromLocationId" class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Choose…</option>
                        @foreach ($this->sourceOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <span class="mt-1 block text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">{{ number_format($this->available) }} units there now</span>
                </label>

                @if ($isAdjust)
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Set quantity to</span>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="newQuantity"
                            class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800" />
                        <span class="mt-1 block text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">For a giveaway of 2 from 10 on hand, set this to 8.</span>
                    </label>
                @else
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">How many</span>
                        <input type="number" step="0.01" min="0.01" max="{{ $this->available ?: null }}" wire:model.live.debounce.400ms="moveQuantity"
                            class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800" />
                    </label>
                @endif
            </div>

            @unless ($isAdjust)
                <label class="block md:w-1/2 md:pr-2">
                    <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Send it to</span>
                    <select wire:model.live="toLocationId" @disabled($isSend) class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm disabled:opacity-70 dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Choose…</option>
                        @foreach ($this->destinationOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    @if ($isSend)<span class="mt-1 block text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">Your own inventory.</span>@endif
                </label>
            @endunless

            @if($isAdjust)
                <div class="grid gap-3 md:grid-cols-2 sm:gap-4">
                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Adjustment reason</span>
                        <select wire:model.live="adjustmentType" class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @foreach($this->adjustmentTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-[10px] text-gray-500 dark:text-gray-400 sm:text-xs">Giveaway and Promo stay identifiable in inventory history.</span>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Related show <span class="normal-case font-normal">(optional)</span></span>
                        <select wire:model="relatedShowId" class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Not tied to a show</option>
                            @foreach($this->relatedShowOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            @endif

            <label class="block">
                <span class="mb-1 block text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">Note {{ $isAdjust ? '(optional except Other)' : '(optional)' }}</span>
                <input type="text" wire:model="reason"
                    placeholder="{{ $isAdjust ? 'e.g. viewer giveaway during Friday show' : 'e.g. moving stock to streamer inventory' }}"
                    class="min-h-11 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
            </label>

            <div class="rounded-lg bg-gray-50 px-3 py-2.5 dark:bg-gray-800/60 sm:px-4 sm:py-3">
                <p class="mb-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 sm:text-xs">This will</p>
                <p class="text-xs font-medium leading-5 text-gray-900 dark:text-gray-100 sm:text-sm">{{ $this->effect }}</p>
            </div>

            <div class="hidden justify-end gap-2 pt-1 sm:flex">
                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $this->record]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</a>
                <button type="button" wire:click="submit" wire:loading.attr="disabled" class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ $isAdjust ? 'Save adjustment' : 'Move it' }}</span>
                    <span wire:loading wire:target="submit">Working…</span>
                </button>
            </div>
        </div>
    </div>
</div>

<div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden">
    <div class="flex gap-2">
        <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $this->record]) }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Cancel</a>
        <button type="button" wire:click="submit" wire:loading.attr="disabled" class="inline-flex min-h-11 flex-[1.4] items-center justify-center rounded-lg bg-violet-600 px-3 text-sm font-semibold text-white disabled:opacity-60">
            <span wire:loading.remove wire:target="submit">{{ $isAdjust ? 'Save Adjustment' : 'Move Inventory' }}</span>
            <span wire:loading wire:target="submit">Working…</span>
        </button>
    </div>
</div>
</x-filament-panels::page>
