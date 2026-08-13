@php
    $summary = $this->summary;
    $lines   = $this->lineItems;
@endphp

<x-filament-panels::page>
    @if (! $this->show)
        {{-- No show chosen yet: pick one, then the items step opens. --}}
        <x-filament::section heading="Choose a show">
            <div class="vx-eos-shows">
                @forelse ($this->shows as $show)
                    <button type="button" wire:click="selectShow('{{ $show->id }}')" class="vx-eos-show">
                        <span class="vx-eos-show-title">{{ $show->title }}</span>
                        <span class="vx-eos-show-meta">
                            {{ $show->show_date?->format('M j, Y') ?? '—' }}
                        </span>
                    </button>
                @empty
                    <p class="vx-eos-empty">No shows are waiting on a log from you.</p>
                @endforelse
            </div>
        </x-filament::section>
    @else
        <div class="vx-eos">
            {{-- ── Main column ──────────────────────────────────────────── --}}
            <div class="vx-eos-main">
                <x-filament::section>
                    <x-slot name="heading">Add Items Sold</x-slot>
                    <x-slot name="description">
                        Search your inventory and add the items you sold during this show.
                    </x-slot>

                    <div class="vx-eos-search-row">
                        <input
                            type="text"
                            wire:model.live.debounce.300ms="search"
                            placeholder="Search items by name, SKU, or category…"
                            class="vx-eos-search"
                            autocomplete="off"
                        />
                        <x-filament::button
                            type="button"
                            color="gray"
                            icon="heroicon-m-plus"
                            wire:click="$set('showInventoryPicker', true)"
                        >
                            Browse Inventory
                        </x-filament::button>
                    </div>

                    {{-- Live results: adding straight from here is the fast path,
                         so a streamer never has to open the modal at all. --}}
                    @if (filled($this->search))
                        <ul class="vx-eos-results">
                            @forelse ($this->inventory as $item)
                                <li class="vx-eos-result">
                                    <span class="vx-eos-result-main">
                                        <span class="vx-eos-result-name">{{ $item->name }}</span>
                                        <span class="vx-eos-result-sku">SKU: {{ $item->sku ?? '—' }}</span>
                                    </span>
                                    <span class="vx-eos-result-stock">
                                        In stock: {{ (int) ($item->stock_sum_quantity ?? 0) }}
                                    </span>
                                    <x-filament::button
                                        type="button"
                                        size="xs"
                                        icon="heroicon-m-plus"
                                        wire:click="addLineItem({{ $item->id }})"
                                    >
                                        Add
                                    </x-filament::button>
                                </li>
                            @empty
                                <li class="vx-eos-empty">Nothing matched “{{ $this->search }}”.</li>
                            @endforelse
                        </ul>
                    @endif
                </x-filament::section>

                <x-filament::section>
                    <x-slot name="heading">Items You Sold ({{ $summary['items'] }})</x-slot>

                    @if ($lines->isEmpty())
                        <p class="vx-eos-empty">
                            No items yet. Search above to add what you sold.
                        </p>
                    @else
                        <div class="vx-eos-lines">
                            @foreach ($lines as $line)
                                <div class="vx-eos-line" wire:key="line-{{ $line->id }}">
                                    <div class="vx-eos-line-item">
                                        <span class="vx-eos-line-name">{{ $line->item_name }}</span>
                                        @if ($line->isMatched())
                                            <span class="vx-eos-line-sku">
                                                SKU: {{ $line->inventoryItem?->sku ?? '—' }}
                                            </span>
                                        @else
                                            <span class="vx-eos-line-unmatched">
                                                Not linked to inventory — stock won’t be deducted
                                            </span>
                                        @endif
                                    </div>

                                    <label class="vx-eos-line-field">
                                        <span>Qty</span>
                                        <input
                                            type="number" min="1" inputmode="numeric"
                                            value="{{ $line->quantity }}"
                                            wire:change="setLineQuantity({{ $line->id }}, $event.target.value)"
                                        />
                                    </label>

                                    <label class="vx-eos-line-field">
                                        <span>Unit Cost</span>
                                        <input
                                            type="number" min="0" step="0.01" inputmode="decimal"
                                            value="{{ $line->unit_cost }}"
                                            wire:change="setLineCost({{ $line->id }}, $event.target.value)"
                                        />
                                    </label>

                                    <span class="vx-eos-line-total">
                                        ${{ number_format($line->total_cost, 2) }}
                                    </span>

                                    <x-filament::icon-button
                                        icon="heroicon-m-trash"
                                        color="danger"
                                        label="Remove"
                                        wire:click="removeLineItem({{ $line->id }})"
                                        wire:confirm="Remove {{ $line->item_name }} from this show?"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            </div>

            {{-- ── Summary ──────────────────────────────────────────────── --}}
            <aside class="vx-eos-side">
                <x-filament::section heading="Show Summary">
                    <dl class="vx-eos-stats">
                        <div><dt>Items</dt><dd>{{ $summary['items'] }}</dd></div>
                        <div><dt>Units Sold</dt><dd>{{ $summary['units'] }}</dd></div>
                        <div><dt>Product Cost</dt><dd>${{ number_format($summary['productCost'], 2) }}</dd></div>
                    </dl>
                </x-filament::section>

                {{-- Says what will actually happen at submission rather than
                     just confirming the items are mapped. --}}
                <x-filament::section heading="Inventory Impact">
                    @if ($summary['items'] === 0)
                        <p class="vx-eos-empty">Nothing to deduct yet.</p>
                    @elseif ($summary['unmatched'] > 0)
                        <p class="vx-eos-warn">
                            {{ $summary['unmatched'] }}
                            {{ \Illuminate\Support\Str::plural('item', $summary['unmatched']) }}
                            not linked to inventory. Those won’t deduct any stock.
                        </p>
                    @else
                        <p class="vx-eos-ok">
                            Ready to deduct {{ $summary['units'] }}
                            {{ \Illuminate\Support\Str::plural('unit', $summary['units']) }}
                            across {{ $summary['items'] }}
                            {{ \Illuminate\Support\Str::plural('product', $summary['items']) }}.
                        </p>
                    @endif
                </x-filament::section>
            </aside>
        </div>

        {{-- ── Inventory picker ─────────────────────────────────────────── --}}
        @if ($this->showInventoryPicker)
            <x-filament::modal id="vx-inventory-picker" visible width="2xl">
                <x-slot name="heading">Add Item from Inventory</x-slot>

                <input
                    type="text"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search items by name, SKU, or category…"
                    class="vx-eos-search"
                    autocomplete="off"
                />

                <ul class="vx-eos-results">
                    @forelse ($this->inventory as $item)
                        <li class="vx-eos-result">
                            <span class="vx-eos-result-main">
                                <span class="vx-eos-result-name">{{ $item->name }}</span>
                                <span class="vx-eos-result-sku">SKU: {{ $item->sku ?? '—' }}</span>
                            </span>
                            <span class="vx-eos-result-stock">
                                In stock: {{ (int) ($item->stock_sum_quantity ?? 0) }}
                            </span>
                            <x-filament::button
                                type="button"
                                size="xs"
                                icon="heroicon-m-plus"
                                wire:click="addLineItem({{ $item->id }})"
                            >
                                Add
                            </x-filament::button>
                        </li>
                    @empty
                        <li class="vx-eos-empty">No inventory matched.</li>
                    @endforelse
                </ul>

                <x-slot name="footerActions">
                    <x-filament::button color="gray" wire:click="$set('showInventoryPicker', false)">
                        Done
                    </x-filament::button>
                </x-slot>
            </x-filament::modal>
        @endif
    @endif
</x-filament-panels::page>
