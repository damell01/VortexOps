<x-filament-panels::page>
@php($rows = $this->rows)
@php($isAdjust = $operation === \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::ADJUST)
@php($isSend = $operation === \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND)

<div class="grid gap-5 lg:grid-cols-3">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white lg:col-span-1 dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Where it is now</h2>
        </div>

        @forelse ($rows as $row)
            <div class="flex items-center justify-between border-b border-gray-50 px-5 py-3 last:border-0 dark:border-gray-800/60 {{ $row['location_id'] === $fromLocationId ? 'bg-violet-50/60 dark:bg-violet-950/30' : '' }}">
                <span class="truncate text-sm text-gray-700 dark:text-gray-300">{{ $row['location'] }}</span>
                <span class="text-sm font-semibold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format($row['quantity']) }}</span>
            </div>
        @empty
            <div class="px-5 py-8 text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">None of this item is in stock anywhere you can see.</p>
            </div>
        @endforelse

        @if (count($rows) > 1)
            <div class="flex items-center justify-between bg-gray-50 px-5 py-3 dark:bg-gray-800/50">
                <span class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Total</span>
                <span class="text-sm font-bold tabular-nums text-gray-900 dark:text-gray-100">{{ number_format(collect($rows)->sum('quantity')) }}</span>
            </div>
        @endif
    </div>

    <div class="space-y-4 lg:col-span-2">
        <div class="flex gap-1 rounded-xl border border-gray-200 bg-white p-1 dark:border-gray-700 dark:bg-gray-900">
            @php($ops = [
                \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::ADJUST => 'Correct / remove stock',
                \App\Filament\Resources\InventoryItemResource\Pages\ManageStock::TRANSFER => 'Move somewhere else',
            ])
            @if (\App\Support\InventoryVisibility::destinationFor(auth()->user()))
                @php($ops[\App\Filament\Resources\InventoryItemResource\Pages\ManageStock::SEND] = 'Send to my inventory')
            @endif

            @foreach ($ops as $key => $label)
                <button type="button" wire:click="setOperation('{{ $key }}')"
                    class="flex-1 rounded-lg px-3 py-2 text-sm font-medium transition-colors {{ $operation === $key ? 'bg-violet-600 text-white shadow' : 'text-gray-600 hover:bg-gray-50 dark:text-gray-300 dark:hover:bg-gray-800' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        <div class="space-y-4 rounded-xl border border-gray-200 bg-white px-5 py-5 dark:border-gray-700 dark:bg-gray-900">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $isAdjust ? 'Which location' : 'Take it from' }}</span>
                    <select wire:model.live="fromLocationId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Choose…</option>
                        @foreach ($this->sourceOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">{{ number_format($this->available) }} units there now</span>
                </label>

                @if ($isAdjust)
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Set quantity to</span>
                        <input type="number" step="0.01" min="0" wire:model.live.debounce.400ms="newQuantity"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800" />
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">For a giveaway of 2 from 10 on hand, set this to 8.</span>
                    </label>
                @else
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">How many</span>
                        <input type="number" step="0.01" min="0.01" max="{{ $this->available ?: null }}" wire:model.live.debounce.400ms="moveQuantity"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800" />
                    </label>
                @endif
            </div>

            @unless ($isAdjust)
                <label class="block md:w-1/2 md:pr-2">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Send it to</span>
                    <select wire:model.live="toLocationId" @disabled($isSend) class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm disabled:opacity-70 dark:border-gray-600 dark:bg-gray-800">
                        <option value="">Choose…</option>
                        @foreach ($this->destinationOptions as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                    </select>
                    @if ($isSend)<span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Your own inventory.</span>@endif
                </label>
            @endunless

            @if($isAdjust)
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Adjustment reason</span>
                        <select wire:model.live="adjustmentType" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            @foreach($this->adjustmentTypeOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Giveaway and Promo are tracked as their own inventory movement types.</span>
                    </label>

                    <label class="block">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Related show <span class="normal-case font-normal">(optional)</span></span>
                        <select wire:model="relatedShowId" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                            <option value="">Not tied to a show</option>
                            @foreach($this->relatedShowOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                        </select>
                    </label>
                </div>
            @endif

            <label class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Note {{ $isAdjust ? '(optional except Other)' : '(optional)' }}</span>
                <input type="text" wire:model="reason"
                    placeholder="{{ $isAdjust ? 'e.g. viewer giveaway during Friday show' : 'e.g. moved for tonight’s stream' }}"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
            </label>

            <div class="rounded-lg bg-gray-50 px-4 py-3 dark:bg-gray-800/60">
                <p class="mb-0.5 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">This will</p>
                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $this->effect }}</p>
            </div>

            <div class="flex justify-end gap-2 pt-1">
                <a href="{{ \App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $this->record]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</a>
                <button type="button" wire:click="submit" wire:loading.attr="disabled" class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 disabled:opacity-60">
                    <span wire:loading.remove wire:target="submit">{{ $isAdjust ? 'Save adjustment' : 'Move it' }}</span>
                    <span wire:loading wire:target="submit">Working…</span>
                </button>
            </div>
        </div>
    </div>
</div>
</x-filament-panels::page>
