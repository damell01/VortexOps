<x-filament-panels::page>
@php
    $run = $this->run;
    $tz = \App\Support\BusinessTime::zone();
    $show = $run?->show;
    $current = $this->current;
    $buyers = $this->buyers;
    $packedBuyers = $buyers->where('status', 'packed')->count();
    $search = mb_strtolower(trim($buyerSearch));
    $visibleBuyers = $search === '' ? $buyers : $buyers->filter(fn ($b) => str_contains(mb_strtolower($b->buyer_username), $search) || mb_strtolower($b->letter) === $search);
    // A page of buyers at a time; by default the page holding the current buyer.
    $perPage = \App\Filament\Pages\FulfillmentStationBoard::BUYERS_PER_PAGE;
    $buyerPages = $search === '' ? max(1, (int) ceil($buyers->count() / $perPage)) : 1;
    $currentIndex = $current ? $buyers->values()->search(fn ($b) => $b->id === $current->id) : false;
    $page = $search !== '' ? 1 : max(1, min($buyerPages, $buyerPage ?? ($currentIndex === false ? 1 : intdiv($currentIndex, $perPage) + 1)));
    $pageBuyers = $search === '' ? $buyers->values()->forPage($page, $perPage) : $visibleBuyers;
    $expected = $run?->expected_packages;
    $done = (int) ($run?->completed_packages ?? 0);
    $pct = $expected ? min(100, round($done / max(1, $expected) * 100)) : ($buyers->count() ? round($packedBuyers / $buyers->count() * 100) : 0);
@endphp
@include('fulfillment.dev-banner')
<div class="vxf" x-data="{
        theme: (() => { try { return localStorage.getItem('vxf-board-theme') || 'auto' } catch (e) { return 'auto' } })(),
        effective() { return this.theme === 'auto' ? (document.documentElement.classList.contains('dark') ? 'dark' : 'light') : this.theme },
        set(t) { this.theme = t; try { localStorage.setItem('vxf-board-theme', t) } catch (e) {} this.apply() },
        apply() { const r = document.documentElement; if (this.theme === 'auto') delete r.dataset.vxfBoard; else r.dataset.vxfBoard = this.theme },
        help: false,
        firstHelp() { try { if (! localStorage.getItem('vxf-help-board')) { this.help = true; localStorage.setItem('vxf-help-board', '1') } } catch (e) {} },
    }" x-init="apply(); @if ($run && $run->isOnStation()) firstHelp() @endif">
@if (! $run)
    {{-- Choose a station or a show --}}
    <div class="vxf-head">
        <div><h1>Station Board</h1><p>Choose your station, then open the show you are packing.</p></div>
        <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}"><x-heroicon-o-arrow-left /> Fulfillment Home</a>
    </div>
    <section class="vxf-card" aria-labelledby="vxf-pick-station">
        <div class="vxf-card__head"><h2 id="vxf-pick-station">Stations</h2></div>
        <div class="vxf-card__body vxf-stations">
            @foreach ($this->stations as $station)
                <button type="button" wire:click="chooseStation({{ $station->id }})" class="vxf-card vxf-station" style="text-align:left;cursor:pointer;{{ $stationId === $station->id ? 'border-color:#7c3aed;box-shadow:0 0 0 2px #7c3aed inset' : '' }}" aria-pressed="{{ $stationId === $station->id ? 'true' : 'false' }}">
                    <span class="vxf-station__top"><span class="vxf-station__name">{{ $station->name }}</span>
                        <span class="vxf-pill vxf-pill--{{ $station->activeRun ? 'packing' : '' }}">{{ $station->activeRun ? 'Packing' : 'Free' }}</span></span>
                    <span class="vxf-station__meta">{{ $station->activeRun?->show?->title ?? 'No show running' }}</span>
                </button>
            @endforeach
        </div>
    </section>
    <section class="vxf-card" aria-labelledby="vxf-pick-show">
        <div class="vxf-card__head"><h2 id="vxf-pick-show">Shows ready to start</h2></div>
        @forelse ($this->startable as $ready)
            @if ($loop->first)<ul class="vxf-list">@endif
            <li>
                <span class="vxf-dot" aria-hidden="true"><x-heroicon-o-inbox-stack /></span>
                <div style="flex:1;min-width:0"><strong>{{ $ready->show->title }}</strong>
                    <span class="vxf-sub">{{ $ready->show->channel?->name }} · {{ $ready->show->show_date?->format('M j') }} · {{ $ready->expected_packages !== null ? $ready->expected_packages . ' packages' : 'package count pending' }}</span></div>
                <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--primary" wire:click="openRun({{ $ready->id }})">Open</button>
            </li>
            @if ($loop->last)</ul>@endif
        @empty
            <p class="vxf-empty">No shows are waiting for you. Assigned shows appear here when they are ready.</p>
        @endforelse
    </section>

