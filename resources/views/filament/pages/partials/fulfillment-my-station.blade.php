{{--
    A packer's home: the show they are on with one big button, what is
    waiting for them next, and today's numbers. Managers see the floor instead.
--}}
@php
    $w = $this->myWork;
    $run = $w['current'];
    $me = auth()->user();
    $first = \Illuminate\Support\Str::before($me?->name ?? '', ' ') ?: 'there';
    $shift = $this->nextShift;
    $board = fn ($r) => \App\Filament\Pages\FulfillmentStationBoard::getUrl(array_filter(['run' => $r->id, 'station' => $r->station_id]));
    $pct = $run && $run->pkg_total ? $run->pkg_packed / $run->pkg_total : 0;
@endphp
<div class="vxmy">
    <div class="vxmy-date">{{ \App\Support\BusinessTime::local()->format('l, M j') }}</div>
    <h1 class="vxmy-hello">Hi {{ $first }} 👋</h1>
    <p class="vxmy-sub">
        @if ($shift && $shift->starts_at->isPast())
            You're on <b>{{ $shift->station?->name ?? 'shift' }}</b> until {{ $shift->ends_at->timezone(\App\Support\BusinessTime::zone())->format('g:i A') }}
        @elseif ($shift)
            Your next shift: {{ $shift->starts_at->timezone(\App\Support\BusinessTime::zone())->format('D g:i A') }}{{ $shift->station ? ' · '.$shift->station->name : '' }}
        @else
            Here's what you're packing
        @endif
    </p>

    @if ($run)
        <section class="vxmy-hero">
            <small>{{ $run->status === 'packing' ? 'Packing now' : ($run->status === 'paused' ? 'Paused' : 'Ready for you') }}{{ $run->station ? ' · '.$run->station->name : '' }}</small>
            <h2>{{ $run->show?->title ?? 'Show' }}</h2>
            <div class="vxmy-meta">{{ $run->show?->channel?->name }}{{ $run->show?->show_date ? ' · streamed '.$run->show->show_date->format('M j') : '' }}{{ $run->pkg_total ? ' · '.$run->pkg_total.' packages' : '' }}</div>
            @if ($run->pkg_total)
                <div class="vxmy-prog"><span><i style="width:{{ round($pct * 100, 1) }}%"></i></span><b>{{ $run->pkg_packed }}/{{ $run->pkg_total }}</b></div>
            @endif
            <a class="vxmy-go" href="{{ $board($run) }}">
                @if ($run->status === 'ready') Start packing @elseif ($run->status === 'paused') Resume packing @else Continue packing @endif
                @if ($w['buyer']) · buyer {{ $w['buyer']->letter }} @endif →
            </a>
        </section>
    @else
        <section class="vxmy-empty">
            <b>Nothing assigned to you right now</b>
            <span>A manager will give you a show. You can also open one that's ready below.</span>
        </section>
        @php $open = $this->queue->take(5); @endphp
        @if ($open->isNotEmpty())
            <h3 class="vxmy-sec">Ready to pack</h3>
            @foreach ($open as $q)
                <a class="vxmy-row" href="{{ $board($q) }}">
                    <span class="vxmy-av">{{ mb_strtoupper(mb_substr($q->show?->title ?? 'S', 0, 1)) }}</span>
                    <div><b>{{ $q->show?->title ?? 'Show' }}</b><span>{{ $q->show?->channel?->name }}{{ $q->show?->show_date ? ' · '.$q->show->show_date->format('M j') : '' }}</span></div>
                    <span class="vxmy-open">Open</span>
                </a>
            @endforeach
        @endif
    @endif

    @if ($w['next']->isNotEmpty())
        <h3 class="vxmy-sec">Up next for you</h3>
        @foreach ($w['next'] as $n)
            <a class="vxmy-row" href="{{ $board($n) }}">
                <span class="vxmy-av">{{ mb_strtoupper(mb_substr($n->show?->title ?? 'S', 0, 1)) }}</span>
                <div><b>{{ $n->show?->title ?? 'Show' }}</b><span>{{ $n->show?->channel?->name }}{{ $n->show?->show_date ? ' · '.$n->show->show_date->format('M j') : '' }}</span></div>
                <span class="vxmy-chip {{ $n->status === 'paused' ? 'warn' : 'blue' }}">{{ $n->status === 'paused' ? 'Paused' : 'Queued' }}</span>
            </a>
        @endforeach
    @endif

    <h3 class="vxmy-sec">Today</h3>
    <div class="vxmy-row"><span class="vxmy-av is-ok">✓</span><div><b>{{ number_format($w['team_today']) }} packages packed</b><span>By the team · you packed {{ number_format($w['mine_today']) }}</span></div></div>
    @if ($w['flags'])
        <a class="vxmy-row" href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'issues']) }}"><span class="vxmy-av is-warn">!</span><div><b>{{ $w['flags'] }} {{ \Illuminate\Support\Str::plural('issue', $w['flags']) }} you flagged</b><span>A manager is on {{ $w['flags'] === 1 ? 'it' : 'them' }}</span></div></a>
    @endif
    <div class="vxmy-links">
        <a href="{{ \App\Filament\Pages\FulfillmentStationBoard::getUrl() }}">All stations</a>
        <a href="{{ \App\Filament\Pages\FulfillmentActivity::getUrl(['tab' => 'schedule']) }}">My schedule</a>
        <a href="{{ route('fulfillment.whiteboard') }}" target="_blank" rel="noopener">TV whiteboard</a>
    </div>
