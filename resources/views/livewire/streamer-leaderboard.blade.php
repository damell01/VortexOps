@php
    $money = fn ($v) => ($v < 0 ? '-$' : '$').number_format(abs($v), 2);
    $visible = $rows->take(min(max(5, $shown), 100));
    $mine = $rows->firstWhere('mine', true);
    $leader = $rows->first();
    $sprite = fn ($row) => '/img/pokemon3d/'.$row['buddy'].'.gif';
@endphp
<section class="vxl" aria-labelledby="vxl-title-{{ $this->getId() }}" data-vx-page="streamer-leaderboard">
    <div class="vxl-head">
        <div><span class="vxl-kicker"><x-filament::icon icon="heroicon-o-trophy" /> Vortex League</span><h2 id="vxl-title-{{ $this->getId() }}">Top Net Shows</h2><p>{{ $from->format('M j') }}–{{ $to->format('M j, Y') }} · {{ \App\Support\BusinessTime::zone() }}</p></div>
        <div class="vxl-controls">
            <label><span class="sr-only">Leaderboard period</span><select wire:model.live="period" aria-label="Leaderboard period">@foreach ($this::PERIODS as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
        </div>
    </div>
    <div class="vxl-toolbar"><p>Shows ranked by Whatnot net earnings · highest first</p></div>
    <div wire:loading role="status" class="vxl-loading">Updating league standings…</div>
    <div wire:loading.class="vxl-updating" wire:target="period">
        @if ($leader)
            <div class="vxl-podium">
                @foreach ($rows->take(3) as $i => $row)
                    <article @class(['vxl-podium-card', 'vxl-podium-first' => $i === 0, 'vxl-is-mine' => $row['mine']]) wire:key="podium-{{ $row['show_id'] }}-{{ $row['streamer_id'] }}">
                        <span class="vxl-medal vxl-medal-{{ min($row['rank'], 4) }}">#{{ $row['rank'] }}</span>
                        <span class="vxl-title-badge">{{ $row['rank'] === 1 ? 'League Champion' : ($row['rank'] <= 3 ? 'Elite Trainer' : 'Challenger') }}</span>
                        <img src="{{ $sprite($row) }}" alt="" aria-hidden="true" loading="lazy" width="80" height="80">
                        <h3>{{ implode(' + ', $row['hosts']) }}@if ($row['mine'])<span class="vxl-you">You</span>@endif</h3>
                        <p class="vxl-show">{{ $row['title'] }}</p>
                        <strong @class(['vxl-score', 'vxl-loss' => $row['value'] < 0])>{{ $money($row['value']) }}</strong>
                        <p class="vxl-meta">{{ $row['channel'] }} · {{ $row['date'] }}@if ($row['hours']) · {{ number_format($row['hours'], 1) }}h @endif</p>
                        <p class="vxl-meta">@if ($row['cohosted'])Cohosted show · @endif{{ $row['estimated'] ? 'Estimated net' : 'Settled net' }}</p>
                    </article>
                @endforeach
            </div>
            @if ($mine)
                <div class="vxl-your-rank"><x-filament::icon icon="heroicon-o-bolt" /><span><b>Your best show: #{{ $mine['rank'] }}</b>@if ($mine['rank'] === 1) · You’re sharing or holding the top spot!@else · {{ $money(max(0, $leader['value'] - $mine['value'])) }} behind the leader @endif</span></div>
            @elseif (auth()->user()?->isStreamer())<p class="vxl-note">No qualifying show for you in this selection yet. Your result will appear when its data is available.</p>@endif
            <div class="vxl-list">
                @foreach ($visible->slice(3) as $row)
                    <article @class(['vxl-row', 'vxl-is-mine' => $row['mine']]) wire:key="standing-{{ $row['show_id'] }}-{{ $row['streamer_id'] }}">
                        <span class="vxl-medal vxl-medal-{{ min($row['rank'], 4) }}">{{ $row['rank'] }}</span>
                        <div class="vxl-row-copy"><b>{{ implode(' + ', $row['hosts']) }}@if ($row['mine'])<span class="vxl-you">You</span>@endif</b><span>{{ $row['title'] }}</span><small>{{ $row['channel'] }} · {{ $row['date'] }}@if ($row['cohosted']) · Cohosted @endif</small></div>
                        <div class="vxl-row-value"><strong @class(['vxl-loss' => $row['value'] < 0])>{{ $money($row['value']) }}</strong><small>{{ $row['estimated'] ? 'Estimated net' : 'Settled net' }}</small></div>
                    </article>
                @endforeach
            </div>
            <div class="vxl-footer"><span>{{ $visible->count() }} of {{ $rows->count() }} shows · Equal scores share a rank</span>@if ($rows->count() > $visible->count() && $visible->count() < 100)<button type="button" wire:click="showMore" wire:loading.attr="disabled">Show 10 more</button>@endif</div>
        @else
            <div class="vxl-empty"><img src="/img/pokemon3d/eevee.gif" alt="" width="72" height="72"><h3>The league is warming up</h3><p>No qualifying results yet. Try another period while shows are awaiting their net earnings.</p></div>
        @endif
    </div>
    <p class="vxl-note">Across all streamers{{ \App\Support\ChannelContext::current() ? ' on '.\App\Support\ChannelContext::current()->name : '' }}. Each show appears once, with cohosts credited together. Net means Whatnot earnings after its fees, before product costs and pay. Shows awaiting earnings are excluded. Estimates can change as shows settle.</p>
</section>