@elseif ($run->status === 'ready')
    {{-- Before packing: confirm the station and start --}}
    @php $needsReport = \App\Services\FulfillmentQueue::requiresApprovedReport() && ! \App\Services\FulfillmentQueue::reportApproved($show); @endphp
    <div class="vxf-board" wire:key="board-ready-{{ $run->id }}">
        <div class="vxf-sb__show">
            <div>
                <p class="vxf-eyebrow">Ready to pack</p>
                <h1>{{ $show->title }}</h1>
                <div class="vxf-sb__facts">
                    <span>Stream: <strong>{{ $show->show_date?->format('M j, Y') }}{{ $show->start_time ? ' · ' . $show->start_time->timezone($tz)->format('g:i A') : '' }}</strong></span>
                    <span>Channel: <strong>{{ $show->channel?->name ?? '—' }}</strong></span>
                    <span>Streamer: <strong>{{ $show->streamers->pluck('name')->join(', ') ?: '—' }}</strong></span>
                    <span>Packers: <strong>{{ $show->fulfillmentUsers->pluck('name')->join(', ') ?: 'Not assigned' }}</strong></span>
                </div>
            </div>
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}">Back</a>
        </div>
        <div class="vxf-card" style="margin-top:14px;padding:18px;display:grid;gap:14px;max-width:720px">
            <div class="vxf-kv">
                <div><dt>Buyers</dt><dd>{!! $run->buyer_count ?? '<span class="vxf-unknown">pending</span>' !!}</dd></div>
                <div><dt>Orders</dt><dd>{!! $run->order_count ?? '<span class="vxf-unknown">pending</span>' !!}</dd></div>
                <div><dt>Packages</dt><dd>{!! $run->expected_packages ?? '<span class="vxf-unknown">pending</span>' !!}</dd></div>
            </div>
            @if ($needsReport)
                <p class="vxf-alert" role="alert">The streamer report for this show has to be approved before packing can start.</p>
            @endif
            <label class="vxf-field">Your station
                <select class="vxf-select" wire:model="stationId">
                    <option value="">Choose a station…</option>
                    @foreach ($this->stations as $station)
                        <option value="{{ $station->id }}" @disabled($station->activeRun)>{{ $station->name }}{{ $station->activeRun ? ' (busy)' : '' }}</option>
                    @endforeach
                </select>
            </label>
            @error('stationId')<p class="vxf-flash vxf-flash--bad" role="alert">{{ $message }}</p>@enderror
            <button type="button" class="vxf-big vxf-big--go" wire:click="startPacking" wire:loading.attr="disabled" @disabled($needsReport)>
                <x-heroicon-o-play /> Start Packing
            </button>
            <p class="vxf-sub" style="margin:0">Starting records the station, packer and time, and loads the buyers A–Z with their letters.</p>
        </div>
        @if ($flash)<p class="vxf-flash vxf-flash--{{ $flash['ok'] ? 'ok' : 'bad' }}" role="status" style="margin-top:12px">{{ $flash['text'] }}</p>@endif
    </div>