</div>
<style>
.vxmy{max-width:560px;margin:0 auto;padding-bottom:24px}
.vxmy-date{font-size:13px;color:#64748b}
.vxmy-hello{margin:2px 0 0;font-size:26px;font-weight:800;letter-spacing:-.03em}
.vxmy-sub{margin:2px 0 0;color:#64748b;font-size:14px}.vxmy-sub b{color:inherit;color:#0f172a}
.dark .vxmy-sub b{color:#f1f5f9}
.vxmy-hero{margin-top:16px;border-radius:24px;padding:20px;color:#fff;background:linear-gradient(140deg,#7c3aed,#4338ca 65%,#2563eb);box-shadow:0 22px 44px -20px rgba(124,58,237,.65);position:relative;overflow:hidden}
.vxmy-hero::after{content:"";position:absolute;right:-60px;top:-60px;width:190px;height:190px;border-radius:50%;background:radial-gradient(circle,rgba(255,255,255,.25),transparent 70%);pointer-events:none}
.vxmy-hero small{font-size:11.5px;font-weight:750;letter-spacing:.07em;text-transform:uppercase;opacity:.9}
.vxmy-hero h2{margin:6px 0 2px;font-size:21px;line-height:1.25;font-weight:800}
.vxmy-meta{font-size:13px;opacity:.88}
.vxmy-prog{display:flex;align-items:center;gap:12px;margin:16px 0}
.vxmy-prog span{flex:1;height:10px;border-radius:99px;background:rgba(255,255,255,.25);overflow:hidden}
.vxmy-prog i{display:block;height:100%;border-radius:99px;background:#fff}
.vxmy-prog b{font-variant-numeric:tabular-nums}
.vxmy-go{display:flex;align-items:center;justify-content:center;min-height:60px;border-radius:18px;background:#fff;color:#3b1d8f!important;font-size:17px;font-weight:800;text-decoration:none;margin-top:6px;position:relative;z-index:1}
.vxmy-go:active{transform:scale(.99)}
.vxmy-empty{margin-top:16px;border:2px dashed #c4b5fd;border-radius:20px;padding:20px;text-align:center;display:grid;gap:4px}
.vxmy-empty span{color:#64748b;font-size:13px}
.vxmy-sec{margin:22px 0 8px;font-size:12px;font-weight:750;letter-spacing:.07em;text-transform:uppercase;color:#64748b}
.vxmy-row{display:flex;align-items:center;gap:12px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;padding:12px;margin-bottom:8px;color:inherit;text-decoration:none}
.dark .vxmy-row{background:#18151f;border-color:#2c2839}
a.vxmy-row:hover{border-color:#a78bfa}
.vxmy-row>div{min-width:0}.vxmy-row b{display:block;font-size:14px}.vxmy-row span{font-size:12.5px;color:#64748b}
.vxmy-av{flex:none;width:42px;height:42px;border-radius:13px;display:grid;place-items:center;font-weight:800;color:#fff!important;font-size:16px!important;background:linear-gradient(135deg,#8b5cf6,#2563eb)}
.vxmy-av.is-ok{background:linear-gradient(135deg,#16a34a,#0d9488)}.vxmy-av.is-warn{background:linear-gradient(135deg,#f59e0b,#ea580c)}
.vxmy-chip{margin-left:auto;flex:none;font-size:11.5px!important;font-weight:750;padding:3px 10px;border-radius:99px}
.vxmy-chip.blue{background:#dbeafe;color:#1d4ed8!important}.vxmy-chip.warn{background:#fef3c7;color:#b45309!important}
.vxmy-open{margin-left:auto;flex:none;background:#ede9fe;color:#5b21b6!important;font-weight:750;border-radius:11px;padding:9px 14px;text-decoration:none}
.vxmy-links{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
.vxmy-links a{flex:1 1 auto;text-align:center;border:1px solid #e5e7eb;border-radius:12px;padding:10px 12px;font-weight:650;font-size:13px;color:inherit;text-decoration:none}
.dark .vxmy-links a{border-color:#2c2839}
</style>
