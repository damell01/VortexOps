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
@if ($run)
    @php
        $packingStage = in_array($run->status, [\App\Models\FulfillmentRun::COMPLETED, \App\Models\FulfillmentRun::APPROVED], true)
            ? 3 : (in_array($run->status, \App\Models\FulfillmentRun::ON_STATION, true) ? 2 : 1);
    @endphp
    <nav class="mb-3 rounded-xl border border-[var(--vx-divider)] p-3" aria-label="Fulfillment workflow">
        <div class="grid grid-cols-4 gap-2 text-center text-xs font-semibold">
            @foreach (['Ready to Pack', 'Packing', 'Packed', 'Shipped'] as $step => $label)
                <div class="rounded-lg px-2 py-2 {{ $packingStage >= $step + 1 ? 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400' }}">
                    {{ $step + 1 }}. {{ $label }}
                    @if ($step === 3)<span class="mt-1 block text-[10px] font-normal">See Shipments for carrier status</span>@endif
                </div>
            @endforeach
        </div>
        <p class="mt-2 text-xs text-[var(--vx-muted)]">
            {{ $run->completed_packages ?? 0 }} / {{ $run->expected_packages ?? '—' }} packages packed.
            Packing completion does not confirm carrier shipment or delivery.
        </p>
    </nav>
@endif
@if (! $run)
    {{-- Choose a station or a show --}}
    <div class="vxf-head">
        <div><h1>Station Board</h1><p>Pick your station, then open the show you're packing.</p></div>
        <a class="vxf-btn" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}"><x-heroicon-o-arrow-left /> Fulfillment Home</a>
    </div>
    <section aria-labelledby="vxf-pick-station">
        <h2 id="vxf-pick-station" class="vxf-step"><b>1</b> Your station</h2>
        <div class="vxf-tiles">
            @foreach ($this->stations as $station)
                @php $active = $station->activeRun; @endphp
                <button type="button" wire:click="chooseStation({{ $station->id }})" @class(['vxf-tile', 'vxf-tile--busy' => $active, 'is-selected' => $stationId === $station->id]) aria-pressed="{{ $stationId === $station->id ? 'true' : 'false' }}">
                    <span class="vxf-tile__top"><strong>{{ $station->name }}</strong>
                        <span class="vxf-pill vxf-pill--{{ $active ? 'packing' : 'ready' }}">{{ $active ? 'Packing' : 'Free' }}</span></span>
                    <span class="vxf-tile__sub">{{ $active?->show?->title ?? 'Ready for a show' }}</span>
                    @if ($stationId === $station->id)<span class="vxf-tile__check" aria-hidden="true"><x-heroicon-s-check-circle /></span>@endif
                </button>
            @endforeach
        </div>
    </section>
    <section aria-labelledby="vxf-pick-show">
        <h2 id="vxf-pick-show" class="vxf-step"><b>2</b> Open a show</h2>
        @forelse ($this->startable as $ready)
            @if ($loop->first)<div class="vxf-showcards">@endif
            <article class="vxf-showcard">
                <span class="vxf-showcard__icon" aria-hidden="true"><x-heroicon-o-inbox-stack /></span>
                <div style="flex:1;min-width:0">
                    <strong>{{ $ready->show->title }}</strong>
                    <span class="vxf-sub">{{ $ready->show->channel?->name ?? 'No channel' }} · {{ $ready->show->show_date?->format('M j') }}</span>
                    <span class="vxf-chips" style="margin-top:6px"><span class="vxf-chip">{{ $ready->expected_packages !== null ? $ready->expected_packages . ' packages' : 'count pending' }}</span>@if ($ready->show->is_slow_pack)<span class="vxf-chip">Long job</span>@endif</span>
                </div>
                <button type="button" class="vxf-btn vxf-btn--primary" wire:click="openRun({{ $ready->id }})">Open <x-heroicon-o-arrow-right /></button>
            </article>
            @if ($loop->last)</div>@endif
        @empty
            <p class="vxf-empty vxf-card">No shows are waiting for you. Assigned shows appear here when they're ready.</p>
        @endforelse
    </section>

