<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $station ? 'Station whiteboard' : 'Fulfillment whiteboard' }} · VortexOps</title>
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/filament/admin/theme.css'])
    @endif
    <style>body{margin:0;background:#070b19}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}</style>
</head>
<body>
<main class="vxf-tv vxf-tv--{{ $density }}" id="vxf-tv" style="--vxf-cols: {{ $station ? 1 : max(1, min(4, count($initial['stations']))) }}">
    <header class="vxf-tv__bar">
        <div class="vxf-tv__brand">
            <svg width="40" height="40" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#7c3aed"/><path d="M8 9h16l-8 15z" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round"/><path d="M12.5 12.5h7l-3.5 6.5z" fill="#fff"/></svg>
            <span>VORTEX OPS <small>{{ $station ? 'Station whiteboard' : 'Fulfillment whiteboard' }}</small></span>
        </div>
        <div class="vxf-tv__tools">
            <span class="vxf-tv__clock" id="vxf-clock" aria-live="off">{{ $initial['now'] }}</span>
            @if (! $station)
                @foreach (['compact' => 'S', 'comfortable' => 'M', 'large' => 'L'] as $key => $label)
                    <a href="{{ route('fulfillment.whiteboard', ['density' => $key]) }}" aria-label="{{ ucfirst($key) }} density" @if ($density === $key) aria-current="true" style="border-color:#8b5cf6;color:#fff" @endif>{{ $label }}</a>
                @endforeach
            @else
                <a href="{{ route('fulfillment.whiteboard') }}">All stations</a>
            @endif
            <button type="button" id="vxf-fullscreen">Full screen</button>
        </div>
    </header>
    @include('fulfillment.dev-banner')

    <section id="vxf-board" class="{{ $station ? '' : 'vxf-tv__grid' }}" aria-live="polite" aria-label="Stations"></section>
    <div class="vxf-tv__offline" id="vxf-offline" hidden role="status">Reconnecting…</div>
</main>

<script>
(() => {
    const single = {{ $station ? 'true' : 'false' }};
    const feed = @json($feedUrl);
    const board = document.getElementById('vxf-board');
    const offline = document.getElementById('vxf-offline');
    const statusLabel = { packing: 'Packing', ready: 'Ready', paused: 'Paused', completed: 'Completed', idle: 'Idle' };

    // Build nodes with textContent only: nothing from the feed is ever parsed as HTML.
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined && text !== null) n.textContent = text; return n; };
    const meta = (label, value) => { const p = el('div'); p.append(label + ': '); p.append(el('strong', null, value || '—')); return p; };

    function card(s) {
        const c = el('article', 'vxf-tv__card' + (s.status === 'packing' ? ' vxf-tv__card--packing' : '') + (s.status === 'paused' ? ' vxf-tv__card--paused' : '') + (s.issue ? ' vxf-tv__card--issue' : ''));
        const head = el('div', 'vxf-tv__station');
        head.append(el('span', null, s.name));
        head.append(el('span', 'vxf-tv__status vxf-tv__status--' + s.status, (s.issue && s.status !== 'idle' ? 'Issue · ' : '') + (statusLabel[s.status] || s.status)));
        c.append(head);
        if (s.status === 'idle') { c.append(el('p', 'vxf-tv__idle', 'No show assigned')); return c; }
        c.append(el('p', 'vxf-tv__show', s.show));
        const m = el('div', 'vxf-tv__meta');
        m.append(meta('Stream', s.stream));
        if (s.started) m.append(meta('Packing', s.started));
        m.append(meta('Packer', s.packer));
        c.append(m);
        if (s.letter) {
            const l = el('div', 'vxf-tv__letter');
            l.append(el('span', null, s.letter));
            l.append(el('em', null, s.buyer));
            c.append(l);
        }
        const p = el('div', 'vxf-tv__progress');
        const bar = el('div', 'vxf-bar'); const fill = el('span'); fill.style.width = (s.pct || 0) + '%'; bar.append(fill);
        bar.setAttribute('role', 'progressbar'); bar.setAttribute('aria-valuenow', s.pct || 0); bar.setAttribute('aria-valuemin', 0); bar.setAttribute('aria-valuemax', 100);
        p.append(bar);
        const count = el('div', 'vxf-tv__count');
        count.append(el('span', null, (s.done ?? 0) + ' / ' + (s.total ?? '?') + ' ' + (s.unit || '')));
        count.append(el('span', null, s.total ? (s.pct + '%') : ''));
        p.append(count);
        c.append(p);
        return c;
    }

    function singleView(s) {
        const wrap = el('div', 'vxf-tv__single');
        const left = el('div');
        const head = el('div', 'vxf-tv__station'); head.append(el('span', null, s.name));
        head.append(el('span', 'vxf-tv__status vxf-tv__status--' + s.status, statusLabel[s.status] || s.status));
        left.append(head);
        if (s.status === 'idle') { left.append(el('p', 'vxf-tv__idle', 'No show assigned')); wrap.append(left); return wrap; }
        left.append(el('p', 'vxf-tv__show', s.show));
        const m = el('div', 'vxf-tv__meta');
        m.append(meta('Stream', s.stream)); m.append(meta('Packing start', s.started)); m.append(meta('Packer', s.packer));
        left.append(m);
        const p = card(s).querySelector('.vxf-tv__progress'); if (p) left.append(p);
        wrap.append(left);
        const right = el('div', 'vxf-tv__letter');
        right.append(el('span', null, s.letter || '–'));
        right.append(el('em', null, s.buyer || (s.status === 'ready' ? 'Waiting to start' : '')));
        wrap.append(right);
        return wrap;
    }

    function render(data) {
        document.getElementById('vxf-clock').textContent = data.now;
        board.replaceChildren(...(single ? data.stations.slice(0, 1).map(singleView) : data.stations.map(card)));
    }

    let delay = 5000, timer;
    async function poll() {
        clearTimeout(timer);
        try {
            const r = await fetch(feed, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (r.status === 401 || r.status === 419) { setTimeout(() => location.reload(), 30000); throw new Error('signed out'); }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            render(await r.json());
            offline.hidden = true;
            delay = 5000;
        } catch (e) {
            offline.hidden = false;
            delay = Math.min(delay * 2, 60000);
        }
        timer = setTimeout(poll, delay);
    }

    render(@json($initial));
    timer = setTimeout(poll, 5000);
    window.addEventListener('online', poll);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
    // A TV runs for days: reload now and then to pick up updates.
    setTimeout(() => location.reload(), 6 * 60 * 60 * 1000);

    document.getElementById('vxf-fullscreen').addEventListener('click', () => {
        if (document.fullscreenElement) document.exitFullscreen(); else document.documentElement.requestFullscreen?.();
    });
})();
</script>
</body>
</html>
