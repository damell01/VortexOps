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
        {{-- Wizard position. Steps are clickable so a streamer can go back
             without losing anything; leaving step 2 persists it. --}}
        <ol class="vx-eos-steps">
            @foreach ([1 => 'Items Sold', 2 => 'Show Details', 3 => 'Review & Submit'] as $n => $label)
                <li>
                    <button type="button" wire:click="goToStep({{ $n }})"
                        class="vx-eos-step @if($this->step === $n) is-current @elseif($this->step > $n) is-done @endif">
                        <span class="vx-eos-step-n">{{ $n }}</span>
                        <span class="vx-eos-step-label">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        <div class="vx-eos">
            {{-- ── Main column ──────────────────────────────────────────── --}}
            <div class="vx-eos-main">
                @if ($this->step === 1)
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
                            wire:click="toggleBrowse"
                        >
                            {{ $this->showInventoryPicker ? 'Hide Inventory' : 'Browse Inventory' }}
                        </x-filament::button>
                    </div>

                    {{-- Results render inline rather than in a modal. The modal
                         was a <x-filament::modal visible> inside an @if, and
                         Filament modals are Alpine-driven: the markup arrived
                         after the Livewire round-trip and Alpine initialised it
                         closed, so Browse Inventory appeared to do nothing.
                         Inline is also easier to use one-handed on a phone. --}}
                    @if (filled($this->search) || $this->showInventoryPicker)
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
                                <li class="vx-eos-empty">
                                    @if (filled($this->search))
                                        Nothing matched “{{ $this->search }}”.
                                    @else
                                        No active inventory items to show.
                                    @endif
                                </li>
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
                @endif

                @if ($this->step === 2)
                    <x-filament::section>
                        <x-slot name="heading">Show Details</x-slot>
                        <x-slot name="description">Hours, shipments and package counts for this show.</x-slot>

                        <div class="vx-eos-fields">
                            @foreach ([
                                ['hoursStreamed',   'Hours Streamed',        '0.5'],
                                ['shipments',       'Shipments',             '1'],
                                ['pweCount',        'PWE Count',             '1'],
                                ['labelCount',      'Label Count',           '1'],
                                ['packagesOver500', 'Packages over $500',    '1'],
                            ] as [$model, $label, $stepAttr])
                                <label class="vx-eos-field">
                                    <span>{{ $label }}</span>
                                    <input type="number" min="0" step="{{ $stepAttr }}" inputmode="decimal"
                                        wire:model.blur="{{ $model }}" />
                                </label>
                            @endforeach
                        </div>

                        <label class="vx-eos-field vx-eos-field-wide">
                            <span>Notes (optional)</span>
                            <textarea rows="3" wire:model.blur="logNotes"
                                placeholder="Anything the admin should know about this show"></textarea>
                        </label>
                    </x-filament::section>
                @endif

                @if ($this->step === 3)
                    <x-filament::section>
                        <x-slot name="heading">Review & Submit</x-slot>
                        <x-slot name="description">Check this over, then send it for admin review.</x-slot>

                        <dl class="vx-eos-review">
                            <div><dt>Items</dt><dd>{{ $summary['items'] }}</dd></div>
                            <div><dt>Units Sold</dt><dd>{{ $summary['units'] }}</dd></div>
                            <div><dt>Product Cost</dt><dd>${{ number_format($summary['productCost'], 2) }}</dd></div>
                            <div><dt>Hours</dt><dd>{{ $this->hoursStreamed !== '' ? $this->hoursStreamed : '—' }}</dd></div>
                            <div><dt>Shipments</dt><dd>{{ $this->shipments !== '' ? $this->shipments : '—' }}</dd></div>
                            <div><dt>PWE / Label</dt><dd>{{ $this->pweCount !== '' ? $this->pweCount : '0' }} / {{ $this->labelCount !== '' ? $this->labelCount : '0' }}</dd></div>
                        </dl>

                        {{-- Problems are shown before submitting, not after. --}}
                        @php ($preview = $this->deductionPreview)
                        @if (! empty($preview))
                            <div class="vx-eos-problems">
                                <p class="vx-eos-warn">These will not deduct stock:</p>
                                <ul>
                                    @foreach ($preview as $problem)
                                        <li>{{ $problem }}</li>
                                    @endforeach
                                </ul>
                                <p class="vx-eos-empty">You can still submit — an admin will see the same list.</p>
                            </div>
                        @else
                            <p class="vx-eos-ok">Everything is linked and in stock. Ready to submit.</p>
                        @endif

                        <div class="vx-eos-actions">
                            <x-filament::button type="button" color="gray" wire:click="goToStep(2)">
                                Back
                            </x-filament::button>
                            <x-filament::button type="button" wire:click="submit"
                                wire:confirm="Submit this log for admin review?">
                                Submit for Review
                            </x-filament::button>
                        </div>
                    </x-filament::section>
                @endif

                @if ($this->step < 3)
                    <div class="vx-eos-actions">
                        @if ($this->step > 1)
                            <x-filament::button type="button" color="gray" wire:click="goToStep({{ $this->step - 1 }})">
                                Back
                            </x-filament::button>
                        @endif
                        <x-filament::button type="button" wire:click="goToStep({{ $this->step + 1 }})"
                            icon="heroicon-m-arrow-right" icon-position="after">
                            {{ $this->step === 1 ? 'Continue to Show Details' : 'Continue to Review' }}
                        </x-filament::button>
                    </div>
                @endif
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

    @endif
</x-filament-panels::page>
