@php
    $summary = $this->summary;
    $lines = $this->lineItems;
    $whatnot = $this->whatnotReference;
    $reportBlocked = $this->reportBlockedReason();
    $reportEntry = $this->logEntry();
    $reportSubmitted = $reportEntry?->isSubmitted() && $reportEntry?->status !== 'changes_requested';
    $dispositions = \App\Models\StreamerLogItem::DISPOSITIONS;
    $steps = [1 => 'Items', 2 => 'Time & notes', 3 => 'Review'];
    $isAdminViewer = auth()->user()?->isAdmin() || auth()->user()?->isOwner();
@endphp

<x-filament-panels::page>
    {{-- A scan anywhere on this page goes into the report. The camera reports
         through a window event carrying only the code; the page resolves it
         to an item, checks it is one this report may draw on, and stages it. --}}
    <div x-data x-on:barcode-scanned.window="$wire.scanIntoPicker($event.detail.value)"></div>

    @if (! $this->show)
        <div class="vxw" style="max-width:960px">
            <section class="vxw-card">
                <div class="vxw-card-body">
                    <div class="vxw-eyebrow">Log a show</div>
                    <h2 class="vxw-h1">Which show did you just finish?</h2>
                    <p class="vxw-sub">Pick it below, then add the items you used — sold, given away, or promo.</p>
                </div>
            </section>

            <div class="vxw-tiles">
                @forelse ($this->shows as $show)
                    <button type="button" wire:key="pick-show-{{ $show->id }}" wire:click="selectShow('{{ $show->id }}')" wire:loading.attr="disabled" wire:target="selectShow" class="vxw-tile">
                        <span class="vxw-tile-title">{{ $show->title }}</span>
                        <span class="vxw-row-line">
                            <span>{{ $show->show_date?->format('D, M j') ?? '—' }}</span>
                            @if($show->start_time)<span>{{ $show->start_time->format('g:i A') }}</span>@endif
                            @if($show->units_sold !== null)<span>{{ number_format($show->units_sold) }} Whatnot orders</span>@endif
                        </span>
                        <span class="vxw-tile-foot">
                            <span class="vxw-link text-[13px]">Start report</span>
                            <x-filament::icon icon="heroicon-m-arrow-right" class="h-4 w-4 text-[var(--vxw-faint)]" />
                        </span>
                    </button>
                @empty
                    <div class="vxw-card col-span-full">
                        <div class="vxw-empty">
                            <x-filament::icon icon="heroicon-o-check-circle" />
                            <div class="vxw-empty-title">Nothing to log right now</div>
                            <div class="vxw-empty-text">No completed shows are waiting on a report from you.</div>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <div class="vxw" data-vx-eos-report>
            {{-- Show header + Whatnot reference numbers --}}
            <section class="vxw-card">
                <div class="vxw-card-body">
                    <div class="vxw-hero">
                        <div>
                            <div class="vxw-eyebrow">
                                {{ $this->show->show_date?->format('D, M j, Y') }}
                                @if($this->show->start_time) · {{ $this->show->start_time->format('g:i A') }} @endif
                                @if($this->show->channel) · {{ $this->show->channel->name }} @endif
                            </div>
                            <h2 class="vxw-h1">{{ $this->show->title }}</h2>
                        </div>
                        <div class="vxw-hero-actions" style="width:auto">
                            <span class="vxw-meta inline-flex items-center gap-1.5" aria-live="polite">
                                <span wire:dirty class="vx-eos-unsaved inline-flex items-center gap-1.5 vxw-tone-warn"><span class="vxw-spin" aria-hidden="true"></span>Saving…</span>
                                <span wire:loading.remove>
                                    @if($this->lastSavedAt)
                                        <x-filament::icon icon="heroicon-m-check" class="inline h-3.5 w-3.5 vxw-tone-ok" /> Saved {{ \Illuminate\Support\Carbon::parse($this->lastSavedAt)->diffForHumans() }}
                                    @else
                                        Autosave on
                                    @endif
                                </span>
                            </span>
                            <button type="button" wire:click="selectShow('')" class="vxw-btn vxw-btn--sm">Change show</button>
                        </div>
                    </div>
                </div>

                {{-- No order count. It is Whatnot's tally of transactions, and
                     nothing on this page is reconciled against it — the report
                     records what physically left the shelf, giveaways and
                     promos included, which Whatnot has no order for. Showing
                     it invited a comparison that was never meant to balance. --}}
                <div class="vxw-stats">
                    @foreach ([
                        ['Sales', $whatnot['sales'] !== null ? '$'.number_format((float)$whatnot['sales'], 2) : '—'],
                        ['Earnings', $whatnot['earnings'] !== null ? '$'.number_format((float)$whatnot['earnings'], 2) : '—'],
                        ['Buyers', $whatnot['buyers'] !== null ? number_format($whatnot['buyers']) : '—'],
                        ['Giveaways', $whatnot['giveaways'] !== null ? number_format($whatnot['giveaways']) : '—'],
                        ['Shipments', number_format($whatnot['shipments'] ?? 0)],
                    ] as [$label, $value])
                        <div class="vxw-stat">
                            <div class="vxw-stat-label">{{ $label }}</div>
                            <div class="vxw-stat-value" style="font-size:17px">{{ $value }}</div>
                        </div>
                    @endforeach
                </div>
                <div class="vxw-card-foot" style="justify-content:flex-start;gap:8px">
                    <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4 text-[var(--vxw-faint)]" />
                    <span class="vxw-meta">Whatnot numbers are for reference. Items you log don't have to match Whatnot orders one-for-one.</span>
                </div>
            </section>

            @if ($reportBlocked)
                <div class="vxw-alert vxw-alert--warn" role="alert">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                    <div>
                        <div class="font-semibold">This report cannot be started yet</div>
                        <div class="mt-0.5">{{ $reportBlocked }}</div>
                        @if($isAdminViewer)
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <a class="vxw-btn vxw-btn--sm" href="{{ \App\Filament\Resources\ShowResource::getUrl('view', ['record' => $this->show]) }}">Open show</a>
                                <span class="vxw-meta">Assign someone there, or use <strong>Detect Streamers</strong> on the Shows list.</span>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                <nav class="vxw-wizard" aria-label="Report steps">
                    @foreach ($steps as $n => $label)
                        <button type="button" wire:click="goToStep({{ $n }})" @class(['is-done' => $this->step > $n]) @if($this->step === $n) aria-current="step" @endif>
                            <span class="vxw-stage-dot">@if($this->step > $n)<x-filament::icon icon="heroicon-m-check" />@else{{ $n }}@endif</span>
                            <span>{{ $label }}</span>
                        </button>
                    @endforeach
                </nav>

                <div class="vxw-split">
                    <main class="grid gap-4">
                        @if ($this->step === 1)
                            <section class="vxw-card">
                                <div class="vxw-card-head">
                                    <div>
                                        <h3 class="vxw-h2">What did you use?</h3>
                                        <p class="vxw-sub">Search or scan the items from this show. Change the type for giveaways or promos.</p>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <button type="button" class="vxw-btn vxw-btn--primary" wire:click="toggleBrowse" wire:loading.attr="disabled" wire:target="toggleBrowse">
                                            <x-filament::icon icon="heroicon-m-magnifying-glass" /> Add items
                                        </button>
                                        <button type="button" class="vxw-btn"
                                            x-on:click="window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan an item', helper: 'Adds it to this report' } }))">
                                            <x-filament::icon icon="heroicon-m-camera" /> Scan
                                        </button>
                                        <button type="button" class="vxw-btn vxw-btn--ghost" wire:click="toggleManualItem">
                                            <x-filament::icon icon="heroicon-m-plus" /> Unlisted item
                                        </button>
                                    </div>
                                </div>

                                @if ($this->showManualItemForm)
                                    <div class="vxw-card-body" style="background:var(--vxw-warn-soft);border-bottom:1px solid var(--vxw-border)">
                                        <div class="mb-3 flex items-start justify-between gap-3">
                                            <div>
                                                <div class="vxw-label">Add an item that isn't in inventory</div>
                                                <div class="vxw-hint">An admin will match it to a product later.</div>
                                            </div>
                                            <button type="button" class="vxw-iconbtn" wire:click="toggleManualItem" aria-label="Close"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                                        </div>
                                        <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_100px_170px_auto] sm:items-end">
                                            <label class="vxw-field">
                                                <span class="vxw-label">Item name</span>
                                                <input wire:model="manualName" type="text" class="vxw-input" placeholder="e.g. Japanese Mystery Slab" />
                                            </label>
                                            <label class="vxw-field">
                                                <span class="vxw-label">Qty</span>
                                                <input wire:model="manualQuantity" min="1" type="number" inputmode="numeric" class="vxw-input" />
                                            </label>
                                            <label class="vxw-field">
                                                <span class="vxw-label">Type</span>
                                                <select wire:model="manualDisposition" class="vxw-select">
                                                    @foreach($dispositions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                                                </select>
                                            </label>
                                            <button type="button" class="vxw-btn vxw-btn--primary" wire:click="addManualItemFromForm" wire:loading.attr="disabled" wire:target="addManualItemFromForm">Add</button>
                                        </div>
                                    </div>
                                @endif

                                <div class="vxw-card-head" style="border-bottom:0;padding-bottom:4px">
                                    <div class="vxw-meta"><strong class="text-[var(--vxw-text)]">{{ number_format($summary['units']) }}</strong> {{ \Illuminate\Support\Str::plural('unit', $summary['units']) }} on this report</div>
                                    @if($summary['unmatched'] > 0)
                                        <span class="vxw-pill vxw-pill--warn">{{ $summary['unmatched'] }} unmatched</span>
                                    @endif
                                </div>

                                @if ($lines->isEmpty())
                                    <div class="vxw-empty">
                                        <x-filament::icon icon="heroicon-o-archive-box" />
                                        <div class="vxw-empty-title">No items yet</div>
                                        <div class="vxw-empty-text">Tap <strong>Add items</strong> to pick from inventory, or scan a barcode.</div>
                                    </div>
                                @else
                                    <div class="grid gap-2 px-[18px] pb-4 max-sm:px-[14px]">
                                        @foreach ($lines as $line)
                                            <article wire:key="show-line-{{ $line->id }}" class="rounded-[var(--vxw-radius-sm)] border border-[var(--vxw-border)] p-3">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0">
                                                        <div class="text-[14px] font-semibold leading-snug text-[var(--vxw-text)]">{{ $line->item_name }}</div>
                                                        @if($line->isMatched())
                                                            <div class="vxw-meta mt-0.5">SKU {{ $line->inventoryItem?->sku ?? '—' }}</div>
                                                        @else
                                                            <span class="vxw-pill vxw-pill--warn mt-1" style="height:20px;font-size:11px">Not in inventory</span>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-1">
                                                        <div class="text-right">
                                                            <div class="vxw-fig-label">Line cost</div>
                                                            <div class="vxw-fig-value">${{ number_format($line->total_cost, 2) }}</div>
                                                        </div>
                                                        <button type="button" class="vxw-iconbtn vxw-iconbtn--danger" wire:click="removeLineItem({{ $line->id }})" wire:confirm="Remove this item from the show report?" aria-label="Remove {{ $line->item_name }}">
                                                            <x-filament::icon icon="heroicon-m-trash" />
                                                        </button>
                                                    </div>
                                                </div>

                                                <div class="mt-3 flex flex-wrap items-end gap-x-4 gap-y-3">
                                                    <div class="vxw-field">
                                                        <span class="vxw-hint">Quantity</span>
                                                        <div class="vxw-qty">
                                                            <button type="button" wire:click="setLineQuantity({{ $line->id }}, {{ max(1, $line->quantity - 1) }})" @disabled($line->quantity <= 1) aria-label="One fewer">−</button>
                                                            <input type="number" min="1" inputmode="numeric" value="{{ $line->quantity }}" wire:change="setLineQuantity({{ $line->id }}, $event.target.value)" aria-label="Quantity of {{ $line->item_name }}" />
                                                            <button type="button" wire:click="setLineQuantity({{ $line->id }}, {{ $line->quantity + 1 }})" aria-label="One more">+</button>
                                                        </div>
                                                    </div>
                                                    <div class="vxw-field">
                                                        <span class="vxw-hint">Type</span>
                                                        <div class="vxw-seg" role="group" aria-label="Type for {{ $line->item_name }}">
                                                            @foreach($dispositions as $value => $label)
                                                                <button type="button" aria-pressed="{{ ($line->disposition ?? 'sold') === $value ? 'true' : 'false' }}"
                                                                    @if(($line->disposition ?? 'sold') !== $value) wire:click="setLineDisposition({{ $line->id }}, '{{ $value }}')" @endif>{{ $value === 'promo' ? 'Promo' : $label }}</button>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    <div class="vxw-field" style="width:130px">
                                                        <span class="vxw-hint">Unit cost</span>
                                                        <input type="number" min="0" step="0.01" inputmode="decimal" class="vxw-input vxw-num" style="min-height:38px"
                                                            value="{{ number_format($line->effectiveUnitCost(), 2, '.', '') }}"
                                                            wire:change="setLineCost({{ $line->id }}, $event.target.value)" aria-label="Unit cost of {{ $line->item_name }}" />
                                                    </div>
                                                    <div class="pb-2 text-[12px]">
                                                        @if ($line->costIsFromInventory())
                                                            <span class="vxw-meta">{{ $line->isMatched() ? 'From inventory' : 'Type the cost' }}</span>
                                                        @else
                                                            <button type="button" wire:click="clearLineCost({{ $line->id }})" class="vxw-link">Use inventory cost</button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                @endif
                            </section>
                        @endif

                        @if ($this->step === 2)
                            {{-- Hours and shipments are what the profit share's
                                 burden is worked out from, so they are filled in
                                 from the show rather than asked for cold — and
                                 left editable, because the person who ran it knows
                                 when the recorded length is wrong. --}}
                            <section class="vxw-card">
                                <div class="vxw-card-head">
                                    <div>
                                        <h3 class="vxw-h2">Time &amp; Shipments</h3>
                                        <p class="vxw-sub">Filled in from the show. Change either one if it's wrong — your pay is worked out from what's here.</p>
                                    </div>
                                </div>
                                <div class="vxw-card-body grid gap-4 sm:grid-cols-2">
                                    <label class="vxw-field">
                                        <span class="vxw-label">Hours streamed</span>
                                        <input type="number" min="0" step="0.01" inputmode="decimal" wire:model.blur="hoursStreamed" class="vxw-input vxw-num" />
                                        <span class="vxw-hint">Show length on Whatnot: {{ $this->show?->show_duration ? number_format($this->show->show_duration / 60, 2) . ' hrs' : 'not recorded yet' }}</span>
                                    </label>
                                    <label class="vxw-field">
                                        <span class="vxw-label">Shipments</span>
                                        <input type="number" min="0" step="1" inputmode="numeric" wire:model.blur="shipments" class="vxw-input vxw-num" />
                                        <span class="vxw-hint">Counted from this show's shipments: {{ number_format($whatnot['shipments'] ?? 0) }}</span>
                                    </label>
                                </div>
                            </section>

                            <section class="vxw-card">
                                <div class="vxw-card-head">
                                    <div>
                                        <h3 class="vxw-h2">Show notes <span class="vxw-meta font-normal">(optional)</span></h3>
                                        <p class="vxw-sub">Anything operations should know. Saves as you type.</p>
                                    </div>
                                </div>
                                <div class="vxw-card-body">
                                    <label class="sr-only" for="eos-notes">Show notes</label>
                                    <textarea id="eos-notes" rows="5" wire:model.live.debounce.700ms="logNotes" class="vxw-textarea" placeholder="Inventory issues, unusual giveaways, an item not in the catalog…"></textarea>
                                </div>
                            </section>
                        @endif

                        @if ($this->step === 3)
                            @php
                                $preview = $this->deductionPreview;
                            @endphp
                            <section class="vxw-card">
                                <div class="vxw-card-head">
                                    <div>
                                        <h3 class="vxw-h2">Review &amp; submit</h3>
                                        <p class="vxw-sub">Check the items logged against this show before sending it for approval.</p>
                                    </div>
                                    @if($reportSubmitted)<span class="vxw-pill vxw-pill--ok vxw-pill--dot">Submitted</span>@endif
                                </div>
                                <div class="vxw-stats" style="border-top:0">
                                    @foreach ([['Sold', $summary['sold']], ['Giveaway', $summary['giveaway']], ['Promo', $summary['promo']], ['Other', $summary['other']]] as [$label, $value])
                                        <div class="vxw-stat"><div class="vxw-stat-label">{{ $label }}</div><div class="vxw-stat-value">{{ number_format($value) }}</div></div>
                                    @endforeach
                                </div>
                                <div class="vxw-stats">
                                    <div class="vxw-stat"><div class="vxw-stat-label">Total units</div><div class="vxw-stat-value">{{ number_format($summary['units']) }}</div></div>
                                    <div class="vxw-stat"><div class="vxw-stat-label">Inventory cost</div><div class="vxw-stat-value">${{ number_format($summary['productCost'], 2) }}</div></div>
                                    <div class="vxw-stat"><div class="vxw-stat-label">Giveaway cost</div><div class="vxw-stat-value">${{ number_format($summary['giveawayCost'], 2) }}</div></div>
                                </div>
                                <div class="vxw-card-body grid gap-4" style="border-top:1px solid var(--vxw-border)">
                                    @if (! empty($preview))
                                        <div class="vxw-alert vxw-alert--warn">
                                            <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                                            <div>
                                                <div class="font-semibold">Inventory exceptions</div>
                                                <ul class="vxw-issues mt-1.5">@foreach ($preview as $problem)<li>{{ $problem }}</li>@endforeach</ul>
                                                <div class="vxw-hint mt-2">You can still submit — an admin will reconcile these.</div>
                                            </div>
                                        </div>
                                    @else
                                        <div class="vxw-alert vxw-alert--ok">
                                            <x-filament::icon icon="heroicon-o-check-circle" />
                                            <div>All matched items have enough stock.</div>
                                        </div>
                                    @endif

                                    {{-- Asked here, at the end, because this is the last
                                         moment the person who ran the show is still
                                         thinking about it — and hours before anybody
                                         opens the shipment list. --}}
                                    <div class="grid gap-3 rounded-[var(--vxw-radius-sm)] border border-[var(--vxw-border)] p-3">
                                        <label class="vxw-check">
                                            <input type="checkbox" wire:model.live="isSlowPack">
                                            <span>
                                                <span class="vxw-label block">Flag this show as slow to pack</span>
                                                <span class="vxw-hint block">Big boxes, awkward shapes, or lots of small orders. Fulfillment sees this on the shipment list.</span>
                                            </span>
                                        </label>
                                        <label class="sr-only" for="eos-fulfillment-notes">Notes for fulfillment</label>
                                        <textarea id="eos-fulfillment-notes" wire:model.blur="fulfillmentNotes" rows="2" class="vxw-textarea" style="min-height:64px" placeholder="Anything fulfillment should know before they start — optional"></textarea>
                                    </div>
                                </div>
                            </section>
                        @endif

                        {{-- Desktop step navigation --}}
                        <div class="vxw-desktop-only flex items-center justify-between gap-3">
                            @if ($this->step > 1)
                                <button type="button" class="vxw-btn" wire:click="goToStep({{ $this->step - 1 }})"><x-filament::icon icon="heroicon-m-arrow-left" /> Back</button>
                            @else
                                <span></span>
                            @endif
                            @if ($this->step < 3)
                                <button type="button" class="vxw-btn vxw-btn--primary" wire:click="goToStep({{ $this->step + 1 }})" wire:loading.attr="disabled" wire:target="goToStep">
                                    {{ $this->step === 1 ? 'Continue to time & notes' : 'Review report' }} <x-filament::icon icon="heroicon-m-arrow-right" />
                                </button>
                            @elseif($reportSubmitted)
                                <button type="button" class="vxw-btn vxw-btn--primary" disabled>Report submitted</button>
                            @else
                                <button type="button" class="vxw-btn vxw-btn--primary" wire:click="submit" wire:confirm="Submit this show report?" wire:loading.attr="disabled" wire:target="submit">
                                    <span wire:loading wire:target="submit" class="vxw-spin" aria-hidden="true"></span>
                                    Submit show report
                                </button>
                            @endif
                        </div>
                    </main>

                    <aside class="vxw-aside">
                        <section class="vxw-card">
                            <div class="vxw-card-head"><h3 class="vxw-h2">Report summary</h3></div>
                            <div class="vxw-card-body" style="padding-block:6px 12px">
                                <dl class="vxw-dl">
                                    <div><dt>Sold</dt><dd>{{ $summary['sold'] }}</dd></div>
                                    <div><dt>Giveaways</dt><dd>{{ $summary['giveaway'] }}</dd></div>
                                    <div><dt>Promo</dt><dd>{{ $summary['promo'] }}</dd></div>
                                    <div><dt>Other</dt><dd>{{ $summary['other'] }}</dd></div>
                                    <div class="is-total"><dt>Total units</dt><dd>{{ $summary['units'] }}</dd></div>
                                </dl>
                            </div>
                            <div class="vxw-card-foot" style="justify-content:flex-start">
                                @if($summary['items'] === 0)
                                    <span class="vxw-meta">Add show items to begin.</span>
                                @elseif($summary['unmatched'] > 0)
                                    <span class="vxw-meta vxw-tone-warn">{{ $summary['unmatched'] }} unlisted {{ \Illuminate\Support\Str::plural('item', $summary['unmatched']) }} will need admin matching.</span>
                                @else
                                    <span class="vxw-meta vxw-tone-ok">Everything is ready.</span>
                                @endif
                            </div>
                        </section>
                    </aside>
                </div>

                {{-- Phone: primary action always within thumb reach --}}
                <div class="vxw-actionbar" data-vx-mobile-actions>
                    @if($this->step === 1)
                        <button type="button" class="vxw-btn" wire:click="toggleBrowse">Add items</button>
                        <button type="button" class="vxw-btn vxw-btn--primary" wire:click="goToStep(2)">Continue</button>
                    @elseif($this->step === 2)
                        <button type="button" class="vxw-btn" wire:click="goToStep(1)">Back</button>
                        <button type="button" class="vxw-btn vxw-btn--primary" wire:click="goToStep(3)">Review</button>
                    @else
                        <button type="button" class="vxw-btn" wire:click="goToStep(2)">Back</button>
                        @if($reportSubmitted)
                            <button type="button" class="vxw-btn vxw-btn--primary" disabled>Submitted</button>
                        @else
                            <button type="button" class="vxw-btn vxw-btn--primary" wire:click="submit" wire:confirm="Submit this show report?" wire:loading.attr="disabled" wire:target="submit">Submit report</button>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        @if($this->showInventoryPicker && ! $reportBlocked)
            @php
                $total = $this->inventoryTotal;
                $staged = $this->stagedSummary;
                $alreadyInReport = $lines->groupBy('inventory_item_id')->map(fn ($rows) => $rows->sum('quantity'));
            @endphp
            <div class="vxw-sheet-wrap" role="dialog" aria-modal="true" aria-labelledby="eos-picker-title"
                x-data x-init="$nextTick(() => $el.querySelector('input[type=search]')?.focus())" @keydown.escape.window="$wire.toggleBrowse()">
                <div class="vxw-sheet-backdrop" wire:click="toggleBrowse"></div>
                <section class="vxw-sheet">
                    <div class="vxw-sheet-grab" aria-hidden="true"></div>
                    <div class="vxw-sheet-head">
                        <div>
                            <h3 id="eos-picker-title" class="vxw-h2">Add items</h3>
                            <p class="vxw-hint">Search or scan, then set how many were used.</p>
                        </div>
                        <button type="button" class="vxw-iconbtn" wire:click="toggleBrowse" aria-label="Close"><x-filament::icon icon="heroicon-m-x-mark" /></button>
                    </div>
                    <div class="vxw-sheet-tools">
                        <div class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto_200px_auto] sm:items-center">
                            {{-- Barcode and UPC are searched too, so a scanner
                                 aimed at this box works without the camera. --}}
                            <div class="vxw-search">
                                <x-filament::icon icon="heroicon-m-magnifying-glass" />
                                <input type="search" wire:model.live.debounce.250ms="search" placeholder="Search name, SKU or barcode…" class="vxw-input" aria-label="Search inventory" />
                            </div>
                            <button type="button" class="vxw-btn"
                                x-on:click="window.dispatchEvent(new CustomEvent('open-camera-scanner', { detail: { title: 'Scan an item', helper: 'Adds it to this report' } }))">
                                <x-filament::icon icon="heroicon-m-camera" /> Scan
                            </button>
                            <select wire:model.live="pickerCategory" class="vxw-select" aria-label="Category">
                                <option value="">All categories</option>
                                @foreach($this->pickerCategories as $category)<option value="{{ $category }}">{{ $category }}</option>@endforeach
                            </select>
                            <label class="vxw-check items-center rounded-[var(--vxw-radius-sm)] border border-[var(--vxw-border-strong)] bg-[var(--vxw-surface)] px-3" style="min-height:40px">
                                <input type="checkbox" wire:model.live="pickerStagedOnly" />
                                <span class="whitespace-nowrap text-[13px] font-medium">Selected only</span>
                            </label>
                        </div>
                        {{--
                            Say how many matched, not just how many are drawn.
                            A silent cut reads as "we do not stock that" when it
                            means "narrow your search", and the two look
                            identical on screen.
                        --}}
                        <p class="vxw-meta" aria-live="polite">
                            <span wire:loading wire:target="search,pickerCategory,pickerStagedOnly" class="vxw-spin mr-1 inline-block align-[-2px]" aria-hidden="true"></span>
                            @if($total === 0)
                                Nothing matches.
                            @elseif($total > $this->inventory->count())
                                Showing {{ number_format($this->inventory->count()) }} of {{ number_format($total) }} — keep typing to narrow it down.
                            @else
                                {{ number_format($total) }} {{ \Illuminate\Support\Str::plural('item', $total) }}.
                            @endif
                        </p>
                    </div>

                    <div class="vxw-sheet-body">
                        <div class="vxw-tiles" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
                            @forelse($this->inventory as $item)
                                @php $stagedQty = (int) ($this->stagedQuantities[$item->id] ?? 0); @endphp
                                <article wire:key="pick-item-{{ $item->id }}" @class(['vxw-tile', 'is-selected' => $stagedQty > 0]) style="cursor:default;padding:12px">
                                    <div>
                                        <div class="vxw-tile-title" style="font-size:13.5px">{{ $item->name }}</div>
                                        <div class="vxw-meta mt-0.5">
                                            SKU {{ $item->sku ?? '—' }}
                                            @if(($alreadyInReport[$item->id] ?? 0) > 0)
                                                · <span class="vxw-tone-ok font-medium">{{ $alreadyInReport[$item->id] }} on report</span>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="vxw-tile-foot">
                                        <div>
                                            <div class="vxw-fig-label">On hand</div>
                                            <div class="vxw-fig-value">{{ number_format((float)($item->stock_sum_quantity ?? 0)) }}</div>
                                        </div>
                                        @if($stagedQty === 0)
                                            <button type="button" class="vxw-btn vxw-btn--sm" wire:click="stageItem({{ $item->id }})">
                                                <x-filament::icon icon="heroicon-m-plus" /> Add
                                            </button>
                                        @else
                                            <div class="vxw-qty">
                                                <button type="button" wire:click="stageItem({{ $item->id }}, -1)" aria-label="One fewer {{ $item->name }}">−</button>
                                                <input type="number" min="0" inputmode="numeric" value="{{ $stagedQty }}" wire:change="setStagedQuantity({{ $item->id }}, $event.target.value)" aria-label="Quantity of {{ $item->name }}" />
                                                <button type="button" wire:click="stageItem({{ $item->id }}, 1)" aria-label="One more {{ $item->name }}">+</button>
                                            </div>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <div class="vxw-empty col-span-full">
                                    <x-filament::icon icon="heroicon-o-magnifying-glass" />
                                    <div class="vxw-empty-text">{{ $this->pickerStagedOnly ? 'Nothing selected yet.' : 'No inventory matched this search.' }}</div>
                                </div>
                            @endforelse
                        </div>

                        @if($total > $this->inventory->count())
                            <div class="mt-3 text-center">
                                <button type="button" class="vxw-btn" wire:click="showMoreInventory" wire:loading.attr="disabled" wire:target="showMoreInventory">Show 60 more</button>
                            </div>
                        @endif
                    </div>

                    {{-- Running total, always visible while scrolling a long catalog. --}}
                    <div class="vxw-sheet-foot">
                        <div class="vxw-meta">
                            @if($staged['items'] === 0)
                                Nothing selected yet.
                            @else
                                <strong class="text-[var(--vxw-text)]">{{ $staged['items'] }}</strong> {{ \Illuminate\Support\Str::plural('item', $staged['items']) }} ·
                                <strong class="text-[var(--vxw-text)]">{{ number_format($staged['units']) }}</strong> {{ \Illuminate\Support\Str::plural('unit', $staged['units']) }}
                                @if($staged['cost'] > 0) · <strong class="text-[var(--vxw-text)]">${{ number_format($staged['cost'], 2) }}</strong> at cost @endif
                            @endif
                        </div>
                        <div class="flex items-center gap-2 max-sm:w-full">
                            @if($staged['items'] > 0)
                                <button type="button" class="vxw-btn vxw-btn--ghost" wire:click="clearStaged">Clear</button>
                            @endif
                            <button type="button" class="vxw-btn vxw-btn--primary max-sm:flex-1" wire:click="addStagedItems" wire:loading.attr="disabled" wire:target="addStagedItems" @disabled($staged['items'] === 0)>
                                <span wire:loading wire:target="addStagedItems" class="vxw-spin" aria-hidden="true"></span>
                                @if($staged['items'] === 0)
                                    Add to report
                                @else
                                    Add {{ number_format($staged['units']) }} {{ \Illuminate\Support\Str::plural('unit', $staged['units']) }} to report
                                @endif
                            </button>
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
