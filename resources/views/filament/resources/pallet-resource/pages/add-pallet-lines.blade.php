<x-filament-panels::page>
@php($totals = $this->totals)
@php($linkedProducts = $this->linkedProducts)

<div class="space-y-5">
    <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <div class="text-sm font-semibold text-gray-950 dark:text-white">Stage what should arrive</div>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    If you already stock an item, search and select it. VortexOps fills the item name and known product details for you. If it is new, just type the name.
                </p>
            </div>

            <div class="grid min-w-[18rem] gap-1.5">
                <label for="vx-location" class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Receive everything into</label>
                <select id="vx-location" wire:model="locationId"
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 shadow-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    <option value="">Decide when it lands</option>
                    @foreach(\App\Models\InventoryLocation::activeOptions() as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="mt-5 grid grid-cols-3 divide-x divide-gray-200 rounded-lg bg-gray-50 px-2 py-3 dark:divide-gray-700 dark:bg-gray-800/60 sm:max-w-md">
            <div class="px-3"><div class="text-xs uppercase tracking-wide text-gray-500">Lines</div><div class="mt-1 text-xl font-semibold tabular-nums">{{ $totals['lines'] }}</div></div>
            <div class="px-3"><div class="text-xs uppercase tracking-wide text-gray-500">Units</div><div class="mt-1 text-xl font-semibold tabular-nums">{{ rtrim(rtrim(number_format($totals['units'], 2), '0'), '.') }}</div></div>
            <div class="px-3"><div class="text-xs uppercase tracking-wide text-gray-500">Est. cost</div><div class="mt-1 text-xl font-semibold tabular-nums">${{ number_format($totals['cost'], 2) }}</div></div>
        </div>
    </div>

    {{--
        Rows used to be a <table min-w-[76rem]> inside overflow-x-auto. That
        forced horizontal scrolling on anything narrower than ~1216px (every
        phone, and plenty of desktop windows), and it clipped the search
        dropdown below: setting overflow-x on a container implicitly clips
        the other axis too, so the absolutely-positioned results list got cut
        off by its own scrolling ancestor instead of floating above the page.

        Each row is its own block now — full width at any viewport, no
        scrolling container, so the dropdown paints wherever it actually
        needs to. Mirrors the mobile-card / desktop-grid split already used
        on the packing-slip import page instead of introducing a third
        pattern.
    --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div id="vx-lines" class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($rows as $i => $row)
                @php($units = (float) ($row['case_count'] ?: 0) * ($row['is_container'] === '1' ? (float) ($row['quantity_per_case'] ?: 0) : 1))
                @php($lineTotal = $units * (float) ($row['unit_cost'] ?: 0))
                @php($linked = filled($row['inventory_item_id'] ?? null) ? ($linkedProducts[(int) $row['inventory_item_id']] ?? null) : null)

                <div wire:key="line-{{ $i }}" class="p-4 sm:p-5">
                    <div class="flex items-start gap-3">
                        <span class="mt-2.5 flex-shrink-0 text-xs font-mono text-gray-400 tabular-nums">{{ $i + 1 }}</span>

                        <div class="min-w-0 flex-1 space-y-4">
                            {{-- Item name --}}
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                    Item name <span class="text-danger-600">*</span>
                                </label>
                                <input type="text" wire:model.blur="rows.{{ $i }}.description" data-vx-line-input
                                    @if($linked) readonly @endif
                                    placeholder="Type a new item name"
                                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-violet-500 focus:ring-violet-500 disabled:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 {{ $linked ? 'bg-success-50/50 dark:bg-success-950/20' : '' }}" />
                                <div class="mt-1.5 text-xs text-gray-500">
                                    @if($linked)
                                        <span class="font-medium text-success-700 dark:text-success-400">Existing product linked</span>
                                        @if($linked['sku']) <span> · SKU {{ $linked['sku'] }}</span> @endif
                                    @else
                                        New item? Type the name here. Existing item? Find it in the search box below.
                                    @endif
                                </div>
                            </div>

                            {{-- Link existing inventory --}}
                            <div>
                                <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Link existing inventory</label>
                                @if($linked)
                                    <div class="flex min-h-[42px] items-center justify-between gap-3 rounded-lg border border-success-200 bg-success-50 px-3 py-2 dark:border-success-800 dark:bg-success-950/30">
                                        <div class="min-w-0">
                                            <div class="truncate font-medium text-success-900 dark:text-success-100">{{ $linked['name'] }}</div>
                                            <div class="text-xs text-success-700 dark:text-success-400">Restocking existing inventory</div>
                                        </div>
                                        <button type="button" wire:click="unlinkProduct({{ $i }})" class="shrink-0 text-xs font-semibold text-success-800 underline decoration-dotted underline-offset-2 dark:text-success-300">Change</button>
                                    </div>
                                @else
                                    <div x-data="{
                                            open: false, q: '', loading: false, results: [], timer: null,
                                            openPicker() {
                                                this.open = true; this.q = ''; this.loading = true;
                                                $wire.browseProducts('').then(r => { this.results = r; this.loading = false; });
                                                this.$nextTick(() => this.$refs.picker{{ $i }}?.focus());
                                            },
                                            search() {
                                                clearTimeout(this.timer);
                                                this.loading = true;
                                                this.timer = setTimeout(async () => {
                                                    try { this.results = await $wire.browseProducts(this.q); }
                                                    finally { this.loading = false; }
                                                }, 220);
                                            },
                                            async choose(id) {
                                                await $wire.selectProduct({{ $i }}, id);
                                                this.open = false; this.q = ''; this.results = [];
                                            }
                                        }">
                                        {{-- Trigger — opens the picker, never types inline. A field you can only
                                             half-see the options in is the exact complaint this replaces. --}}
                                        <button type="button" @click="openPicker()"
                                            class="flex w-full items-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-500 shadow-sm hover:border-violet-400 hover:text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400 dark:hover:text-gray-200">
                                            <x-heroicon-o-magnifying-glass class="h-5 w-5 flex-shrink-0 text-gray-400" />
                                            Search or browse inventory…
                                        </button>

                                        {{-- Teleported to <body> on purpose: a modal that inherits some ancestor's
                                             overflow/positioning is exactly the clipping bug this page just got
                                             fixed for elsewhere. This can never be clipped by a row, a card, or a
                                             future layout change around it. --}}
                                        <template x-teleport="body">
                                            <div x-show="open" x-cloak
                                                class="fixed inset-0 z-50 flex items-end justify-center bg-gray-950/60 sm:items-center sm:p-4"
                                                @keydown.escape.window="open = false">
                                                <div @click.outside="open = false"
                                                    class="flex max-h-[85vh] w-full flex-col rounded-t-2xl bg-white shadow-2xl dark:bg-gray-900 sm:max-w-lg sm:rounded-2xl">
                                                    <div class="flex items-center gap-2 border-b border-gray-200 p-4 dark:border-gray-700">
                                                        <x-heroicon-o-magnifying-glass class="h-5 w-5 flex-shrink-0 text-gray-400" />
                                                        <input x-ref="picker{{ $i }}" type="search" x-model="q" @input="search"
                                                            placeholder="Search name, SKU, UPC or barcode…"
                                                            class="min-w-0 flex-1 border-0 bg-transparent p-0 text-base text-gray-900 focus:outline-none focus:ring-0 dark:text-gray-100" />
                                                        <svg x-show="loading" class="h-5 w-5 flex-shrink-0 animate-spin text-violet-500" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
                                                        <button type="button" @click="open = false" title="Close" class="flex-shrink-0 rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800">
                                                            <x-heroicon-o-x-mark class="h-5 w-5" />
                                                        </button>
                                                    </div>

                                                    <div class="flex-1 overflow-y-auto p-2">
                                                        <template x-if="!loading && results.length === 0">
                                                            <div class="px-3 py-10 text-center text-sm text-gray-500">
                                                                No matching inventory.<br>Close this and type a new item name instead.
                                                            </div>
                                                        </template>
                                                        <template x-for="item in results" :key="item.id">
                                                            <button type="button" @click="choose(item.id)"
                                                                class="block w-full rounded-lg px-3 py-3 text-left hover:bg-violet-50 dark:hover:bg-violet-950/30">
                                                                <div class="font-medium text-gray-950 dark:text-white" x-text="item.name"></div>
                                                                <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-gray-500">
                                                                    <span x-show="item.sku" x-text="'SKU ' + item.sku"></span>
                                                                    <span x-show="item.upc" x-text="'UPC ' + item.upc"></span>
                                                                    <span x-show="item.barcode && item.barcode !== item.upc" x-text="'Barcode ' + item.barcode"></span>
                                                                </div>
                                                            </button>
                                                        </template>
                                                    </div>

                                                    <div class="border-t border-gray-100 p-2.5 text-center text-xs text-gray-400 dark:border-gray-800">
                                                        Showing up to 50 — keep typing to narrow it down.
                                                    </div>
                                                </div>
                                            </div>
                                        </template>
                                    </div>
                                @endif
                            </div>

                            {{-- Packaging / quantities / cost --}}
                            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Packaging</label>
                                    <select wire:model.live="rows.{{ $i }}.is_container" class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                        <option value="">Not sure</option>
                                        <option value="1">Case / box</option>
                                        <option value="0">Single item</option>
                                    </select>
                                </div>

                                <div class="{{ $row['is_container'] !== '1' ? 'col-span-2 sm:col-span-1' : '' }}">
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">
                                        {{ $row['is_container'] === '1' ? 'Qty / cases' : 'Quantity' }}
                                    </label>
                                    <input type="number" min="1" step="1" wire:model.blur="rows.{{ $i }}.case_count"
                                        title="{{ $row['is_container'] === '1' ? 'How many cases' : 'How many items' }}"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                                </div>

                                @if($row['is_container'] === '1')
                                    <div>
                                        <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Units / case</label>
                                        <input type="number" min="0.01" step="any" wire:model.blur="rows.{{ $i }}.quantity_per_case"
                                            class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                                    </div>
                                @endif

                                <div>
                                    <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-1">Unit cost</label>
                                    <input type="number" min="0" step="0.01" placeholder="—" wire:model.blur="rows.{{ $i }}.unit_cost"
                                        class="w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2 text-sm tabular-nums dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" />
                                    @if($linked && filled($row['unit_cost'])) <div class="mt-1 text-[11px] text-gray-500">Known cost filled when available</div> @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-shrink-0 items-start gap-3 pl-1">
                            <span class="mt-2.5 text-sm font-medium tabular-nums text-gray-600 dark:text-gray-300 whitespace-nowrap">{{ $lineTotal > 0 ? '$' . number_format($lineTotal, 2) : '—' }}</span>
                            <button type="button" wire:click="removeRow({{ $i }})" title="Remove line" class="rounded-lg p-2 text-gray-400 hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950">
                                <x-heroicon-o-trash class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="border-t border-gray-200 p-3 dark:border-gray-700">
            <button type="button" wire:click="addRow" class="w-full rounded-lg border border-dashed border-gray-300 py-2.5 text-sm font-semibold text-gray-600 transition hover:border-violet-400 hover:bg-violet-50 hover:text-violet-700 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-violet-950/30">
                + Add another manifest line
            </button>
        </div>
    </div>

    <div class="sticky bottom-3 z-20 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white/95 p-3 shadow-lg backdrop-blur dark:border-gray-700 dark:bg-gray-900/95">
        <p class="text-xs text-gray-500 dark:text-gray-400"><strong>Enter</strong> moves to the next item row · <strong>Tab</strong> moves across · blank rows are ignored</p>
        <div class="flex gap-2">
            <a href="{{ \App\Filament\Resources\PalletResource::getUrl('view', ['record' => $this->record]) }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 dark:border-gray-600 dark:text-gray-200">Cancel</a>
            <button type="button" wire:click="save" wire:loading.attr="disabled" class="rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white shadow hover:bg-violet-700 disabled:opacity-60">
                <span wire:loading.remove wire:target="save">Save {{ $totals['lines'] }} {{ Str::plural('line', $totals['lines']) }}</span>
                <span wire:loading wire:target="save">Saving…</span>
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    const table = document.getElementById('vx-lines');
    if (! table || table.dataset.bound) return;
    table.dataset.bound = '1';

    table.addEventListener('keydown', (event) => {
        if (event.key !== 'Enter') return;
        const inputs = [...table.querySelectorAll('[data-vx-line-input]')];
        const index = inputs.indexOf(event.target);
        if (index === -1 || event.target.readOnly) return;
        event.preventDefault();

        if (index < inputs.length - 1) {
            inputs[index + 1].focus();
            return;
        }

        window.Livewire.find(event.target.closest('[wire\\:id]').getAttribute('wire:id')).call('addRow').then(() => {
            const refreshed = table.querySelectorAll('[data-vx-line-input]');
            refreshed[refreshed.length - 1]?.focus();
        });
    });
})();
</script>
</x-filament-panels::page>
