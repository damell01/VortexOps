<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── Show Header ──────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-4 flex flex-wrap gap-6 items-start">
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Show</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->title ?? 'Show #' . $this->record->id }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Date</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->show_date?->format('M j, Y') ?? '—' }}</p>
            </div>
            <div>
                <p class="text-xs text-gray-400 uppercase tracking-wide">Streamers</p>
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $this->record->streamers->pluck('name')->join(', ') ?: '—' }}</p>
            </div>
        </div>

        {{-- ── Quick Add ────────────────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 px-6 py-5">
            <form wire:submit="addItem" class="flex flex-wrap items-end gap-3">
                <div class="flex-1 min-w-[220px]">
                    <label for="inventory_item_id" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Item</label>
                    <select
                        id="inventory_item_id"
                        wire:model="inventory_item_id"
                        class="fi-select-input block w-full rounded-lg border-none bg-white py-3 px-4 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    >
                        <option value="">Select an item…</option>
                        @foreach ($this->inventoryItems as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                    @error('inventory_item_id') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="w-28">
                    <label for="quantity" class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Qty</label>
                    <input
                        id="quantity"
                        type="number"
                        min="1"
                        step="1"
                        wire:model="quantity"
                        class="fi-input block w-full rounded-lg border-none bg-white py-3 px-4 text-base text-gray-950 shadow-sm ring-1 ring-gray-950/10 focus:ring-2 focus:ring-primary-600 dark:bg-white/5 dark:text-white dark:ring-white/20"
                    />
                    @error('quantity') <p class="text-xs text-danger-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <x-filament::button type="submit" icon="heroicon-o-plus">
                    Add Item
                </x-filament::button>
            </form>
        </div>

        {{-- ── Items Added So Far ───────────────────────────────────────────── --}}
        <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">Items added ({{ $this->lines->count() }})</p>
                <p class="text-xs text-gray-400 mt-0.5">Cost isn't needed here — ops fills that in during review.</p>
            </div>

            @if ($this->lines->isEmpty())
                <div class="px-6 py-10 text-center">
                    <x-heroicon-o-archive-box class="mx-auto h-8 w-8 text-gray-400 mb-2" />
                    <p class="text-sm text-gray-500">No items added yet — use the form above.</p>
                </div>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($this->lines as $line)
                        <li class="px-6 py-3 flex items-center justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ $line->inventoryItem?->name ?? ($line->raw_description ?? 'Unknown item') }}
                                </p>
                                <p class="text-xs text-gray-400">Qty {{ (int) $line->quantity_approved }}</p>
                            </div>
                            <button
                                type="button"
                                wire:click="removeLine({{ $line->id }})"
                                wire:confirm="Remove this item?"
                                class="text-gray-400 hover:text-danger-600 transition"
                            >
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

    </div>
</x-filament-panels::page>