@elseif ($run->status === 'ready')
    {{-- Before packing: confirm the station and start --}}
    @php $needsReport = \App\Services\FulfillmentQueue::requiresApprovedReport() && ! \App\Services\FulfillmentQueue::reportApproved($show); @endphp
    <div class="vxf-board vxf-ready" wire:key="board-ready-{{ $run->id }}">
        <section class="vxf-ready__show">
            <a class="vxf-backlink" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl() }}"><x-heroicon-o-arrow-left /> Fulfillment Home</a>
            <p class="vxf-eyebrow">Ready to pack</p>
            <h1 style="display:flex;align-items:center;gap:12px"><x-show-cover :show="$show" :size="48" /><span>{{ $show->title }}</span></h1>
            <div class="vxf-chips">
                <span class="vxf-chip"><x-heroicon-o-video-camera /> {{ $show->show_date?->format('M j') }}{{ $show->start_time ? ' · ' . $show->start_time->timezone($tz)->format('g:i A') : '' }}</span>
                <span class="vxf-chip"><x-heroicon-o-signal /> {{ $show->channel?->name ?? 'No channel' }}</span>
                <span class="vxf-chip"><x-heroicon-o-user /> {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}</span>
            </div>
            <div class="vxf-bignums">
                <div><x-heroicon-o-users /><b>{!! $run->buyer_count ?? '<span class="vxf-unknown">—</span>' !!}</b><span>buyers</span></div>
                <div><x-heroicon-o-receipt-percent /><b>{!! $run->order_count ?? '<span class="vxf-unknown">—</span>' !!}</b><span>orders</span></div>
                <div><x-heroicon-o-cube /><b>{!! $run->expected_packages ?? '<span class="vxf-unknown">—</span>' !!}</b><span>packages</span></div>
            </div>
            <div class="vxf-packers">
                <span class="vxf-sub">Packing this show</span>
                @forelse ($show->fulfillmentUsers as $u)
                    <span class="vxf-person"><i>{{ mb_strtoupper(mb_substr($u->name, 0, 1)) }}</i>{{ $u->name }}</span>
                @empty
                    <span class="vxf-person vxf-person--none">Not assigned yet</span>
                @endforelse
            </div>
            <ul class="vxf-whatnext">
                <li><x-heroicon-o-language /> Buyers load A–Z, each with a letter.</li>
                <li><x-heroicon-o-tv /> The TV whiteboard shows your progress live.</li>
            </ul>
        </section>
        <section class="vxf-ready__go">
            @if ($needsReport)
                <p class="vxf-alert" role="alert"><x-heroicon-o-lock-closed style="width:18px;height:18px;flex-shrink:0" /> The streamer report has to be approved before packing can start.</p>
            @endif
            <h2 class="vxf-step"><b>1</b> Choose your station</h2>
            <div class="vxf-tiles vxf-tiles--pick" role="radiogroup" aria-label="Station">
                @foreach ($this->stations as $station)
                    @php $busy = $station->activeRun; @endphp
                    <button type="button" role="radio" aria-checked="{{ (int) $stationId === $station->id ? 'true' : 'false' }}" wire:click="$set('stationId', {{ $station->id }})" @disabled($busy)
                        @class(['vxf-tile', 'vxf-tile--busy' => $busy, 'is-selected' => (int) $stationId === $station->id])>
                        <span class="vxf-tile__top"><strong>{{ $station->name }}</strong>@if ((int) $stationId === $station->id)<span class="vxf-tile__check" aria-hidden="true"><x-heroicon-s-check-circle /></span>@endif</span>
                        <span class="vxf-tile__sub">{{ $busy ? 'Busy · ' . $busy->show?->title : 'Free' }}</span>
                    </button>
                @endforeach
            </div>
            @error('stationId')<p class="vxf-flash vxf-flash--bad" role="alert">{{ $message }}</p>@enderror
            <h2 class="vxf-step"><b>2</b> Start</h2>
            <button type="button" class="vxf-big vxf-big--go vxf-big--hero" wire:click="startPacking" wire:loading.attr="disabled" @disabled($needsReport || ! $stationId)>
                <x-heroicon-o-play /> Start Packing
            </button>
            @if ($flash)<p class="vxf-flash vxf-flash--{{ $flash['ok'] ? 'ok' : 'bad' }}" role="status">{{ $flash['text'] }}</p>@endif
        </section>
    </div>

