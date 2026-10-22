@php
    $money = fn ($v) => ($v < 0 ? '-$' : '$').number_format(abs($v), 2);
    $mine = $rows->firstWhere('mine', true);
    $leader = $rows->first();
    $sprite = fn ($row) => '/img/pokemon3d/'.$row['buddy'].'.gif';
    $cinematicVideo = public_path('video/vortex-colosseum-intro.mp4');
    $hasCinematicVideo = is_file($cinematicVideo);
    $pageNumbers = collect([1, $page - 1, $page, $page + 1, $pages])->filter(fn ($n) => $n >= 1 && $n <= $pages)->unique()->sort()->values();
@endphp
<section class="vxl" aria-labelledby="vxl-title-{{ $this->getId() }}" data-vx-page="streamer-leaderboard">
    <div class="vxl-head">
        <div class="vxl-heading"><x-filament::icon icon="heroicon-o-trophy" /><div><h2 id="vxl-title-{{ $this->getId() }}">Top Net Shows</h2><p>{{ $from->format('M j') }}–{{ $to->format('M j, Y') }}</p></div></div>
        <div class="vxl-controls" role="group" aria-label="Leaderboard period">
            @foreach ($this::PERIODS as $value => $label)
                <button type="button" wire:click="$set('period', '{{ $value }}')" aria-pressed="{{ $period === $value ? 'true' : 'false' }}" wire:loading.attr="disabled">{{ $label }}</button>
            @endforeach
        </div>
        <div class="vxa-mini" role="group" aria-label="Leaderboard animation">
            <button type="button" class="vxa-replay-btn" x-data x-on:click="$el.closest('.vxl').querySelector('.vxl-replay')?.click()" title="Replay the podium animation"><x-filament::icon icon="heroicon-m-arrow-path" /><span class="vxa-long">Replay animation</span><span class="vxa-short">Replay</span></button>
        </div>
    </div>
    <div wire:loading role="status" class="vxl-loading">Updating standings…</div>
    <div wire:loading.class="vxl-updating">
        @if ($leader)
            @if ($page === 1)
                {{-- Compact shared Vortex arena: sprite podium reveal, no 3D engine. --}}
                <div class="vxl-podium" data-vxl-cinematic="{{ $hasCinematicVideo ? '1' : '0' }}" wire:key="podium-{{ $period }}-{{ $leader['show_id'] }}"
                     x-data="{ go: false, video: null, play() { this.go = false; this.video = this.$refs.arenaVideo || null; if (this.video && !matchMedia('(prefers-reduced-motion: reduce)').matches) { if (!this.video.src && this.video.dataset.src) this.video.src = this.video.dataset.src; this.video.currentTime = 0; this.video.play().catch(() => {}); } requestAnimationFrame(() => requestAnimationFrame(() => this.go = true)); } }"
                     x-init="if (!matchMedia('(prefers-reduced-motion: reduce)').matches  ) { const k = 'vxl-arena-' + @js($period) + '-' + @js($leader['show_id']); let seen = null; try { seen = localStorage.getItem('vxl-battle'); localStorage.setItem('vxl-battle', k); } catch (e) {} if (seen !== k) play(); }"
                     :class="{ 'vxl-reveal': go }"
                     x-on:animationend="if ($event.animationName === 'vxlr-crown') go = false">
                    @if ($hasCinematicVideo)
                        <video class="vxl-cinematic-video" x-ref="arenaVideo" muted playsinline preload="none" aria-hidden="true" tabindex="-1" poster="" data-src="{{ asset('video/vortex-colosseum-intro.mp4') }}?v={{ filemtime($cinematicVideo) }}"></video>
                    @endif
                    @foreach ($visible->take(3) as $i => $row)
                        <article @class(['vxl-podium-card', 'vxl-place-'.($i + 1), 'vxl-podium-first' => $i === 0, 'vxl-is-mine' => $row['mine']]) wire:key="podium-{{ $row['show_id'] }}">
                            @if ($i === 0)<button type="button" class="vxl-replay" x-on:click="play()" aria-label="Replay the podium reveal" title="Replay the animation"></button>@endif
                            @if ($i === 0)<svg class="vxl-crown" viewBox="0 0 32 24" aria-hidden="true"><path d="M3 5l7 6 6-9 6 9 7-6-4 17H7z" fill="currentColor" stroke="#c58b13" stroke-width="1.5"/></svg>@endif
                            <div class="vxl-scene vxl-effect-{{ $row['effect'] }}" style="--vxl-delay: {{ $i * 0.7 }}s">
                                <span class="vxl-medal vxl-medal-{{ min($row['rank'], 4) }}">#{{ $row['rank'] }}</span>
                                <span class="vxl-attack" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
                                <img class="vxl-fighter" src="{{ $sprite($row) }}" alt="" aria-hidden="true" loading="lazy" width="76" height="76">
                            </div>
                            <div class="vxl-podium-copy">
                                <h3 title="{{ implode(' + ', $row['hosts']) }}">{{ implode(' + ', $row['hosts']) }}@if ($row['mine'])<span class="vxl-you">You</span>@endif</h3>
                                <p class="vxl-show" title="{{ $row['title'] }}">{{ $row['title'] }}</p>
                                <strong @class(['vxl-score', 'vxl-loss' => $row['value'] < 0])>{{ $money($row['value']) }}</strong>
                                <p class="vxl-meta">{{ $row['estimated'] ? 'Estimated net' : 'Settled net' }}@if ($row['cohosted']) · Cohosted @endif</p>
                                <p class="vxl-meta vxl-channel" title="{{ $row['channel'] }}">{{ $row['channel'] }} · {{ $row['date'] }}@if ($row['hours']) · {{ number_format($row['hours'], 1) }}h @endif</p>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
            @if ($mine)
                <div class="vxl-your-rank"><x-filament::icon icon="heroicon-o-bolt" /><span><b>Your best show: #{{ $mine['rank'] }}</b>@if ($mine['rank'] === 1) · At the top! @else · {{ $money(max(0, $leader['value'] - $mine['value'])) }} behind #1 @endif</span></div>
            @endif
            <div class="vxl-list">
                @foreach (($page === 1 ? $visible->slice(3) : $visible) as $row)
                    <article @class(['vxl-row', 'vxl-is-mine' => $row['mine']]) wire:key="standing-{{ $row['show_id'] }}">
                        <span class="vxl-medal">{{ $row['rank'] }}</span>
                        <img class="vxl-row-avatar" src="{{ $sprite($row) }}" alt="" aria-hidden="true" loading="lazy" width="38" height="38">
                        <div class="vxl-row-copy"><b>{{ implode(' + ', $row['hosts']) }}@if ($row['mine'])<span class="vxl-you">You</span>@endif</b><span title="{{ $row['title'] }}">{{ $row['title'] }}</span><small>{{ $row['channel'] }} · {{ $row['date'] }}@if ($row['cohosted']) · Cohosted @endif</small></div>
                        <div class="vxl-row-value"><strong @class(['vxl-loss' => $row['value'] < 0])>{{ $money($row['value']) }}</strong><small>{{ $row['estimated'] ? 'Estimated net' : 'Settled net' }}</small></div>
                    </article>
                @endforeach
            </div>
            <div class="vxl-footer">
                <span>Showing {{ ($page - 1) * $this::PER_PAGE + 1 }}–{{ ($page - 1) * $this::PER_PAGE + $visible->count() }} of {{ $rows->count() }} shows</span>
                @if ($pages > 1)
                    <nav class="vxl-pagination" aria-label="Leaderboard pages">
                        <button type="button" wire:click="goToPage({{ $page - 1 }})" @disabled($page === 1) wire:loading.attr="disabled" aria-label="Previous leaderboard page">‹ <span>Previous</span></button>
                        @foreach ($pageNumbers as $n)
                            @if ($loop->index > 0 && $n > $pageNumbers[$loop->index - 1] + 1)<span aria-hidden="true">…</span>@endif
                            <button type="button" wire:click="goToPage({{ $n }})" @if ($page === $n) aria-current="page" @endif aria-label="Leaderboard page {{ $n }}" wire:loading.attr="disabled">{{ $n }}</button>
                        @endforeach
                        <button type="button" wire:click="goToPage({{ $page + 1 }})" @disabled($page === $pages) wire:loading.attr="disabled" aria-label="Next leaderboard page"><span>Next</span> ›</button>
                    </nav>
                @endif
            </div>
        @else
            <div class="vxl-empty"><img src="/img/pokemon3d/eevee.gif" alt="" width="56" height="56"><h3>No shows on the board yet</h3><p>Results appear once a finished show has net earnings.</p></div>
        @endif
    </div>
    <p class="vxl-note" title="Whatnot earnings after platform fees, before product costs and pay. Each show appears once with cohosts credited together. Equal scores share a rank.">Whatnot net · Across all streamers · Estimates update as shows settle</p>
</section>
