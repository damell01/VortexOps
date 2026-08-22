@php
    $summary = $this->summary;
    $lines = $this->lineItems;
    $whatnot = $this->whatnotReference;
@endphp

<x-filament-panels::page>
    @if (! $this->show)
        <div class="mx-auto max-w-4xl space-y-4">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <h2 class="text-lg font-semibold text-gray-950 dark:text-white">Choose a completed show</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Select the show you just finished to report sold items, giveaways, promos, and anything not yet in the catalog.</p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                @forelse ($this->shows as $show)
                    <button type="button" wire:click="selectShow('{{ $show->id }}')"
                        class="rounded-2xl border border-gray-200 bg-white p-4 text-left transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900">
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $show->title }}</div>
                        <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                            <span>{{ $show->show_date?->format('M j, Y') ?? '—' }}</span>
                            @if($show->start_time)<span>{{ $show->start_time->format('g:i A') }}</span>@endif
                            @if($show->units_sold !== null)<span>{{ number_format($show->units_sold) }} Whatnot orders</span>@endif
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-2xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700">
                        No completed shows are waiting on a report from you.
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <div class="mx-auto max-w-7xl space-y-5">
            {{-- Show context / Whatnot reference --}}
            <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <div class="flex flex-col gap-3 border-b border-gray-100 p-5 sm:flex-row sm:items-start sm:justify-between dark:border-gray-800">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-primary-600">Streamer Show Report</div>
                        <h2 class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ $this->show->title }}</h2>
                        <div class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            {{ $this->show->show_date?->format('M j, Y') }}
                            @if($this->show->start_time) · {{ $this->show->start_time->format('g:i A') }} @endif
                            @if($this->show->channel) · {{ $this->show->channel->name }} @endif
                        </div>
                    </div>
                    <button type="button" wire:click="selectShow('')" class="text-sm font-medium text-gray-500 hover:text-gray-900 dark:hover:text-white">Change show</button>
                </div>

                <div class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-3 lg:grid-cols-6 dark:bg-gray-800">
                    @foreach ([
                        ['Whatnot Orders', $whatnot['orders'] !== null ? number_format($whatnot['orders']) : '—'],
                        ['Sales', $whatnot['sales'] !== null ? '$'.number_format((float)$whatnot['sales'], 2) : '—'],
                        ['Earnings', $whatnot['earnings'] !== null ? '$'.number_format((float)$whatnot['earnings'], 2) : '—'],
                        ['Buyers', $whatnot['buyers'] !== null ? number_format($whatnot['buyers']) : '—'],
                        ['Whatnot Giveaways', $whatnot['giveaways'] !== null ? number_format($whatnot['giveaways']) : '—'],
                        ['Shipments', number_format($whatnot['shipments'] ?? 0)],
                    ] as [$label, $value])
                        <div class="bg-white p-4 dark:bg-gray-900">
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $label }}</div>
                            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="bg-blue-50 px-5 py-3 text-xs text-blue-700 dark:bg-blue-950/40 dark:text-blue-200">
                    These are Whatnot reference numbers. Your item quantities do not have to equal the Whatnot order count.
                </div>
            </section>

            {{-- Wizard --}}
            <ol class="grid grid-cols-3 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                @foreach ([1 => 'Items', 2 => 'Show Notes', 3 => 'Review'] as $n => $label)
                    <li>
                        <button type="button" wire:click="goToStep({{ $n }})"
                            class="flex w-full items-center justify-center gap-2 px-3 py-3 text-sm font-medium {{ $this->step === $n ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-200' : 'text-gray-500 dark:text-gray-400' }}">
                            <span class="flex h-6 w-6 items-center justify-center rounded-full {{ $this->step > $n ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-800' }}">{{ $this->step > $n ? '✓' : $n }}</span>
                            <span>{{ $label }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px]">
                <main class="space-y-5">
                    @if ($this->step === 1)
                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="font-semibold text-gray-950 dark:text-white">What inventory was used?</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Choose from the streamer's inventory. Classify each line as sold, giveaway, promo, or other.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:flex">
                                    <x-filament::button type="button" icon="heroicon-m-magnifying-glass" wire:click="toggleBrowse">Browse Inventory</x-filament::button>
                                    <x-filament::button type="button" color="gray" icon="heroicon-m-plus" wire:click="toggleManualItem">Unlisted Item</x-filament::button>
                                </div>
                            </div>
                        </section>

                        @if ($this->showManualItemForm)
                            <section class="rounded-2xl border border-amber-200 bg-amber-50/60 p-5 dark:border-amber-900 dark:bg-amber-950/20">
                                <div class="mb-4 flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="font-semibold text-gray-950 dark:text-white">Add an unlisted item</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">This will be flagged for admin matching instead of silently creating catalog inventory.</p>
                                    </div>
                                    <button wire:click="toggleManualItem" type="button" class="text-gray-500">✕</button>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_110px_180px_auto] sm:items-end">
                                    <label class="text-sm">
                                        <span class="mb-1 block text-gray-600 dark:text-gray-300">Item name</span>
                                        <input wire:model="manualName" type="text" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" placeholder="e.g. Japanese Mystery Slab" />
                                    </label>
                                    <label class="text-sm">
                                        <span class="mb-1 block text-gray-600 dark:text-gray-300">Qty</span>
                                        <input wire:model="manualQuantity" min="1" type="number" inputmode="numeric" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                    </label>
                                    <label class="text-sm">
                                        <span class="mb-1 block text-gray-600 dark:text-gray-300">Type</span>
                                        <select wire:model="manualDisposition" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                            @foreach(\App\Models\StreamerLogItem::DISPOSITIONS as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>
                                    <x-filament::button type="button" wire:click="addManualItemFromForm">Add</x-filament::button>
                                </div>
                            </section>
                        @endif

                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                            <div class="mb-4 flex items-center justify-between gap-3">
                                <div>
                                    <h3 class="font-semibold text-gray-950 dark:text-white">Show Items</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $summary['units'] }} total units reported</p>
                                </div>
                                @if($summary['unmatched'] > 0)
                                    <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200">{{ $summary['unmatched'] }} unmatched</span>
                                @endif
                            </div>

                            @if ($lines->isEmpty())
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-10 text-center dark:border-gray-700">
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">No items reported yet</div>
                                    <div class="mt-1 text-xs text-gray-500">Browse the streamer's inventory or add an unlisted item.</div>
                                </div>
                            @else
                                <div class="space-y-3">
                                    @foreach ($lines as $line)
                                        <article wire:key="show-line-{{ $line->id }}" class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="font-medium text-gray-950 dark:text-white">{{ $line->item_name }}</div>
                                                    @if($line->isMatched())
                                                        <div class="mt-1 text-xs text-gray-500">SKU {{ $line->inventoryItem?->sku ?? '—' }}</div>
                                                    @else
                                                        <div class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200">Unmatched inventory</div>
                                                    @endif
                                                </div>
                                                <x-filament::icon-button icon="heroicon-m-trash" color="danger" label="Remove" wire:click="removeLineItem({{ $line->id }})" wire:confirm="Remove this item from the show report?" />
                                            </div>

                                            <div class="mt-4 grid gap-3 sm:grid-cols-[110px_190px_130px_1fr] sm:items-end">
                                                <label class="text-sm">
                                                    <span class="mb-1 block text-gray-500">Quantity</span>
                                                    <input type="number" min="1" inputmode="numeric" value="{{ $line->quantity }}"
                                                        wire:change="setLineQuantity({{ $line->id }}, $event.target.value)"
                                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                                </label>
                                                <label class="text-sm">
                                                    <span class="mb-1 block text-gray-500">Type</span>
                                                    <select wire:change="setLineDisposition({{ $line->id }}, $event.target.value)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                                        @foreach(\App\Models\StreamerLogItem::DISPOSITIONS as $value => $label)
                                                            <option value="{{ $value }}" @selected(($line->disposition ?? 'sold') === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="text-sm">
                                                    <span class="mb-1 block text-gray-500">Unit Cost</span>
                                                    <input type="number" min="0" step="0.01" inputmode="decimal" value="{{ $line->unit_cost }}"
                                                        wire:change="setLineCost({{ $line->id }}, $event.target.value)"
                                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                                </label>
                                                <div class="sm:text-right">
                                                    <div class="text-xs text-gray-500">Line Cost</div>
                                                    <div class="text-base font-semibold text-gray-950 dark:text-white">${{ number_format($line->total_cost, 2) }}</div>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endif

                    @if ($this->step === 2)
                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                            <h3 class="font-semibold text-gray-950 dark:text-white">Show Notes</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Only add what the operations team needs to know. Whatnot analytics and shipments are imported automatically.</p>
                            <label class="mt-5 block text-sm">
                                <span class="mb-2 block font-medium text-gray-700 dark:text-gray-200">Notes (optional)</span>
                                <textarea rows="7" wire:model.blur="logNotes" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" placeholder="Inventory issues, unusual giveaways, item not in catalog, or anything else admin should know…"></textarea>
                            </label>
                        </section>
                    @endif

                    @if ($this->step === 3)
                        <section class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                            <h3 class="font-semibold text-gray-950 dark:text-white">Review Show Report</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Review what will be recorded against this show before submitting.</p>

                            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                                @foreach ([
                                    ['Sold', $summary['sold']],
                                    ['Giveaway', $summary['giveaway']],
                                    ['Promo', $summary['promo']],
                                    ['Other', $summary['other']],
                                ] as [$label, $value])
                                    <div class="rounded-xl bg-gray-50 p-4 dark:bg-gray-800">
                                        <div class="text-xs text-gray-500">{{ $label }}</div>
                                        <div class="mt-1 text-xl font-semibold text-gray-950 dark:text-white">{{ number_format($value) }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="text-xs text-gray-500">Total Units</div>
                                    <div class="mt-1 text-lg font-semibold">{{ number_format($summary['units']) }}</div>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="text-xs text-gray-500">Inventory Cost</div>
                                    <div class="mt-1 text-lg font-semibold">${{ number_format($summary['productCost'], 2) }}</div>
                                </div>
                                <div class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="text-xs text-gray-500">Giveaway Cost</div>
                                    <div class="mt-1 text-lg font-semibold">${{ number_format($summary['giveawayCost'], 2) }}</div>
                                </div>
                            </div>

                            @php($preview = $this->deductionPreview)
                            @if (! empty($preview))
                                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/20">
                                    <div class="font-medium text-amber-800 dark:text-amber-200">Inventory exceptions</div>
                                    <ul class="mt-2 space-y-1 text-sm text-amber-700 dark:text-amber-300">
                                        @foreach ($preview as $problem)<li>• {{ $problem }}</li>@endforeach
                                    </ul>
                                    <p class="mt-3 text-xs text-amber-700 dark:text-amber-300">You can still submit. These stay visible for admin reconciliation.</p>
                                </div>
                            @else
                                <div class="mt-5 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-700 dark:border-green-900 dark:bg-green-950/20 dark:text-green-200">✓ All matched report lines have enough stock in the streamer's inventory.</div>
                            @endif

                            <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                <x-filament::button type="button" color="gray" wire:click="goToStep(2)">Back</x-filament::button>
                                <x-filament::button type="button" wire:click="submit" wire:confirm="Submit this show report?">Submit Show Report</x-filament::button>
                            </div>
                        </section>
                    @endif

                    @if ($this->step < 3)
                        <div class="flex justify-between gap-3">
                            @if ($this->step > 1)
                                <x-filament::button type="button" color="gray" wire:click="goToStep({{ $this->step - 1 }})">Back</x-filament::button>
                            @else
                                <span></span>
                            @endif
                            <x-filament::button type="button" wire:click="goToStep({{ $this->step + 1 }})" icon="heroicon-m-arrow-right" icon-position="after">
                                {{ $this->step === 1 ? 'Continue to Notes' : 'Review Report' }}
                            </x-filament::button>
                        </div>
                    @endif
                </main>

                <aside class="space-y-4">
                    <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Report Summary</h3>
                        <dl class="mt-4 space-y-3 text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Sold</dt><dd class="font-medium">{{ $summary['sold'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Giveaways</dt><dd class="font-medium">{{ $summary['giveaway'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Promo / Bonus</dt><dd class="font-medium">{{ $summary['promo'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Other</dt><dd class="font-medium">{{ $summary['other'] }}</dd></div>
                            <div class="border-t border-gray-100 pt-3 dark:border-gray-800 flex justify-between"><dt class="text-gray-500">Total Units</dt><dd class="font-semibold">{{ $summary['units'] }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Inventory Status</h3>
                        @if($summary['items'] === 0)
                            <p class="mt-3 text-sm text-gray-500">Add show items to begin.</p>
                        @elseif($summary['unmatched'] > 0)
                            <p class="mt-3 text-sm text-amber-700 dark:text-amber-300">{{ $summary['unmatched'] }} unlisted {{ \Illuminate\Support\Str::plural('item', $summary['unmatched']) }} will need admin matching.</p>
                        @else
                            <p class="mt-3 text-sm text-green-700 dark:text-green-300">All report lines are linked to inventory.</p>
                        @endif
                    </section>
                </aside>
            </div>
        </div>

        {{-- Existing page, catalog-modal experience implemented without Filament's Alpine modal lifecycle. --}}
        @if($this->showInventoryPicker)
            <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-6" wire:click.self="toggleBrowse">
                <section class="flex max-h-[88vh] w-full max-w-5xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
                        <div>
                            <h3 class="font-semibold text-gray-950 dark:text-white">Add From Streamer Inventory</h3>
                            <p class="text-xs text-gray-500">Only stock assigned to this streamer's inventory is shown by default.</p>
                        </div>
                        <button type="button" wire:click="toggleBrowse" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">✕</button>
                    </div>
                    <div class="border-b border-gray-100 p-4 dark:border-gray-800">
                        <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search product, SKU, or brand…" autofocus
                            class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                    </div>
                    <div class="overflow-y-auto p-4">
                        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse($this->inventory as $item)
                                <article class="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
                                    <div class="min-h-14">
                                        <div class="font-medium text-gray-950 dark:text-white">{{ $item->name }}</div>
                                        <div class="mt-1 text-xs text-gray-500">SKU {{ $item->sku ?? '—' }}</div>
                                    </div>
                                    <div class="mt-4 flex items-center justify-between gap-3">
                                        <div>
                                            <div class="text-xs text-gray-500">Streamer Stock</div>
                                            <div class="font-semibold text-gray-950 dark:text-white">{{ number_format((float)($item->stock_sum_quantity ?? 0)) }}</div>
                                        </div>
                                        <x-filament::button type="button" size="sm" wire:click="addLineItem({{ $item->id }})">Add</x-filament::button>
                                    </div>
                                </article>
                            @empty
                                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-10 text-center text-sm text-gray-500 dark:border-gray-700">No streamer inventory matched this search.</div>
                            @endforelse
                        </div>
                    </div>
                </section>
            </div>
        @endif
    @endif
</x-filament-panels::page>