@elseif ($run->isOnStation())
    {{-- Packing --}}
    @php $canWork = $this->canWork(); $paused = $run->status === 'paused'; @endphp
    <div class="vxf-board" wire:key="board-packing-{{ $run->id }}" x-data="{ focusScan() { this.$refs.scan?.focus() } }" x-init="$nextTick(() => focusScan())" @vxf-scan-done.window="$nextTick(() => focusScan())">
        <header class="vxf-sbar">
            <span class="vxf-sbar__station {{ $paused ? 'is-paused' : '' }}"><i aria-hidden="true"></i>{{ $run->station?->name }} · {{ $paused ? 'Paused' : 'Packing' }}</span>
            <div class="vxf-sbar__show">
                <h1 title="{{ $show->title }}">{{ $show->title }}</h1>
                <p>{{ $show->show_date?->format('M j') }}{{ $show->start_time ? ' · ' . $show->start_time->timezone($tz)->format('g:i A') : '' }}
                    · {{ $show->channel?->name ?? 'No channel' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}
                    · <strong>{{ $run->packers->pluck('name')->join(', ') }}</strong> since {{ $run->started_at?->timezone($tz)->format('g:i A') }}</p>
            </div>
            <div class="vxf-sbar__progress" role="progressbar" aria-valuenow="{{ $pct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Show progress">
                <strong class="vxf-num">@if ($expected !== null){{ $done }}<span>/{{ $expected }}</span>@else{{ $packedBuyers }}<span>/{{ $buyers->count() }}</span>@endif</strong>
                <small>{{ $expected !== null ? 'packages' : 'buyers' }} · {{ $pct }}%</small>
                <div class="vxf-bar"><span style="width: {{ $pct }}%"></span></div>
            </div>
            <div class="vxf-sbar__tools">
                <button type="button" class="vxf-iconbtn" @click="help = true" aria-label="How to pack" title="How to pack"><x-heroicon-o-question-mark-circle /></button>
                <button type="button" class="vxf-iconbtn" @click="set(effective() === 'dark' ? 'light' : 'dark')" :aria-label="effective() === 'dark' ? 'Switch to light' : 'Switch to dark'" :title="effective() === 'dark' ? 'Light board' : 'Dark board'">
                    <span x-show="effective() !== 'dark'"><x-heroicon-o-moon /></span><span x-show="effective() === 'dark'" x-cloak><x-heroicon-o-sun /></span>
                </button>
                <a class="vxf-iconbtn" href="{{ route('fulfillment.whiteboard', ['station' => $run->station_occupied_id]) }}" target="_blank" rel="noopener" aria-label="Open this station on the TV" title="TV view"><x-heroicon-o-tv /></a>
            </div>
        </header>

        @if ($flash)
            <p class="vxf-toast vxf-flash vxf-flash--{{ $flash['ok'] ? 'ok' : 'bad' }}" role="status" aria-live="polite" wire:key="flash-{{ $flash['at'] }}"
               x-data="{ on: true }" x-init="setTimeout(() => on = false, {{ $flash['ok'] ? 2600 : 6000 }})" x-show="on" x-transition.opacity.duration.300ms>
                @if ($flash['ok'])<x-heroicon-o-check-circle />@else<x-heroicon-o-exclamation-triangle />@endif {{ $flash['text'] }}</p>
        @endif

        <div class="vxf-sb">
            {{-- Buyers A–Z --}}
            <section class="vxf-card" aria-labelledby="vxf-buyers" style="min-width:0">
                <div class="vxf-card__head"><h2 id="vxf-buyers" style="color:var(--vxf-ink)">Buyers A–Z</h2><span class="vxf-sub vxf-num">{{ $packedBuyers }}/{{ $buyers->count() }}</span></div>
                <div style="padding:8px 8px 0"><label class="sr-only" for="vxf-buyer-search">Search buyer</label>
                    <input id="vxf-buyer-search" class="vxf-input" type="search" placeholder="Search buyer or letter…" wire:model.live.debounce.250ms="buyerSearch"></div>
                <div class="vxf-buyers" role="list">
                    @forelse ($pageBuyers as $b)
                        <div role="listitem">
                            <button type="button" wire:key="b-{{ $b->id }}" wire:click="selectBuyer({{ $b->id }})"
                                class="vxf-buyer {{ $b->status === 'packed' ? 'vxf-buyer--packed' : '' }} {{ $b->status === 'held' ? 'vxf-buyer--held' : '' }}"
                                aria-current="{{ $current?->id === $b->id ? 'true' : 'false' }}" @disabled($paused)>
                                <span class="vxf-letter">{{ $b->letter }}</span>
                                <span style="flex:1;min-width:0">
                                    <span class="vxf-buyer__name">{{ $b->buyer_username }}</span>
                                    <span class="vxf-sub">{{ $b->packages_count ? $b->packed_packages_count . '/' . $b->packages_count . ' pkg' : $b->item_count . ' ' . \Illuminate\Support\Str::plural('item', (int) $b->item_count) }} · {{ ['packed' => 'Packed', 'held' => 'On hold', 'packing' => 'Packing'][$b->status] ?? 'Waiting' }}</span>
                                </span>
                                @if ($b->status === 'packed')<x-heroicon-o-check-circle style="width:20px;height:20px;color:#16a34a" aria-label="Packed" />@endif
                                @if ($b->status === 'held')<x-heroicon-o-exclamation-triangle style="width:20px;height:20px;color:#d97706" aria-label="On hold" />@endif
                            </button>
                        </div>
                    @empty
                        <p class="vxf-empty">No buyer matches.</p>
                    @endforelse
                </div>
                @if ($buyerPages > 1)
                    @php $first = $buyers->values()->forPage($page, $perPage)->first(); $last = $buyers->values()->forPage($page, $perPage)->last(); @endphp
                    <nav class="vxf-pager" aria-label="Buyer pages">
                        <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--icon" wire:click="setBuyerPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Previous buyers"><x-heroicon-o-chevron-left /></button>
                        <span class="vxf-num" style="font-weight:700;font-size:13px">{{ $first?->letter }}–{{ $last?->letter }}</span>
                        <button type="button" class="vxf-btn vxf-btn--sm vxf-btn--icon" wire:click="setBuyerPage({{ $page + 1 }})" @disabled($page >= $buyerPages) aria-label="Next buyers"><x-heroicon-o-chevron-right /></button>
                        <span class="vxf-sub vxf-num">Page {{ $page }} of {{ $buyerPages }}</span>
                    </nav>
                @endif
            </section>

            {{-- Current buyer --}}
            <section aria-labelledby="vxf-current" style="display:flex;flex-direction:column;gap:12px;min-width:0">
                @if ($current)
                    <div class="vxf-current">
                        <div class="vxf-bigletter {{ strlen($current->letter) > 1 ? 'vxf-bigletter--sm' : '' }}" aria-label="Buyer letter {{ $current->letter }}">{{ $current->letter }}</div>
                        <div style="flex:1;min-width:200px">
                            <p class="vxf-eyebrow">Current buyer{{ $current->status === 'held' ? ' · on hold' : '' }}</p>
                            <h2 id="vxf-current">{{ $current->buyer_username }}</h2>
                            <div class="vxf-chips">
                                <span class="vxf-chip">{{ $current->order_count }} {{ \Illuminate\Support\Str::plural('order', $current->order_count) }}</span>
                                <span class="vxf-chip">{{ $current->packages_count ?: '—' }} {{ \Illuminate\Support\Str::plural('package', $current->packages_count ?: 2) }}</span>
                                <span class="vxf-chip">{{ $current->item_count }} {{ \Illuminate\Support\Str::plural('item', (int) $current->item_count) }}</span>
                                @if ($current->packing_started_at)<span class="vxf-chip">Started {{ $current->packing_started_at->timezone($tz)->format('g:i:s A') }}</span>@endif
                            </div>
                        </div>
                    </div>

                    <div class="vxf-card" style="padding:12px 14px">
                        <form wire:submit="scan" class="vxf-scan" aria-label="Scan an item to verify">
                            <x-heroicon-o-qr-code aria-hidden="true" />
                            <label for="vxf-scan-input" class="sr-only">Scan item barcode</label>
                            <input id="vxf-scan-input" x-ref="scan" type="text" autocomplete="off" placeholder="Scan an item to verify…" wire:model="scanCode" @disabled($paused || ! $canWork || $current->status === 'packed')>
                            <button type="submit" class="vxf-btn vxf-btn--sm vxf-btn--primary" @disabled($paused || ! $canWork)>Verify</button>
                        </form>
                        @php $cov = $this->coverage; @endphp
                        @if ($cov && $cov['supported'])
                            <ul class="vxf-items" style="margin-top:8px">
                                @foreach ($cov['lines'] as $line)
                                    <li>
                                        <span class="vxf-check {{ $line['verified'] >= $line['expected'] ? 'vxf-check--ok' : '' }}" aria-hidden="true">@if ($line['verified'] >= $line['expected'])<x-heroicon-o-check />@endif</span>
                                        <span style="flex:1;min-width:0;font-weight:600">{{ $line['name'] }}</span>
                                        <span class="vxf-num" style="font-weight:800">{{ $line['verified'] }}/{{ $line['expected'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                            @if (! $cov['complete'] && $this->isManager())
                                <div style="display:flex;gap:8px;margin-top:8px">
                                    <label class="sr-only" for="vxf-exception">Exception note</label>
                                    <input id="vxf-exception" class="vxf-input" placeholder="Manager exception note (missing barcode…)" wire:model="exceptionNote">
                                    <button type="button" class="vxf-btn vxf-btn--sm" wire:click="approveException">Approve exception</button>
                                </div>
                            @endif
                        @else
                            <p class="vxf-sub" style="margin:8px 0 0">Item verification is not available for this buyer: their orders are not mapped to inventory items. Check the items against the packing slip.</p>
                        @endif
                    </div>

                    @if ($this->currentPackages->isNotEmpty())
                        <div class="vxf-card" style="padding:4px 14px">
                            <ul class="vxf-items">
                                @foreach ($this->currentPackages as $package)
                                    <li wire:key="p-{{ $package->id }}">
                                        <span class="vxf-check {{ $package->status === 'packed' ? 'vxf-check--ok' : '' }}" aria-hidden="true">@if ($package->status === 'packed')<x-heroicon-o-check />@endif</span>
                                        <span style="flex:1;min-width:0"><strong>Package {{ $loop->iteration }}</strong>
                                            <span class="vxf-sub">{{ $package->order_ids ? 'Order ' . implode(', ', (array) $package->order_ids) : '' }}{{ $package->shipment?->item_count ? ($package->order_ids ? ' · ' : '') . $package->shipment->item_count . ' ' . \Illuminate\Support\Str::plural('item', (int) $package->shipment->item_count) : '' }}</span></span>
                                        @if ($package->status !== 'packed' && $canWork && ! $paused)
                                            <button type="button" class="vxf-btn vxf-btn--sm" wire:click="packPackage({{ $package->id }})" aria-label="Mark package {{ $loop->iteration }} packed"><x-heroicon-o-check /> Pack</button>
                                        @elseif ($package->status === 'packed')
                                            <span class="vxf-pill vxf-pill--packing">Packed{{ $package->packed_at ? ' ' . $package->packed_at->timezone($tz)->format('g:i A') : '' }}</span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @else
                    <p class="vxf-empty">No buyers loaded.</p>
                @endif
            </section>

            {{-- Actions --}}
            <section class="vxf-big-actions" aria-label="Packing actions">
                @if ($canWork)
                    @if ($paused)
                        <button type="button" class="vxf-big vxf-big--go" wire:click="resume"><x-heroicon-o-play /> Resume Packing</button>
                    @else
                        <button type="button" class="vxf-big vxf-big--go" wire:click="packNext" wire:loading.attr="disabled" @disabled(! $current || $current->status === 'packed' || $current->status === 'held')>
                            @php $openPkgs = $this->currentPackages->where('status', '!=', 'packed')->count(); $allPkgs = $this->currentPackages->count(); @endphp
                            <x-heroicon-o-check-circle /> <span>Mark Packed @if ($openPkgs > 1)<small style="display:block;font-size:12px;font-weight:600;opacity:.85">package {{ $allPkgs - $openPkgs + 1 }} of {{ $allPkgs }}</small>@endif</span>
                        </button>
                        <div class="vxf-pair">
                            <button type="button" class="vxf-big" wire:click="step(-1)" aria-label="Previous buyer"><x-heroicon-o-arrow-left /> Prev</button>
                            <button type="button" class="vxf-big" wire:click="step(1)" aria-label="Next buyer">Next <x-heroicon-o-arrow-right /></button>
                        </div>
                        <button type="button" class="vxf-big" x-on:click="focusScan()"><x-heroicon-o-qr-code /> Scan Item</button>
                        <button type="button" class="vxf-big vxf-big--warn" wire:click="openFlag"><x-heroicon-o-flag /> Flag Issue</button>
                        <button type="button" class="vxf-big" wire:click="pause"><x-heroicon-o-pause /> Pause Packing</button>
                    @endif
                    <button type="button" class="vxf-big vxf-big--brand vxf-big--end" wire:click="finish" wire:confirm="Finish packing this show? Every buyer must be packed or flagged.">
                        <x-heroicon-o-flag /> Finish Show
                    </button>
                    @if ($this->isManager() && $current && $current->status === 'packed')
                        <button type="button" class="vxf-btn vxf-btn--sm" wire:click="reopenBuyer({{ $current->id }})" wire:confirm="Reopen {{ $current->letter }} so it can be packed again?">Reopen buyer</button>
                    @endif
                @else
                    <p class="vxf-sub">You are viewing this station. Only the assigned packers can pack.</p>
                @endif
            </section>
        </div>

        <div class="vxf-help" x-show="help" x-cloak x-transition.opacity @keydown.escape.window="help = false" role="dialog" aria-modal="true" aria-labelledby="vxf-help-title">
            <div class="vxf-help__card" @click.outside="help = false">
                <h2 id="vxf-help-title">How to pack a show</h2>
                <ol>
                    <li><b>A</b><span><strong>Pack buyers in letter order.</strong> The big letter is the buyer you're on. It starts at A and the label matches the camera.</span></li>
                    <li><b><x-heroicon-o-qr-code /></b><span><strong>Scan each item.</strong> The scan box is always ready. If scanning isn't set up for a buyer, check against the packing slip.</span></li>
                    <li><b><x-heroicon-o-check /></b><span><strong>Press Mark Packed.</strong> It packs the next package, then moves on to the next buyer by itself.</span></li>
                    <li><b><x-heroicon-o-flag /></b><span><strong>Something wrong? Flag Issue.</strong> That buyer is put on hold and a manager is told. Keep packing the rest.</span></li>
                    <li><b><x-heroicon-o-pause /></b><span><strong>Taking a break? Pause.</strong> The timer stops until you resume.</span></li>
                    <li><b><x-heroicon-o-flag /></b><span><strong>All done? Finish Show.</strong> Then confirm the record so a manager can approve it.</span></li>
                </ol>
                <button type="button" class="vxf-btn vxf-btn--primary" @click="help = false">Got it</button>
            </div>
        </div>

        @if ($showFlag)
            <div class="vxf-drawer-backdrop" wire:click="$set('showFlag', false)"></div>
            <div class="vxf-drawer vxf-board" style="border-radius:0" role="dialog" aria-modal="true" aria-labelledby="vxf-flag-title" @keydown.escape.window="$wire.set('showFlag', false)">
                <div class="vxf-drawer__head"><h2 id="vxf-flag-title" style="color:var(--vxf-ink)">Flag an issue</h2>
                    <button type="button" class="vxf-icon-btn" style="color:var(--vxf-ink)" wire:click="$set('showFlag', false)" aria-label="Close"><x-heroicon-o-x-mark /></button></div>
                <div class="vxf-drawer__body">
                    @if ($current)<p style="margin:0"><span class="vxf-letter" style="display:inline-grid">{{ $current->letter }}</span> <strong>{{ $current->buyer_username }}</strong></p>@endif
                    <fieldset style="border:0;margin:0;padding:0"><legend class="vxf-field" style="margin-bottom:6px">What's wrong?</legend>
                        <div class="vxf-grid2">
                            @foreach (\App\Services\FulfillmentFlagService::CATEGORIES as $key => $label)
                                <label class="vxf-btn" style="justify-content:flex-start;{{ $flagType === $key ? 'border-color:#8b5cf6;background:color-mix(in srgb,#8b5cf6 22%,transparent)' : '' }}"><input type="radio" name="flagType" value="{{ $key }}" wire:model.live="flagType" style="accent-color:#8b5cf6"> {{ $label }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <label class="vxf-field">Order number (optional)<input class="vxf-input" wire:model="flagOrder"></label>
                    <label class="vxf-field">What happened<textarea class="vxf-textarea" rows="4" wire:model="flagDetails" placeholder="e.g. not on the shelf at Main Warehouse"></textarea></label>
                    @error('flagDetails')<p class="vxf-flash vxf-flash--bad" role="alert">{{ $message }}</p>@enderror
                    <p class="vxf-sub" style="margin:0">The buyer is put on hold and the fulfillment manager is notified. Keep packing the other buyers.</p>
                </div>
                <div class="vxf-drawer__foot">
                    <button type="button" class="vxf-btn" wire:click="$set('showFlag', false)">Cancel</button>
                    <button type="button" class="vxf-btn vxf-btn--primary" style="background:#ea580c;border-color:#ea580c" wire:click="submitFlag">Send flag</button>
                </div>
            </div>
        @endif
    </div>

@else
    {{-- Finished --}}
    <div class="vxf-head">
        <div><h1>{{ $show->title }}</h1><p>{{ \App\Models\FulfillmentRun::statusLabel($run->status) }} · {{ $run->station?->name }}</p></div>
        <div class="vxf-actions">
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['run' => $run->id]) }}">Open show log</a>
            <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}">Fulfillment Home</a>
        </div>
    </div>
    @if ($flash)<p class="vxf-flash vxf-flash--{{ $flash['ok'] ? 'ok' : 'bad' }}" role="status">{{ $flash['text'] }}</p>@endif
    <section class="vxf-card vxf-card__body" style="display:grid;gap:12px">
        <dl class="vxf-kv">
            <div><dt>Packing start</dt><dd>{{ $run->started_at?->timezone($tz)->format('M j · g:i A') ?? '—' }}</dd></div>
            <div><dt>Packing complete</dt><dd>{{ $run->finished_at?->timezone($tz)->format('M j · g:i A') ?? '—' }}</dd></div>
            <div><dt>Buyers</dt><dd>{{ $run->buyer_count ?? '—' }}</dd></div>
            <div><dt>Packages</dt><dd>{{ $run->completed_packages }}{{ $run->expected_packages !== null ? ' / ' . $run->expected_packages : '' }}</dd></div>
            <div><dt>Packers</dt><dd>{{ $run->packers->pluck('name')->join(', ') ?: '—' }}</dd></div>
            <div><dt>Approval</dt><dd>{{ $run->approved_at ? 'Approved by ' . $run->approver?->name : 'Waiting for manager' }}</dd></div>
        </dl>
        @if ($run->discrepancies)
            <div class="vxf-alert" role="note"><div><strong>Check before approving</strong><ul style="margin:4px 0 0;padding-left:18px">@foreach ($run->discrepancies as $d)<li>{{ $d }}</li>@endforeach</ul></div></div>
        @endif
        @if ($run->acknowledged_at)
            <p class="vxf-sub" style="margin:0">Acknowledged by {{ $run->acknowledger?->name }} · {{ $run->acknowledged_at->timezone($tz)->format('M j, g:i A') }}</p>
        @elseif ($run->packers->contains('id', auth()->id()))
            <button type="button" class="vxf-btn vxf-btn--primary" style="justify-self:start" wire:click="acknowledge">I confirm these counts are right</button>
        @endif
    </section>
@endif
</div>
</x-filament-panels::page>
