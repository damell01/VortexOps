{{-- Full-screen final sign-off review. Needs the ReviewsFulfillmentSignoff trait on the page. --}}
    @if ($this->signoffRun)
        @php
            $run = $this->signoffRun; $d = $this->signoffData;
            $packed = $d['packages']->where('status', 'packed')->count(); $pkgTotal = $d['packages']->count();
            $buyersTotal = $d['buyers']->count(); $buyersPacked = $d['buyers']->where('status', 'packed')->count();
            $openIssues = $d['flags']->where('status', 'open'); $signed = $run->status === 'approved';
            $per = 8; $tz = \App\Support\BusinessTime::zone();
            $pages = max(1, (int) ceil($buyersTotal / $per)); $page = min($signoffPage, $pages);
            $pageBuyers = $d['buyers']->values()->forPage($page, $per);
            $byBuyer = $d['packages']->groupBy('buyer_id');
            $secs = $run->activeSeconds();
        @endphp
        <div class="vxf vxf-full" role="dialog" aria-modal="true" aria-labelledby="vxf-so-title"
             x-data="{
                 onKey: null,
                 init() { document.documentElement.style.overflow = 'hidden'; this.onKey = (e) => { if (e.key === 'Escape' && e.isTrusted) $wire.closeSignoff() }; window.addEventListener('keydown', this.onKey) },
                 destroy() { window.removeEventListener('keydown', this.onKey); document.documentElement.style.overflow = '' },
             }">
            <header class="vxf-full__head">
                <div style="min-width:0">
                    <p class="vxf-eyebrow">{{ $signed ? 'Signed off' : 'Final sign-off' }}</p>
                    <h2 id="vxf-so-title">{{ $run->show?->title }}</h2>
                    <p class="vxf-sub" style="margin:0">{{ $run->show?->show_date?->format('D, M j') }}{{ $run->show?->channel ? ' · ' . $run->show->channel->name : '' }}{{ $run->station ? ' · ' . $run->station->name : '' }} · Packed by {{ $run->packers->pluck('name')->join(', ') ?: '—' }}</p>
                </div>
                <button type="button" class="vxf-icon-btn" wire:click="closeSignoff" aria-label="Close"><x-heroicon-o-x-mark /></button>
            </header>

            <div class="vxf-full__body">
                <div class="vxf-review__stats">
                    <div class="vxf-review__stat {{ $buyersPacked === $buyersTotal ? 'is-ok' : 'is-bad' }}"><b>{{ $buyersPacked }}<small>/{{ $buyersTotal }}</small></b><span>buyers packed</span></div>
                    @if ($pkgTotal)<div class="vxf-review__stat {{ $packed === $pkgTotal ? 'is-ok' : 'is-bad' }}"><b>{{ $packed }}<small>/{{ $pkgTotal }}</small></b><span>packages packed</span></div>@endif
                    <div class="vxf-review__stat {{ $openIssues->count() ? 'is-warn' : 'is-ok' }}"><b>{{ $openIssues->count() }}</b><span>open {{ \Illuminate\Support\Str::plural('issue', $openIssues->count()) }}{{ $d['flags']->count() > $openIssues->count() ? ' · ' . ($d['flags']->count() - $openIssues->count()) . ' resolved' : '' }}</span></div>
                    <div class="vxf-review__stat"><b>{{ $secs >= 3600 ? gmdate('G\h i\m', $secs) : intdiv($secs, 60) . 'm' }}</b><span>packing time</span></div>
                    <div class="vxf-review__stat"><b>{{ $run->order_count ?? '—' }}</b><span>orders</span></div>
                </div>

                <div class="vxf-full__grid">
                    <section class="vxf-card" aria-labelledby="vxf-so-buyers">
                        <div class="vxf-card__head"><h3 id="vxf-so-buyers">Buyers &amp; packages</h3><span class="vxf-sub">{{ $buyersTotal }} buyers</span></div>
                        <ul class="vxf-so-list">
                            @forelse ($pageBuyers as $b)
                                @php $pk = $byBuyer->get($b->id, collect()); @endphp
                                <li>
                                    <b class="vxf-so-letter {{ $b->status === 'packed' ? 'is-ok' : '' }}">{{ $b->letter }}</b>
                                    <div class="vxf-so-who"><strong>{{ $b->buyer_username }}</strong>
                                        <span class="vxf-sub">{{ $b->status === 'packed' ? 'Packed by ' . ($b->packer?->name ?? '—') . ($b->packed_at ? ' · ' . $b->packed_at->timezone($tz)->format('g:i A') : '') : ucfirst($b->status) }}</span></div>
                                    <div class="vxf-so-pkgs">
                                        @forelse ($pk as $p)
                                            <span class="vxf-so-pkg {{ $p->status === 'packed' ? 'is-ok' : '' }}">@if ($p->status === 'packed')<x-heroicon-s-check-circle />@else<x-heroicon-o-clock />@endif {{ ! empty($p->order_ids) ? '#' . implode(', #', (array) $p->order_ids) : 'Package' }}</span>
                                        @empty
                                            <span class="vxf-sub">{{ $b->order_count }} {{ \Illuminate\Support\Str::plural('order', $b->order_count) }}</span>
                                        @endforelse
                                    </div>
                                </li>
                            @empty
                                <li class="vxf-empty">No buyers on this show.</li>
                            @endforelse
                        </ul>
                        @if ($pages > 1)
                            <div class="vxf-pager"><button type="button" class="vxf-icon-btn" wire:click="setSignoffPage({{ $page - 1 }})" @disabled($page <= 1) aria-label="Previous buyers"><x-heroicon-o-chevron-left /></button>
                                <span class="vxf-sub">{{ ($page - 1) * $per + 1 }}–{{ min($buyersTotal, $page * $per) }} of {{ $buyersTotal }}</span>
                                <button type="button" class="vxf-icon-btn" wire:click="setSignoffPage({{ $page + 1 }})" @disabled($page >= $pages) aria-label="Next buyers"><x-heroicon-o-chevron-right /></button></div>
                        @endif
                    </section>

                    <aside class="vxf-full__side">
                        <section class="vxf-card vxf-card__body" aria-labelledby="vxf-so-time">
                            <h3 id="vxf-so-time" style="margin:0">Timeline</h3>
                            <ol class="vxf-so-steps">
                                <li class="is-done"><strong>Packing started</strong><span>{{ $run->started_at?->timezone($tz)->format('M j · g:i A') ?? '—' }}</span></li>
                                <li class="is-done"><strong>Packing finished</strong><span>{{ $run->finished_at?->timezone($tz)->format('M j · g:i A') ?? '—' }}</span></li>
                                <li class="{{ $run->acknowledged_at ? 'is-done' : '' }}"><strong>Packer confirmed done</strong><span>{{ $run->acknowledged_at ? $run->acknowledger?->name . ' · ' . $run->acknowledged_at->timezone($tz)->format('M j · g:i A') : 'Not yet' }}</span></li>
                                <li class="{{ $signed ? 'is-done' : 'is-next' }}"><strong>Final sign-off</strong><span>{{ $signed ? $run->approver?->name . ' · ' . $run->approved_at->timezone($tz)->format('M j · g:i A') : 'You' }}</span></li>
                            </ol>
                        </section>
                        @php $rc = $d['reconciliation'] ?? null; @endphp
                        @if ($rc)
                            <section class="vxf-card vxf-card__body" aria-labelledby="vxf-so-rec">
                                <h3 id="vxf-so-rec" style="margin:0;display:flex;align-items:center;gap:8px">Reconciliation
                                    <span class="vxf-pill vxf-pill--{{ $rc['ok'] ? 'approved' : 'paused' }}">{{ $rc['ok'] ? 'All accounted for' : 'Check ' . (count($rc['missing_orders']) + count($rc['missing_shipments'])) }}</span></h3>
                                <dl class="vxf-so-rec">
                                    <div><dt>Whatnot orders</dt><dd class="vxf-num">{{ $rc['orders'] }}</dd></div>
                                    <div><dt>Shipments</dt><dd class="vxf-num">{{ $rc['shipments'] }}</dd></div>
                                    <div><dt>Packages packed</dt><dd class="vxf-num">{{ $rc['packed'] }}</dd></div>
                                    <div><dt>Buyers packed</dt><dd class="vxf-num">{{ $rc['buyers_packed'] }}/{{ $rc['buyers'] }}</dd></div>
                                </dl>
                                @if ($rc['missing_orders'])
                                    <p class="vxf-sub" style="margin:8px 0 4px;font-weight:700;color:var(--vxf-bad,#dc2626)">{{ count($rc['missing_orders']) }} {{ \Illuminate\Support\Str::plural('order', count($rc['missing_orders'])) }} with no package</p>
                                    <ul class="vxf-so-missing">
                                        @foreach (array_slice($rc['missing_orders'], 0, 12) as $m)
                                            <li><strong>{{ $m['buyer'] ?: 'No buyer' }}</strong> <span class="vxf-sub">{{ $m['order'] ? '#' . $m['order'] : '' }}{{ $m['lot'] ? ' · lot ' . $m['lot'] : '' }}{{ $m['item'] ? ' · ' . \Illuminate\Support\Str::limit($m['item'], 40) : '' }} · {{ $m['why'] }}</span></li>
                                        @endforeach
                                        @if (count($rc['missing_orders']) > 12)<li class="vxf-sub">and {{ count($rc['missing_orders']) - 12 }} more</li>@endif
                                    </ul>
                                @endif
                                @if ($rc['missing_shipments'])
                                    <p class="vxf-sub" style="margin:8px 0 4px;font-weight:700;color:var(--vxf-warn,#d97706)">{{ count($rc['missing_shipments']) }} {{ \Illuminate\Support\Str::plural('shipment', count($rc['missing_shipments'])) }} not packed</p>
                                    <ul class="vxf-so-missing">
                                        @foreach (array_slice($rc['missing_shipments'], 0, 8) as $m)
                                            <li><strong>{{ $m['buyer'] ?: 'No buyer' }}</strong> <span class="vxf-sub">{{ $m['tracking'] ?: 'Shipment ' . $m['shipment'] }}</span></li>
                                        @endforeach
                                    </ul>
                                @endif
                            </section>
                        @endif
                        @if ($run->discrepancies)
                            <div class="vxf-alert" role="note"><div><strong>Look at these</strong><ul style="margin:4px 0 0;padding-left:18px">@foreach ($run->discrepancies as $x)<li>{{ $x }}</li>@endforeach</ul></div></div>
                        @endif
                        <section class="vxf-card vxf-card__body" aria-labelledby="vxf-so-issues">
                            <h3 id="vxf-so-issues" style="margin:0">Issues</h3>
                            @forelse ($d['flags']->take(5) as $flag)
                                <div class="vxf-so-issue"><strong>{{ \App\Services\FulfillmentFlagService::label($flag->type) }}</strong> <span class="vxf-pill vxf-pill--{{ $flag->status === 'open' ? 'paused' : 'approved' }}">{{ ucfirst($flag->status) }}</span>
                                    <span class="vxf-sub">{{ $flag->buyer ? $flag->buyer->letter . ' · ' . $flag->buyer->buyer_username . ' · ' : '' }}{{ $flag->details }}</span>
                                    @if ($flag->resolution)<span class="vxf-sub">Resolved: {{ $flag->resolution }}</span>@endif</div>
                            @empty
                                <p class="vxf-sub" style="margin:0">No issues on this show.</p>
                            @endforelse
                        </section>
                    </aside>
                </div>
            </div>

            <footer class="vxf-full__foot">
                @if ($signed)
                    <div class="vxf-so-done"><x-heroicon-s-check-badge /><div><strong>Signed off by {{ $run->approver?->name }}</strong><span class="vxf-sub">{{ $run->approved_at->timezone($tz)->format('M j, Y · g:i A') }}{{ $run->approval_notes ? ' · ' . $run->approval_notes : '' }}</span></div></div>
                    <div style="display:flex;gap:8px;margin-left:auto">
                        <a class="vxf-btn" href="{{ route('fulfillment.run.print', $run) }}" target="_blank" rel="noopener"><x-heroicon-o-printer /> Print record</a>
                        <button type="button" class="vxf-btn vxf-btn--primary" wire:click="closeSignoff">Done</button>
                    </div>
                @else
                    @if ($openIssues->count())
                        <p class="vxf-flash vxf-flash--bad" style="margin:0" role="alert">Resolve the open {{ \Illuminate\Support\Str::plural('issue', $openIssues->count()) }} before signing off.</p>
                    @else
                        <label class="sr-only" for="vxf-so-note">Note (optional)</label>
                        <input id="vxf-so-note" class="vxf-input vxf-so-note" placeholder="Note (optional)" wire:model="signoffNote">
                    @endif
                    <div style="display:flex;gap:8px;margin-left:auto">
                        <button type="button" class="vxf-btn" wire:click="closeSignoff">Not yet</button>
                        <button type="button" class="vxf-btn vxf-btn--primary vxf-btn--lg" wire:click="signOff" @disabled($openIssues->count())><x-heroicon-o-check-badge /> Sign off show</button>
                    </div>
                @endif
            </footer>
        </div>
    @endif
