@php
    $summary = $this->summary;
    $lines = $this->lineItems;
    $whatnot = $this->whatnotReference;
    $reconciliationWarnings = $this->reconciliationWarnings;
    $reportBlocked = $this->reportBlockedReason();
@endphp

<x-filament-panels::page>
    @if (! $this->show)
        <div class="mx-auto max-w-4xl space-y-3 sm:space-y-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white sm:text-lg">Choose a completed show</h2>
                <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Select the show you finished to report sold inventory, giveaways, promos, and anything not yet in the catalog.</p>
            </div>

            <div class="grid gap-2.5 sm:grid-cols-2 sm:gap-3">
                @forelse ($this->shows as $show)
                    <button type="button" wire:click="selectShow('{{ $show->id }}')"
                        class="rounded-xl border border-gray-200 bg-white p-3.5 text-left transition hover:border-primary-400 hover:shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-4">
                        <div class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">{{ $show->title }}</div>
                        <div class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-[11px] text-gray-500 dark:text-gray-400 sm:mt-2 sm:text-xs">
                            <span>{{ $show->show_date?->format('M j, Y') ?? '—' }}</span>
                            @if($show->start_time)<span>{{ $show->start_time->format('g:i A') }}</span>@endif
                            @if($show->units_sold !== null)<span>{{ number_format($show->units_sold) }} Whatnot orders</span>@endif
                        </div>
                    </button>
                @empty
                    <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 sm:rounded-2xl sm:p-10">
                        No completed shows are waiting on a report from you.
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <div class="mx-auto max-w-7xl space-y-3 pb-24 sm:space-y-5 sm:pb-0" data-vx-eos-report>
            <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl">
                <div class="flex flex-col gap-2.5 border-b border-gray-100 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5 dark:border-gray-800">
                    <div class="min-w-0">
                        <div class="text-[10px] font-bold uppercase tracking-[.12em] text-primary-600 sm:text-xs">Streamer Show Report</div>
                        <h2 class="mt-1 text-lg font-semibold leading-tight text-gray-950 dark:text-white sm:text-xl">{{ $this->show->title }}</h2>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400 sm:text-sm">
                            {{ $this->show->show_date?->format('M j, Y') }}
                            @if($this->show->start_time) · {{ $this->show->start_time->format('g:i A') }} @endif
                            @if($this->show->channel) · {{ $this->show->channel->name }} @endif
                        </div>
                    </div>
                    <div class="flex items-center justify-between gap-3 sm:flex-col sm:items-end">
                        <div class="text-[10px] font-medium text-gray-400 sm:text-xs">
                            <span wire:dirty class="vx-eos-unsaved text-amber-600">Saving…</span>
                            <span wire:loading.remove>
                                @if($this->lastSavedAt)
                                    Saved {{ \Illuminate\Support\Carbon::parse($this->lastSavedAt)->diffForHumans() }}
                                @else
                                    Autosave on
                                @endif
                            </span>
                        </div>
                        <button type="button" wire:click="selectShow('')" class="text-xs font-medium text-gray-500 hover:text-gray-900 dark:hover:text-white sm:text-sm">Change show</button>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-px bg-gray-100 sm:grid-cols-3 lg:grid-cols-6 dark:bg-gray-800">
                    @foreach ([
                        ['Orders', $whatnot['orders'] !== null ? number_format($whatnot['orders']) : '—'],
                        ['Sales', $whatnot['sales'] !== null ? '$'.number_format((float)$whatnot['sales'], 2) : '—'],
                        ['Earnings', $whatnot['earnings'] !== null ? '$'.number_format((float)$whatnot['earnings'], 2) : '—'],
                        ['Buyers', $whatnot['buyers'] !== null ? number_format($whatnot['buyers']) : '—'],
                        ['Giveaways', $whatnot['giveaways'] !== null ? number_format($whatnot['giveaways']) : '—'],
                        ['Shipments', number_format($whatnot['shipments'] ?? 0)],
                    ] as [$label, $value])
                        <div class="min-w-0 bg-white px-2.5 py-2.5 dark:bg-gray-900 sm:p-4">
                            <div class="truncate text-[9px] font-medium uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">{{ $label }}</div>
                            <div class="mt-0.5 truncate text-sm font-semibold text-gray-950 dark:text-white sm:mt-1 sm:text-lg">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="bg-blue-50 px-3 py-2 text-[10px] leading-4 text-blue-700 dark:bg-blue-950/40 dark:text-blue-200 sm:px-5 sm:py-3 sm:text-xs">
                    Whatnot totals are reference data. Physical inventory units do not have to match Whatnot transactions one-for-one.
                </div>
            </section>

            @if ($reportBlocked)
                <section class="rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900 dark:bg-amber-950/40 sm:rounded-2xl sm:p-5">
                    <div class="flex items-start gap-3">
                        <x-heroicon-o-exclamation-triangle class="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-amber-900 dark:text-amber-100 sm:text-base">This report cannot be started yet</h3>
                            <p class="mt-1 text-xs leading-5 text-amber-800 dark:text-amber-200 sm:text-sm">{{ $reportBlocked }}</p>
                        </div>
                    </div>
                </section>
            @else

            <ol class="grid grid-cols-3 overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                @foreach ([1 => 'Items', 2 => 'Notes', 3 => 'Review'] as $n => $label)
                    <li>
                        <button type="button" wire:click="goToStep({{ $n }})"
                            class="flex min-h-11 w-full items-center justify-center gap-1.5 px-2 py-2 text-xs font-medium sm:gap-2 sm:px-3 sm:py-3 sm:text-sm {{ $this->step === $n ? 'bg-primary-50 text-primary-700 dark:bg-primary-950/40 dark:text-primary-200' : 'text-gray-500 dark:text-gray-400' }}">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] sm:h-6 sm:w-6 sm:text-xs {{ $this->step > $n ? 'bg-green-100 text-green-700 dark:bg-green-950 dark:text-green-200' : 'bg-gray-100 dark:bg-gray-800' }}">{{ $this->step > $n ? '✓' : $n }}</span>
                            <span>{{ $label }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>

            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_300px] lg:gap-5">
                <main class="space-y-3 sm:space-y-5">
                    @if ($this->step === 1)
                        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">What inventory was used?</h3>
                                    <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Choose from inventory currently held by this streamer. Classify each line as Sold, Giveaway, Promo / Bonus, or Other.</p>
                                </div>
                                <div class="grid grid-cols-2 gap-2 sm:flex">
                                    <x-filament::button type="button" icon="heroicon-m-magnifying-glass" wire:click="toggleBrowse">Browse Inventory</x-filament::button>
                                    <x-filament::button type="button" color="gray" icon="heroicon-m-plus" wire:click="toggleManualItem">Unlisted Item</x-filament::button>
                                </div>
                            </div>
                        </section>

                        @if ($this->showManualItemForm)
                            <section class="rounded-xl border border-amber-200 bg-amber-50/60 p-4 dark:border-amber-900 dark:bg-amber-950/20 sm:rounded-2xl sm:p-5">
                                <div class="mb-3 flex items-start justify-between gap-3 sm:mb-4">
                                    <div>
                                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Add an unlisted item</h3>
                                        <p class="text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Use this when the exact product is not in inventory. Admin can match it later.</p>
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

                        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                            <div class="mb-3 flex items-center justify-between gap-3 sm:mb-4">
                                <div>
                                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Show Items</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 sm:text-sm">{{ $summary['units'] }} total units reported</p>
                                </div>
                                @if($summary['unmatched'] > 0)
                                    <span class="rounded-full bg-amber-100 px-2 py-1 text-[10px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200 sm:px-2.5 sm:text-xs">{{ $summary['unmatched'] }} unmatched</span>
                                @endif
                            </div>

                            @if ($lines->isEmpty())
                                <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-gray-700 sm:py-10">
                                    <div class="text-sm font-medium text-gray-700 dark:text-gray-200">No items reported yet</div>
                                    <div class="mt-1 text-xs text-gray-500">Browse the streamer's inventory or add an unlisted item.</div>
                                </div>
                            @else
                                <div class="space-y-2.5 sm:space-y-3">
                                    @foreach ($lines as $line)
                                        <article wire:key="show-line-{{ $line->id }}" class="rounded-xl border border-gray-200 p-3 dark:border-gray-700 sm:p-4">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-medium text-gray-950 dark:text-white sm:text-base">{{ $line->item_name }}</div>
                                                    @if($line->isMatched())
                                                        <div class="mt-1 text-[10px] text-gray-500 sm:text-xs">SKU {{ $line->inventoryItem?->sku ?? '—' }}</div>
                                                    @else
                                                        <div class="mt-1 inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-950 dark:text-amber-200 sm:text-xs">Unmatched inventory</div>
                                                    @endif
                                                </div>
                                                <x-filament::icon-button icon="heroicon-m-trash" color="danger" label="Remove" wire:click="removeLineItem({{ $line->id }})" wire:confirm="Remove this item from the show report?" />
                                            </div>

                                            <div class="mt-3 grid grid-cols-2 gap-2.5 sm:mt-4 sm:grid-cols-[110px_190px_130px_1fr] sm:gap-3 sm:items-end">
                                                <label class="text-xs sm:text-sm">
                                                    <span class="mb-1 block text-gray-500">Quantity</span>
                                                    <input type="number" min="1" inputmode="numeric" value="{{ $line->quantity }}"
                                                        wire:change="setLineQuantity({{ $line->id }}, $event.target.value)"
                                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                                </label>
                                                <label class="text-xs sm:text-sm">
                                                    <span class="mb-1 block text-gray-500">Type</span>
                                                    <select wire:change="setLineDisposition({{ $line->id }}, $event.target.value)" class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800">
                                                        @foreach(\App\Models\StreamerLogItem::DISPOSITIONS as $value => $label)
                                                            <option value="{{ $value }}" @selected(($line->disposition ?? 'sold') === $value)>{{ $label }}</option>
                                                        @endforeach
                                                    </select>
                                                </label>
                                                <label class="text-xs sm:text-sm">
                                                    <span class="mb-1 block text-gray-500">Unit Cost</span>
                                                    <input type="number" min="0" step="0.01" inputmode="decimal" value="{{ $line->unit_cost }}"
                                                        wire:change="setLineCost({{ $line->id }}, $event.target.value)"
                                                        class="w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800" />
                                                </label>
                                                <div class="self-end text-right">
                                                    <div class="text-[10px] text-gray-500 sm:text-xs">Line Cost</div>
                                                    <div class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">${{ number_format($line->total_cost, 2) }}</div>
                                                </div>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endif

                    @if ($this->step === 2)
                        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Show Notes</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Only add what operations needs to know. Notes autosave while you type.</p>
                            <label class="mt-4 block text-sm sm:mt-5">
                                <span class="mb-2 block font-medium text-gray-700 dark:text-gray-200">Notes (optional)</span>
                                <textarea rows="6" wire:model.live.debounce.700ms="logNotes" class="w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" placeholder="Inventory issues, unusual giveaways, item not in catalog, or anything else admin should know…"></textarea>
                            </label>
                        </section>
                    @endif

                    @if ($this->step === 3)
                        <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-5">
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Review Show Report</h3>
                            <p class="mt-1 text-xs leading-5 text-gray-500 dark:text-gray-400 sm:text-sm">Review the physical inventory recorded against this show before submitting.</p>

                            <div class="mt-4 grid grid-cols-2 gap-2 sm:mt-5 sm:grid-cols-4 sm:gap-3">
                                @foreach ([
                                    ['Sold', $summary['sold']],
                                    ['Giveaway', $summary['giveaway']],
                                    ['Promo', $summary['promo']],
                                    ['Other', $summary['other']],
                                ] as [$label, $value])
                                    <div class="rounded-lg bg-gray-50 p-3 dark:bg-gray-800 sm:rounded-xl sm:p-4">
                                        <div class="text-[10px] text-gray-500 sm:text-xs">{{ $label }}</div>
                                        <div class="mt-0.5 text-lg font-semibold text-gray-950 dark:text-white sm:mt-1 sm:text-xl">{{ number_format($value) }}</div>
                                    </div>
                                @endforeach
                            </div>

                            <div class="mt-3 grid grid-cols-3 gap-2 sm:mt-4 sm:gap-3">
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:rounded-xl sm:p-4">
                                    <div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Units</div>
                                    <div class="mt-0.5 text-base font-semibold sm:mt-1 sm:text-lg">{{ number_format($summary['units']) }}</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:rounded-xl sm:p-4">
                                    <div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Inv. Cost</div>
                                    <div class="mt-0.5 truncate text-sm font-semibold sm:mt-1 sm:text-lg">${{ number_format($summary['productCost'], 2) }}</div>
                                </div>
                                <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700 sm:rounded-xl sm:p-4">
                                    <div class="text-[9px] uppercase tracking-wide text-gray-400 sm:text-xs sm:normal-case sm:tracking-normal">Giveaway Cost</div>
                                    <div class="mt-0.5 truncate text-sm font-semibold sm:mt-1 sm:text-lg">${{ number_format($summary['giveawayCost'], 2) }}</div>
                                </div>
                            </div>

                            @if($reconciliationWarnings)
                                <div class="mt-4 rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-900 dark:bg-blue-950/20 sm:mt-5 sm:p-4">
                                    <div class="flex items-start gap-2.5">
                                        <x-heroicon-m-information-circle class="mt-0.5 h-4 w-4 shrink-0 text-blue-600" />
                                        <div>
                                            <div class="text-xs font-semibold text-blue-900 dark:text-blue-100 sm:text-sm">Whatnot reference differences</div>
                                            <div class="mt-1 space-y-1 text-[11px] leading-5 text-blue-700 dark:text-blue-300 sm:text-xs">
                                                @foreach($reconciliationWarnings as $warning)<p>{{ $warning }}</p>@endforeach
                                            </div>
                                            <p class="mt-1.5 text-[10px] leading-4 text-blue-600 dark:text-blue-400">These do not block submission. Transactions and physical inventory units can legitimately differ.</p>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- Block form deliberately: an inline @ php(...) is collected by the same
                                 raw-block pass as @ php ... @ endphp and pairs with the next block's
                                 closing tag, which silently moves the boundary and breaks compilation
                                 somewhere else entirely. This file already had that latent. --}}
                            @php
                                $preview = $this->deductionPreview;
                            @endphp
                            @if (! empty($preview))
                                <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900 dark:bg-amber-950/20 sm:mt-5 sm:p-4">
                                    <div class="text-xs font-semibold text-amber-800 dark:text-amber-200 sm:text-sm">Inventory exceptions</div>
                                    <ul class="mt-2 space-y-1 text-xs leading-5 text-amber-700 dark:text-amber-300 sm:text-sm">
                                        @foreach ($preview as $problem)<li>• {{ $problem }}</li>@endforeach
                                    </ul>
                                    <p class="mt-2 text-[10px] text-amber-700 dark:text-amber-300 sm:mt-3 sm:text-xs">You can still submit. These stay visible for admin reconciliation.</p>
                                </div>
                            @else
                                <div class="mt-4 rounded-xl border border-green-200 bg-green-50 p-3 text-xs text-green-700 dark:border-green-900 dark:bg-green-950/20 dark:text-green-200 sm:mt-5 sm:p-4 sm:text-sm">✓ All matched report lines have enough stock in the streamer's inventory.</div>
                            @endif

                            <div class="mt-5 hidden gap-2 sm:flex sm:justify-end">
                                <x-filament::button type="button" color="gray" wire:click="goToStep(2)">Back</x-filament::button>
                                <x-filament::button type="button" wire:click="submit" wire:confirm="Submit this show report?">Submit Show Report</x-filament::button>
                            </div>
                        </section>
                    @endif

                    @if ($this->step < 3)
                        <div class="hidden justify-between gap-3 sm:flex">
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

                <aside class="space-y-3 sm:space-y-4">
                    <section class="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-4">
                        <h3 class="text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Report Summary</h3>
                        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-xs sm:mt-4 sm:block sm:space-y-3 sm:text-sm">
                            <div class="flex justify-between"><dt class="text-gray-500">Sold</dt><dd class="font-medium">{{ $summary['sold'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Giveaways</dt><dd class="font-medium">{{ $summary['giveaway'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Promo</dt><dd class="font-medium">{{ $summary['promo'] }}</dd></div>
                            <div class="flex justify-between"><dt class="text-gray-500">Other</dt><dd class="font-medium">{{ $summary['other'] }}</dd></div>
                            <div class="col-span-2 flex justify-between border-t border-gray-100 pt-2 dark:border-gray-800 sm:pt-3"><dt class="text-gray-500">Total Units</dt><dd class="font-semibold">{{ $summary['units'] }}</dd></div>
                        </dl>
                    </section>

                    <section class="rounded-xl border border-gray-200 bg-white p-3.5 dark:border-gray-700 dark:bg-gray-900 sm:rounded-2xl sm:p-4">
                        <h3 class="text-xs font-semibold text-gray-950 dark:text-white sm:text-sm">Inventory Status</h3>
                        @if($summary['items'] === 0)
                            <p class="mt-2 text-xs text-gray-500 sm:mt-3 sm:text-sm">Add show items to begin.</p>
                        @elseif($summary['unmatched'] > 0)
                            <p class="mt-2 text-xs leading-5 text-amber-700 dark:text-amber-300 sm:mt-3 sm:text-sm">{{ $summary['unmatched'] }} unlisted {{ \Illuminate\Support\Str::plural('item', $summary['unmatched']) }} will need admin matching.</p>
                        @else
                            <p class="mt-2 text-xs text-green-700 dark:text-green-300 sm:mt-3 sm:text-sm">All report lines are linked to inventory.</p>
                        @endif
                    </section>
                </aside>
            </div>
            @endif
        </div>

        {{-- Mobile sticky workflow actions --}}
        @unless ($reportBlocked)
        <div class="fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 px-3 pb-[max(.65rem,env(safe-area-inset-bottom))] pt-2.5 shadow-[0_-8px_24px_rgba(15,23,42,.08)] backdrop-blur dark:border-gray-700 dark:bg-gray-900/95 sm:hidden" data-vx-mobile-actions>
            <div class="mx-auto flex max-w-7xl gap-2">
                @if($this->step === 1)
                    <button type="button" wire:click="toggleBrowse" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Add Item</button>
                    <button type="button" wire:click="goToStep(2)" class="inline-flex min-h-11 flex-[1.25] items-center justify-center rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white">Continue</button>
                @elseif($this->step === 2)
                    <button type="button" wire:click="goToStep(1)" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Back</button>
                    <button type="button" wire:click="goToStep(3)" class="inline-flex min-h-11 flex-[1.25] items-center justify-center rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white">Review Report</button>
                @else
                    <button type="button" wire:click="goToStep(2)" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">Back</button>
                    <button type="button" wire:click="submit" wire:confirm="Submit this show report?" class="inline-flex min-h-11 flex-[1.35] items-center justify-center rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white">Submit Report</button>
                @endif
            </div>
        </div>
        @endunless

        @if($this->showInventoryPicker && ! $reportBlocked)
            <div class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 p-0 sm:items-center sm:p-6" wire:click.self="toggleBrowse">
                <section class="flex h-[92vh] w-full max-w-7xl flex-col overflow-hidden rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl dark:bg-gray-900">
                    <div class="flex items-center justify-between gap-3 border-b border-gray-200 p-4 dark:border-gray-700">
                        <div>
                            <h3 class="text-sm font-semibold text-gray-950 dark:text-white sm:text-base">Add From Streamer Inventory</h3>
                            <p class="text-[11px] leading-4 text-gray-500 sm:text-xs">Shows stock currently held in this streamer's inventory locations.</p>
                        </div>
                        <button type="button" wire:click="toggleBrowse" class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800">✕</button>
                    </div>
                    <div class="border-b border-gray-100 p-3 dark:border-gray-800 sm:p-4">
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_200px_auto] sm:items-center">
                            <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search product, SKU, or brand…" autofocus
                                class="min-h-11 w-full rounded-xl border-gray-300 dark:border-gray-600 dark:bg-gray-800" />

                            <select wire:model.live="pickerCategory" class="min-h-11 w-full rounded-xl border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                                <option value="">All categories</option>
                                @foreach($this->pickerCategories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @endforeach
                            </select>

                            <label class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-gray-300 px-3 dark:border-gray-600">
                                <input type="checkbox" wire:model.live="pickerStagedOnly" class="rounded border-gray-300 text-primary-600" />
                                <span class="whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">Selected only</span>
                            </label>
                        </div>

                        {{--
                            Say how many matched, not just how many are drawn.
                            A silent cut reads as "we do not stock that" when it
                            means "narrow your search", and the two look
                            identical on screen.
                        --}}
                        @php $total = $this->inventoryTotal; @endphp
                        <p class="mt-2 text-[11px] text-gray-500 sm:text-xs">
                            @if($total === 0)
                                Nothing matches.
                            @elseif($total > $this->inventory->count())
                                Showing {{ number_format($this->inventory->count()) }} of {{ number_format($total) }} — keep typing to narrow it down.
                            @else
                                {{ number_format($total) }} {{ Str::plural('item', $total) }}.
                            @endif
                        </p>
                    </div>
                    @php
                        $staged = $this->stagedSummary;
                        $alreadyInReport = $lines->groupBy('inventory_item_id')->map(fn ($rows) => $rows->sum('quantity'));
                    @endphp

                    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
                        <div class="grid gap-2.5 sm:grid-cols-2 sm:gap-3 lg:grid-cols-3 xl:grid-cols-4">
                            @forelse($this->inventory as $item)
                                @php $stagedQty = (int) ($this->stagedQuantities[$item->id] ?? 0); @endphp
                                <article @class([
                                    'rounded-xl border p-3 transition sm:p-4',
                                    'border-primary-500 bg-primary-50/50 dark:border-primary-500 dark:bg-primary-950/20' => $stagedQty > 0,
                                    'border-gray-200 dark:border-gray-700' => $stagedQty === 0,
                                ])>
                                    <div class="min-h-12 sm:min-h-14">
                                        <div class="text-sm font-medium text-gray-950 dark:text-white sm:text-base">{{ $item->name }}</div>
                                        <div class="mt-1 text-[10px] text-gray-500 sm:text-xs">
                                            SKU {{ $item->sku ?? '—' }}
                                            @if(($alreadyInReport[$item->id] ?? 0) > 0)
                                                · <span class="font-medium text-green-700 dark:text-green-300">{{ $alreadyInReport[$item->id] }} already on this report</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="mt-3 flex items-center justify-between gap-3 sm:mt-4">
                                        <div>
                                            <div class="text-[10px] text-gray-500 sm:text-xs">On Hand</div>
                                            <div class="font-semibold text-gray-950 dark:text-white">{{ number_format((float)($item->stock_sum_quantity ?? 0)) }}</div>
                                        </div>

                                        @if($stagedQty === 0)
                                            <x-filament::button type="button" size="sm" wire:click="stageItem({{ $item->id }})">Add</x-filament::button>
                                        @else
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" wire:click="stageItem({{ $item->id }}, -1)"
                                                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-lg font-medium leading-none text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                    aria-label="One fewer {{ $item->name }}">−</button>
                                                <input type="number" min="0" inputmode="numeric"
                                                    value="{{ $stagedQty }}"
                                                    wire:change="setStagedQuantity({{ $item->id }}, $event.target.value)"
                                                    class="h-9 w-14 rounded-lg border-gray-300 text-center text-sm font-semibold dark:border-gray-600 dark:bg-gray-800"
                                                    aria-label="Quantity of {{ $item->name }}" />
                                                <button type="button" wire:click="stageItem({{ $item->id }}, 1)"
                                                    class="flex h-9 w-9 items-center justify-center rounded-lg border border-gray-300 text-lg font-medium leading-none text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                                    aria-label="One more {{ $item->name }}">+</button>
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-gray-700 sm:p-10">
                                    {{ $this->pickerStagedOnly ? 'Nothing selected yet.' : 'No streamer inventory matched this search.' }}
                                </div>
                            @endforelse
                        </div>

                        @if($total > $this->inventory->count())
                            <div class="mt-3 text-center">
                                <button type="button" wire:click="showMoreInventory"
                                    class="inline-flex min-h-10 items-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                                    Show 60 more
                                </button>
                            </div>
                        @endif
                    </div>

                    {{-- Running total. Sticky so the basket stays readable while scrolling a long catalog. --}}
                    <div class="border-t border-gray-200 bg-gray-50 p-3 pb-[max(.75rem,env(safe-area-inset-bottom))] dark:border-gray-700 dark:bg-gray-900/60 sm:p-4">
                        <div class="flex flex-col gap-2.5 sm:flex-row sm:items-center sm:justify-between sm:gap-4">
                            <div class="min-w-0 text-xs text-gray-600 dark:text-gray-300 sm:text-sm">
                                @if($staged['items'] === 0)
                                    Nothing selected yet — search above and add what this show used.
                                @else
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ $staged['items'] }}</span>
                                    {{ Str::plural('item', $staged['items']) }} ·
                                    <span class="font-semibold text-gray-950 dark:text-white">{{ number_format($staged['units']) }}</span>
                                    {{ Str::plural('unit', $staged['units']) }}
                                    @if($staged['cost'] > 0)
                                        · <span class="font-semibold text-gray-950 dark:text-white">${{ number_format($staged['cost'], 2) }}</span> at cost
                                    @endif
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                @if($staged['items'] > 0)
                                    <button type="button" wire:click="clearStaged"
                                        class="min-h-10 rounded-lg px-3 text-xs font-medium text-gray-500 hover:text-gray-900 dark:hover:text-white sm:text-sm">Clear</button>
                                @endif
                                <x-filament::button type="button" wire:click="addStagedItems" :disabled="$staged['items'] === 0">
                                    @if($staged['items'] === 0)
                                        Add to report
                                    @else
                                        Add {{ number_format($staged['units']) }} {{ Str::plural('unit', $staged['units']) }} to report
                                    @endif
                                </x-filament::button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        @endif

        <script>
            (() => {
                if (window.__vxEosUnloadGuardInstalled) return;
                window.__vxEosUnloadGuardInstalled = true;

                window.addEventListener('beforeunload', (event) => {
                    const marker = document.querySelector('.vx-eos-unsaved');
                    if (!marker || getComputedStyle(marker).display === 'none') return;
                    event.preventDefault();
                    event.returnValue = '';
                });
            })();
        </script>
    @endif
</x-filament-panels::page>