@elseif ($run->isOnStation())
    {{-- Packing --}}
    @php $canWork = $this->canWork(); $paused = $run->status === 'paused'; @endphp
    <div class="vxf-board" wire:key="board-packing-{{ $run->id }}" x-data="{ focusScan() { this.$refs.scan?.focus() },
            beep(ok) { try { const C = window.AudioContext || window.webkitAudioContext; if (!C) return; this.ac ??= new C(); const t = this.ac.currentTime; (ok ? [[880, 0]] : [[220, 0], [180, 0.18]]).forEach(([f, d]) => { const o = this.ac.createOscillator(), g = this.ac.createGain(); o.frequency.value = f; o.type = ok ? 'sine' : 'square'; g.gain.setValueAtTime(0.12, t + d); g.gain.exponentialRampToValueAtTime(0.001, t + d + 0.16); o.connect(g).connect(this.ac.destination); o.start(t + d); o.stop(t + d + 0.17); }); } catch (e) {} try { navigator.vibrate?.(ok ? 40 : [90, 60, 90]); } catch (e) {} } }"
        x-init="$nextTick(() => focusScan())" @vxf-scan-done.window="$nextTick(() => focusScan())" @vxf-scan-result.window="beep($event.detail.ok ?? $event.detail[0]?.ok)">
        <header class="vxf-sbar">
            <span class="vxf-sbar__station {{ $paused ? 'is-paused' : '' }}"><i aria-hidden="true"></i>{{ $run->station?->name }} · {{ $paused ? 'Paused · ' . \App\Models\FulfillmentRun::pauseReasonLabel($run->pause_reason) : 'Packing' }}</span>
            <x-show-cover :show="$show" :size="40" class="vxf-sbar__cover" />
            <div class="vxf-sbar__show">
                <h1 title="{{ $show->title }}">{{ $show->title }}</h1>
                <p>{{ $show->show_date?->format('M j') }}{{ $show->start_time ? ' · ' . $show->start_time->timezone($tz)->format('g:i A') : '' }}
                    · {{ $show->channel?->name ?? 'No channel' }} · {{ $show->streamers->pluck('name')->join(', ') ?: 'No streamer' }}
                    · <strong>{{ $run->packers->pluck('name')->join(', ') }}</strong> since {{ $run->started_at?->timezone($tz)->format('g:i A') }}</p>
            </div>
            <div class="vxf-sbar__progress">
                @include('fulfillment.ring', ['pct' => $pct, 'label' => ($expected !== null ? $done . '/' . $expected : $packedBuyers . '/' . $buyers->count()), 'tone' => $paused ? 'paused' : 'packing', 'size' => 58])
                <small>{{ $expected !== null ? 'packages' : 'buyers' }}<br>packed</small>
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
                                        @if (! empty($line['image']))<img src="{{ $line['image'] }}" alt="" loading="lazy" width="44" height="44" class="vxf-item-photo">@endif
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
                                        @php $label = $this->labels[$package->id] ?? null; @endphp
                                        @if ($label && $label['url'])
                                            <button type="button" class="vxf-btn vxf-btn--sm" x-on:click="$dispatch('vxf-label', {{ \Illuminate\Support\Js::from(['id' => $package->id, 'src' => route('fulfillment.label', $package->id), 'url' => $label['url'], 'title' => $current->letter . ' · ' . $current->buyer_username . ' · package ' . $loop->iteration]) }})" title="Show the label here and print it{{ $label['printed'] ? ' · printed ' . $label['printed'] . '× already' : '' }}"><x-heroicon-o-printer /> {{ $label['printed'] ? 'Reprint' : 'Print label' }}@if ($label['printed'])<span class="vxf-pill" style="margin-left:4px">{{ $label['printed'] }}×</span>@endif</button>
                                        @elseif ($package->shipment_id)
                                            <span class="vxf-btn vxf-btn--sm" aria-disabled="true" style="opacity:.55;cursor:not-allowed" title="This shipment's label has not synced from Whatnot yet. Refresh it on Shipping & Labels.">
                                                <x-heroicon-o-printer /> No label yet</span>
                                        @endif
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
                    @endif
                    <div class="vxf-acttiles">
                        @unless ($paused)
                            <button type="button" class="vxf-acttile vxf-acttile--bad" wire:click="openFlag"><x-heroicon-o-flag /><span>Flag Issue</span></button>
                            <div x-data="{ open: false }" class="vxf-pausepick" @keydown.escape.window="open = false" style="position:relative;display:contents">
                                <button type="button" class="vxf-acttile vxf-acttile--warn" @click="open = true" :aria-expanded="open"><x-heroicon-o-pause /><span>Pause</span></button>
                                <div x-show="open" x-cloak x-transition.opacity class="vxf-pausepick__backdrop" @click.self="open = false" role="dialog" aria-modal="true" aria-labelledby="vxf-pause-title">
                                    <div class="vxf-pausepick__card">
                                        <h2 id="vxf-pause-title">Why are you pausing?</h2>
                                        <div class="vxf-pausepick__grid">
                                            @foreach (\App\Models\FulfillmentRun::PAUSE_REASONS as $key => $label)
                                                <button type="button" class="vxf-big" wire:click="pause('{{ $key }}')" @click="open = false">{{ $label }}</button>
                                            @endforeach
                                        </div>
                                        <label class="vxf-sub" for="vxf-pause-note" style="display:block;margin-top:10px">Note (optional)</label>
                                        <input id="vxf-pause-note" type="text" class="vxf-input" wire:model="pauseReason" maxlength="200" placeholder="e.g. waiting on 2 more boxes" style="width:100%">
                                        <button type="button" class="vxf-btn vxf-btn--sm" style="margin-top:10px" @click="open = false">Cancel</button>
                                    </div>
                                </div>
                            </div>
                        @endunless
                        <button type="button" class="vxf-acttile vxf-acttile--brand" wire:click="openReview"><x-heroicon-o-check-badge /><span>Finish Show</span></button>
                    </div>
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
                    <li><b>A</b><span><strong>Pack buyers in letter order.</strong> The big letter is the buyer you're on. It starts at A.</span></li>
                    <li><b><x-heroicon-o-qr-code /></b><span><strong>Scan each item.</strong> The scan box is always ready. If scanning isn't set up for a buyer, check against the packing slip.</span></li>
                    <li><b><x-heroicon-o-check /></b><span><strong>Press Mark Packed.</strong> It packs the next package, then moves on to the next buyer by itself.</span></li>
                    <li><b><x-heroicon-o-flag /></b><span><strong>Something wrong? Flag Issue.</strong> That buyer is put on hold and a manager is told. Keep packing the rest.</span></li>
                    <li><b><x-heroicon-o-pause /></b><span><strong>Taking a break? Pause.</strong> The timer stops until you resume.</span></li>
                    <li><b><x-heroicon-o-check-badge /></b><span><strong>All done? Finish Show.</strong> Look over the review screen and confirm the show is done. The fulfillment admin then signs it off.</span></li>
                </ol>
                <button type="button" class="vxf-btn vxf-btn--primary" @click="help = false">Got it</button>
            </div>
        </div>

        {{-- In-app label print: the label loads from this site into the window; Print prints just the label. --}}
        <div x-data="{ open: false, l: null, loading: true, failed: false,
                show(d) { this.l = d; this.loading = true; this.failed = false; this.open = true },
                print() { const f = this.$refs.frame; try { f.contentWindow.focus(); f.contentWindow.print(); $wire.labelOpened(this.l.id) } catch (e) { window.open(this.l.url, '_blank', 'noopener'); $wire.labelOpened(this.l.id) } } }"
             x-on:vxf-label.window="show($event.detail)" x-on:keydown.escape.window="open = false">
            <div x-show="open" x-cloak class="vxf-label-backdrop" x-on:click.self="open = false" role="dialog" aria-modal="true" aria-labelledby="vxf-label-title">
                <div class="vxf-label-card">
                    <div class="vxf-label-head">
                        <h2 id="vxf-label-title"><x-heroicon-o-printer /> <span x-text="l?.title"></span></h2>
                        <button type="button" class="vxf-iconbtn" x-on:click="open = false" aria-label="Close"><x-heroicon-o-x-mark /></button>
                    </div>
                    <div class="vxf-label-frame">
                        <p x-show="loading && !failed" class="vxf-sub">Loading the label…</p>
                        <template x-if="open && l"><iframe x-ref="frame" :src="l.src" title="Shipping label" x-on:load="loading = false"></iframe></template>
                    </div>
                    <div class="vxf-label-foot">
                        <a class="vxf-btn" :href="l?.url" target="_blank" rel="noopener noreferrer" x-on:click="$wire.labelOpened(l.id)">Open in new tab</a>
                        <button type="button" class="vxf-btn vxf-btn--primary vxf-btn--lg" x-on:click="print()" :disabled="loading"><x-heroicon-o-printer /> Print</button>
                    </div>
                </div>
            </div>
        </div>

        @if ($reviewing && ($r = $this->review))
            @php $ready = $r['notPacked']->isEmpty() && ! $r['blockingIssues']; @endphp
            <div class="vxf-drawer-backdrop" wire:click="$set('reviewing', false)"></div>
            <div class="vxf-review" role="dialog" aria-modal="true" aria-labelledby="vxf-review-title" x-data="{ sure: false }" @keydown.escape.window="$wire.set('reviewing', false)">
                <div class="vxf-review__head">
                    <p class="vxf-eyebrow">Final check</p>
                    <h2 id="vxf-review-title">Is {{ $show->title }} done?</h2>
                    <p class="vxf-sub" style="margin:0">Look it over. Once you confirm, the show goes to the fulfillment admin for final sign-off.</p>
                </div>
                <div class="vxf-review__stats">
                    <div class="vxf-review__stat {{ $r['packedBuyers'] === $r['buyers'] ? 'is-ok' : 'is-bad' }}"><b>{{ $r['packedBuyers'] }}<small>/{{ $r['buyers'] }}</small></b><span>buyers packed</span></div>
                    @if ($r['packages'])
                        <div class="vxf-review__stat {{ $r['packedPackages'] === $r['packages'] ? 'is-ok' : 'is-bad' }}"><b>{{ $r['packedPackages'] }}<small>/{{ $r['packages'] }}</small></b><span>packages packed</span></div>
                    @endif
                    <div class="vxf-review__stat {{ $r['openIssues'] ? 'is-warn' : 'is-ok' }}"><b>{{ $r['openIssues'] }}</b><span>open {{ \Illuminate\Support\Str::plural('issue', $r['openIssues']) }}{{ $r['issues'] > $r['openIssues'] ? ' · ' . ($r['issues'] - $r['openIssues']) . ' resolved' : '' }}</span></div>
                    <div class="vxf-review__stat"><b>{{ $r['seconds'] >= 3600 ? gmdate('G\h i\m', $r['seconds']) : intdiv($r['seconds'], 60) . 'm' }}</b><span>packing time</span></div>
                </div>
                @if ($r['notPacked']->isNotEmpty())
                    <div class="vxf-alert" role="alert"><div><strong>{{ $r['notPacked']->count() }} {{ \Illuminate\Support\Str::plural('buyer', $r['notPacked']->count()) }} not packed yet</strong>
                        <div class="vxf-review__left">@foreach ($r['notPacked']->take(12) as $b)<span><b>{{ $b->letter }}</b> {{ $b->buyer_username }}</span>@endforeach @if ($r['notPacked']->count() > 12)<span>+{{ $r['notPacked']->count() - 12 }} more</span>@endif</div>
                        <span class="vxf-sub">Pack them, or flag the ones you can't, before the show can be done.</span></div></div>
                @elseif ($r['blockingIssues'])
                    <div class="vxf-alert" role="alert"><div><strong>{{ $r['blockingIssues'] }} open {{ \Illuminate\Support\Str::plural('issue', $r['blockingIssues']) }} must be resolved first</strong><span class="vxf-sub">Ask the fulfillment admin to resolve it.</span></div></div>
                @else
                    @php $rc = $r['reconcile'] ?? null; $missing = $rc['missing_orders'] ?? []; @endphp
                    @if ($missing)
                        <div class="vxf-alert vxf-alert--warn" role="alert"><div><strong>{{ count($missing) }} {{ \Illuminate\Support\Str::plural('order', count($missing)) }} with no package</strong>
                            <div class="vxf-review__left">@foreach (array_slice($missing, 0, 10) as $m)<span><b>{{ $m['buyer'] ?: '—' }}</b> {{ $m['order'] ? '#' . $m['order'] : '' }}{{ $m['item'] ? ' · ' . \Illuminate\Support\Str::limit($m['item'], 30) : '' }}</span>@endforeach @if (count($missing) > 10)<span>+{{ count($missing) - 10 }} more</span>@endif</div>
                            <span class="vxf-sub">These are on Whatnot but were not packed here ({{ collect($missing)->contains('why', 'Buyer was not on the board') ? 'some buyers ordered after packing started' : 'not in a packed package' }}). Pack them or flag them; if they are handled, you can still confirm.</span></div></div>
                    @endif
                    <label class="vxf-review__confirm"><input type="checkbox" x-model="sure"> <span>{{ $missing ? 'I checked the orders above, and every' : 'Every' }} package for this show is packed and the counts above are right.</span></label>
                @endif
                <div class="vxf-review__foot">
                    <button type="button" class="vxf-btn" wire:click="$set('reviewing', false)">Keep packing</button>
                    <button type="button" class="vxf-btn vxf-btn--primary vxf-btn--lg" wire:click="confirmDone" @disabled(! $ready) x-bind:disabled="{{ $ready ? '! sure' : 'true' }}"><x-heroicon-o-check-badge /> Confirm, show is done</button>
                </div>
            </div>
        @endif

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
            <div><dt>Final sign-off</dt><dd>{{ $run->approved_at ? 'Signed off by ' . $run->approver?->name . ' · ' . $run->approved_at->timezone($tz)->format('M j, g:i A') : 'Waiting for the fulfillment admin' }}</dd></div>
        </dl>
        @if ($run->discrepancies)
            <div class="vxf-alert" role="note"><div><strong>For the sign-off</strong><ul style="margin:4px 0 0;padding-left:18px">@foreach ($run->discrepancies as $d)<li>{{ $d }}</li>@endforeach</ul></div></div>
        @endif
        @if ($run->acknowledged_at)
            <p class="vxf-sub" style="margin:0">Confirmed done by {{ $run->acknowledger?->name }} · {{ $run->acknowledged_at->timezone($tz)->format('M j, g:i A') }}</p>
        @elseif ($run->packers->contains('id', auth()->id()))
            <button type="button" class="vxf-btn vxf-btn--primary" style="justify-self:start" wire:click="acknowledge">Confirm this show is done</button>
        @endif
    </section>
@endif
</div>
</x-filament-panels::page>
