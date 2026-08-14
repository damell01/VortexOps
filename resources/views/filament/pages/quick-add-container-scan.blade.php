@php
    $container = $this->container;
    $totals    = $this->totals;
@endphp

<x-filament-panels::page>
    {{-- Camera scans land in whichever field is focused; the component
         dispatches barcode-scanned, which the inputs below listen for. --}}
    @include('filament.components.camera-barcode-scanner')

    <div class="vx-cs"
         x-data="{
             fill(model, value) {
                 @this.set(model, value);
             }
         }"
         x-on:barcode-scanned.window="fill($event.detail.scope === 'container' || {{ $this->step }} === 1 ? 'containerCode' : 'itemCode', $event.detail.value)">

        {{-- Steps --}}
        <ol class="vx-cs-steps">
            @foreach ([1 => 'Scan Container', 2 => 'Scan Items', 3 => 'Review & Save'] as $n => $label)
                <li>
                    <button type="button" wire:click="goToStep({{ $n }})"
                        class="vx-cs-step @if($this->step === $n) is-current @elseif($this->step > $n) is-done @endif">
                        <span class="vx-cs-step-n">
                            @if ($this->step > $n) &check; @else {{ $n }} @endif
                        </span>
                        <span class="vx-cs-step-label">{{ $label }}</span>
                    </button>
                </li>
            @endforeach
        </ol>

        @if (filled($this->lookupError))
            <p class="vx-cs-error">{{ $this->lookupError }}</p>
        @endif

        {{-- Unknown code: create it here rather than sending the user off to
             the catalogue and losing their place in the case. --}}
        @if ($this->showCreate)
            <x-filament::section>
                <x-slot name="heading">
                    Add this {{ $this->createFor === 'container' ? 'container' : 'item' }} to the catalogue
                </x-slot>
                <x-slot name="description">
                    It isn't in the catalogue yet. Name it and carry on — this creates the
                    product record only, it doesn't add any stock.
                </x-slot>

                <div class="vx-cs-createform">
                    <label class="vx-cs-field">
                        <span>Name <span class="vx-cs-req">*</span></span>
                        <input type="text" wire:model="createName"
                            placeholder="{{ $this->createFor === 'container' ? 'e.g. 2024 Topps Chrome Hobby Case' : 'e.g. 2024 Topps Chrome Hobby Box' }}"
                            wire:keydown.enter.prevent="createAndUse" />
                    </label>

                    <label class="vx-cs-field">
                        <span>SKU / Barcode</span>
                        <input type="text" wire:model="createSku" placeholder="Scanned code" />
                    </label>

                    <label class="vx-cs-field">
                        <span>Unit Cost</span>
                        <input type="number" min="0" step="0.01" inputmode="decimal"
                            wire:model="createCost" placeholder="0.00" />
                    </label>
                </div>

                <div class="vx-cs-actions">
                    <x-filament::button type="button" color="gray" wire:click="cancelCreate">
                        Cancel
                    </x-filament::button>
                    <x-filament::button type="button" icon="heroicon-m-plus" wire:click="createAndUse">
                        Create &amp; {{ $this->createFor === 'container' ? 'Use' : 'Add' }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        {{-- ── Step 1 ──────────────────────────────────────────────── --}}
        @if ($this->step === 1)
            <x-filament::section>
                <x-slot name="heading">Scan Container / Box SKU</x-slot>
                <x-slot name="description">Scan the outer box or case SKU to begin.</x-slot>

                <div class="vx-cs-scanbox">
                    <x-filament::icon icon="heroicon-o-cube" class="vx-cs-scanbox-glyph" />

                    <x-filament::button
                        type="button"
                        icon="heroicon-m-qr-code"
                        onclick="window.dispatchEvent(new Event('open-camera-scanner'))"
                    >
                        Scan Container
                    </x-filament::button>

                    <p class="vx-cs-or">or type the SKU manually</p>

                    <input
                        type="text"
                        class="vx-cs-input"
                        placeholder="Container SKU, barcode or UPC"
                        autocomplete="off"
                        autofocus
                        wire:model.live.debounce.400ms="containerCode"
                        wire:keydown.enter.prevent="lookupContainer"
                    />
                </div>
            </x-filament::section>
        @endif

        {{-- ── Step 2 ──────────────────────────────────────────────── --}}
        @if ($this->step === 2 && $container)
            <x-filament::section>
                <x-slot name="heading">Scan or Add Items</x-slot>
                <x-slot name="description">
                    Scan each item inside the container — scanning the same one twice counts it twice.
                </x-slot>

                <div class="vx-cs-container-bar">
                    <span>
                        <span class="vx-cs-container-label">Container</span>
                        <span class="vx-cs-container-name">{{ $container->name }}</span>
                        <span class="vx-cs-container-sku">{{ $container->sku ?: '—' }}</span>
                    </span>
                    <x-filament::button type="button" size="xs" color="gray" wire:click="changeContainer">
                        Change
                    </x-filament::button>
                </div>

                <div class="vx-cs-scanrow">
                    <input
                        type="text"
                        class="vx-cs-input"
                        placeholder="Scan item SKU / UPC"
                        autocomplete="off"
                        autofocus
                        wire:model.live.debounce.400ms="itemCode"
                        wire:keydown.enter.prevent="lookupItem"
                    />
                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-m-qr-code"
                        onclick="window.dispatchEvent(new Event('open-camera-scanner'))"
                    >
                        Camera
                    </x-filament::button>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Items in this container ({{ $totals['items'] }})</x-slot>
                <x-slot name="afterHeader">
                    @if ($totals['items'] > 0)
                        <x-filament::button type="button" size="xs" color="danger"
                            wire:click="clearAll" wire:confirm="Remove every scanned item?">
                            Clear All
                        </x-filament::button>
                    @endif
                </x-slot>

                @if ($totals['items'] === 0)
                    <p class="vx-cs-empty">Nothing scanned yet.</p>
                @else
                    <div class="vx-cs-table-wrap">
                        <table class="vx-cs-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>SKU</th>
                                    <th class="is-num">Qty</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->lines as $line)
                                    <tr wire:key="line-{{ $line['id'] }}">
                                        <td data-label="Item" class="vx-cs-name">{{ $line['name'] }}</td>
                                        <td data-label="SKU" class="vx-cs-sku">{{ $line['sku'] ?: '—' }}</td>
                                        <td data-label="Qty">
                                            <span class="vx-cs-stepper">
                                                <button type="button" wire:click="decrementLine({{ $line['id'] }})" aria-label="Decrease">&minus;</button>
                                                <input type="number" min="0" inputmode="numeric"
                                                    value="{{ $line['qty'] }}"
                                                    wire:change="setLineQty({{ $line['id'] }}, $event.target.value)" />
                                                <button type="button" wire:click="incrementLine({{ $line['id'] }})" aria-label="Increase">+</button>
                                            </span>
                                        </td>
                                        <td data-label="">
                                            <x-filament::icon-button
                                                icon="heroicon-m-trash"
                                                color="danger"
                                                label="Remove"
                                                wire:click="removeLine({{ $line['id'] }})"
                                            />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="vx-cs-totals">
                        <span><span class="vx-cs-total-label">Total Items</span> {{ $totals['items'] }}</span>
                        <span><span class="vx-cs-total-label">Total Quantity</span> {{ $totals['units'] }}</span>
                    </div>
                @endif
            </x-filament::section>

            <div class="vx-cs-actions">
                <x-filament::button type="button" color="gray" wire:click="changeContainer">Back</x-filament::button>
                <x-filament::button type="button" wire:click="goToStep(3)"
                    icon="heroicon-m-arrow-right" icon-position="after">
                    Review &amp; Save
                </x-filament::button>
            </div>
        @endif

        {{-- ── Step 3 ──────────────────────────────────────────────── --}}
        @if ($this->step === 3 && $container)
            <x-filament::section>
                <x-slot name="heading">Review &amp; Save</x-slot>
                <x-slot name="description">This records what the container holds. It does not move any stock.</x-slot>

                <dl class="vx-cs-summary">
                    <div><dt>Container</dt><dd>{{ $container->name }}</dd></div>
                    <div><dt>Total Items</dt><dd>{{ $totals['items'] }}</dd></div>
                    <div><dt>Total Quantity</dt><dd>{{ $totals['units'] }}</dd></div>
                    <div><dt>Contents Value</dt><dd>${{ number_format($totals['value'], 2) }}</dd></div>
                </dl>

                <ul class="vx-cs-review-list">
                    @foreach ($this->lines as $line)
                        <li>
                            <span class="vx-cs-name">{{ $line['name'] }}</span>
                            <span class="vx-cs-sku">{{ $line['sku'] ?: '—' }}</span>
                            <span class="vx-cs-review-qty">Qty {{ $line['qty'] }}</span>
                            <span class="vx-cs-review-cost">
                                ${{ number_format($line['unit_cost'] * $line['qty'], 2) }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-filament::section>

            <div class="vx-cs-actions">
                <x-filament::button type="button" color="gray" wire:click="goToStep(2)">Back</x-filament::button>
                <x-filament::button type="button" wire:click="save" icon="heroicon-m-check">
                    Save Container Contents
                </x-filament::button>
            </div>
        @endif
    </div>
</x-filament-panels::page>
