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
    <style>body{margin:0;background:#060914}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)}</style>
    <style>
        .vxtv__station-switch { display:flex; align-items:center; gap:5px; flex-wrap:wrap; }
        .vxtv__station-link { display:inline-flex; align-items:center; justify-content:center; min-height:34px; padding:5px 11px; border:1px solid #30375f; border-radius:9px; background:#171b3b; color:#e4e9ff; text-decoration:none; font:600 12px/1.2 system-ui,sans-serif; white-space:nowrap; }
        .vxtv__station-link:hover, .vxtv__station-link:focus-visible { border-color:#a78bfa; background:#30205b; }
        .vxtv__station-link[aria-current="page"] { background:#7c3aed; border-color:#b794f6; color:white; }
        @media(max-width:1050px) { .vxtv__station-switch { max-width:100%; overflow-x:auto; flex-wrap:nowrap; } }
        /* Show-finished celebration */
        .vxtv-cheer { position:fixed; inset:0; z-index:90; display:grid; place-items:center; pointer-events:none; background:radial-gradient(circle at 50% 45%, rgba(124,58,237,.45), rgba(6,9,20,.82) 70%); animation:vxtv-cheer-in .5s ease-out both; overflow:hidden; }
        .vxtv-cheer[hidden] { display:none; }
        .vxtv-cheer.is-out { animation:vxtv-cheer-out .6s ease-in both; }
        .vxtv-cheer__card { text-align:center; color:#fff; font-family:system-ui,sans-serif; padding:32px 48px; border-radius:28px; background:rgba(23,27,59,.92); border:2px solid #a78bfa; box-shadow:0 30px 80px rgba(0,0,0,.5); animation:vxtv-cheer-pop .7s cubic-bezier(.2,1.6,.4,1) both; max-width:min(860px,90vw); }
        .vxtv-cheer__emoji { font-size:clamp(56px,9vw,110px); line-height:1; }
        .vxtv-cheer__kicker { margin-top:10px; font:800 clamp(16px,2vw,24px)/1.2 system-ui,sans-serif; letter-spacing:.14em; text-transform:uppercase; color:#86efac; }
        .vxtv-cheer__title { margin:8px 0 0; font:900 clamp(28px,4.6vw,64px)/1.1 system-ui,sans-serif; }
        .vxtv-cheer__meta { margin-top:10px; font:600 clamp(16px,2vw,26px)/1.3 system-ui,sans-serif; color:#c7d2fe; }
        .vxtv-cheer__bit { position:absolute; top:-20px; width:12px; height:18px; border-radius:3px; animation:vxtv-fall linear forwards; }
        @keyframes vxtv-cheer-in { from { opacity:0 } to { opacity:1 } }
        @keyframes vxtv-cheer-out { to { opacity:0 } }
        @keyframes vxtv-cheer-pop { from { transform:scale(.6); opacity:0 } to { transform:scale(1); opacity:1 } }
        @keyframes vxtv-fall { to { transform:translateY(110vh) rotate(720deg); } }
        @media (prefers-reduced-motion: reduce) { .vxtv-cheer, .vxtv-cheer__card { animation:none } .vxtv-cheer__bit { display:none } }
    </style>
</head>
<body>
<main class="vxtv vxtv--{{ $density }} {{ $station ? 'vxtv--single' : '' }}" id="vxtv" data-density="{{ $density }}">
    <header class="vxtv__bar">
        <div class="vxtv__brand">
            <svg width="44" height="44" viewBox="0 0 32 32" aria-hidden="true"><rect width="32" height="32" rx="9" fill="#7c3aed"/><path d="M8 9h16l-8 15z" fill="none" stroke="#fff" stroke-width="2.6" stroke-linejoin="round"/><path d="M12.5 12.5h7l-3.5 6.5z" fill="#fff"/></svg>
            <span>VORTEX OPS <small>{{ $station ? 'Station whiteboard' : 'Fulfillment whiteboard' }}</small></span>
            <span class="vxtv__live" id="vxtv-live"><i aria-hidden="true"></i> Live</span>
            <span class="vxtv__dev">In development</span>
        </div>
        @unless ($station)
            <dl class="vxtv__summary" aria-label="Today">
                <div><dt>Stations packing</dt><dd><b data-sum="packing">0</b><span>/<b data-sum="stations">0</b></span></dd></div>
                <div><dt>Packages today</dt><dd><b data-sum="packed_today">0</b></dd></div>
                <div><dt>Buyers today</dt><dd><b data-sum="buyers_today">0</b></dd></div>
                <div class="vxtv__sum-issue"><dt>Open issues</dt><dd><b data-sum="issues">0</b></dd></div>
            </dl>
        @endunless
        <div class="vxtv__tools">
            <span class="vxtv__clock" id="vxtv-clock" aria-live="off">{{ $initial['now'] }}</span>
            <a class="vxtv__home" href="{{ \App\Filament\Pages\FulfillmentHome::getUrl(panel: 'admin') }}"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10"/></svg> Fulfillment Home</a>
            <nav class="vxtv__station-switch" aria-label="Choose station">
                <a class="vxtv__station-link" href="{{ route('fulfillment.whiteboard', ['density' => $density]) }}" @if (! $station) aria-current="page" @endif>All stations</a>
                @foreach ($stationList as $s)
                    <a class="vxtv__station-link" href="{{ route('fulfillment.whiteboard', ['station' => $s->id, 'density' => $density]) }}" @if ($station === $s->id) aria-current="page" @endif>{{ $s->name }}</a>
                @endforeach
            </nav>
            @if (! $station)
                @foreach (['compact' => 'S', 'comfortable' => 'M', 'large' => 'L', 'rows' => 'Rows'] as $key => $label)
                    <a href="{{ route('fulfillment.whiteboard', ['density' => $key]) }}" aria-label="{{ $key === 'rows' ? 'One row per station' : ucfirst($key) . ' cards' }}" @if ($density === $key) aria-current="true" @endif>{{ $label }}</a>
                @endforeach
            @endif
            <button type="button" id="vxf-fullscreen">Full screen</button>
        </div>
    </header>

    <section id="vxf-board" class="vxtv__board" aria-live="polite" aria-label="Stations"></section>
    <div class="vxtv-cheer" id="vxtv-cheer" role="status" aria-live="assertive" hidden>
        <div class="vxtv-cheer__card"><div class="vxtv-cheer__emoji" aria-hidden="true">🎉</div><div class="vxtv-cheer__kicker">Show packed!</div><p class="vxtv-cheer__title" data-cheer="title"></p><div class="vxtv-cheer__meta" data-cheer="meta"></div></div>
    </div>
    <nav class="vxtv__pages" id="vxtv-pages" aria-label="Station pages" hidden></nav>

    @unless ($station)
        <dl class="vxtv__totals" aria-label="Today">
            <div><dt>Stations active</dt><dd><b data-sum="packing">0</b><span>/<b data-sum="stations">0</b></span></dd></div>
            <div><dt>Packages today</dt><dd><b data-sum="packed_today">0</b></dd></div>
            <div><dt>Buyers today</dt><dd><b data-sum="buyers_today">0</b></dd></div>
            <div class="vxtv__sum-issue"><dt>Open issues</dt><dd><b data-sum="issues">0</b></dd></div>
        </dl>
    @endunless
    <footer class="vxtv__ticker" id="vxtv-ticker" hidden>
        <span class="vxtv__ticker-label">Recent</span>
        <div class="vxtv__ticker-window"><div class="vxtv__ticker-track" id="vxtv-track"></div></div>
    </footer>
    <div class="vxtv__offline" id="vxf-offline" hidden role="status">Reconnecting…</div>
</main>

<script>
(() => {
    const single = {{ $station ? 'true' : 'false' }};
    const density = @json($density);
    const feed = @json($feedUrl);
    const stationUrl = @json(route('fulfillment.whiteboard', ['station' => '__ID__', 'density' => $density]));
    const board = document.getElementById('vxf-board');
    const offline = document.getElementById('vxf-offline');
    const pagesNav = document.getElementById('vxtv-pages');
    const ticker = document.getElementById('vxtv-ticker');
    const track = document.getElementById('vxtv-track');
    const calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const statusLabel = { packing: 'Packing', ready: 'Ready', paused: 'Paused', completed: 'Completed', idle: 'Idle' };
    const perPage = { compact: 8, comfortable: 4, large: 2, rows: 4 }[density] || 4;

    // Station selection uses accessible links so switching works without JavaScript.

    // Everything from the feed goes in as text; nothing is parsed as HTML.
    const el = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text !== undefined && text !== null) n.textContent = text; return n; };
    const svg = (tag, attrs) => { const n = document.createElementNS('http://www.w3.org/2000/svg', tag); for (const k in attrs) n.setAttribute(k, attrs[k]); return n; };
    const set = (node, text) => { const t = text === undefined || text === null ? '' : String(text); if (node.textContent !== t) node.textContent = t; };
    const fmt = (sec) => { sec = Math.max(0, Math.floor(sec)); const h = Math.floor(sec / 3600), m = Math.floor(sec % 3600 / 60), s = sec % 60; return (h ? h + ':' + String(m).padStart(2, '0') : m) + ':' + String(s).padStart(2, '0'); };

    // ── Summary numbers count up to their new value ──────────────────────
    function countTo(node, value) {
        const from = parseInt(node.textContent, 10) || 0, to = Number(value) || 0;
        if (from === to) return;
        if (calm) { node.textContent = to; return; }
        const start = performance.now(), dur = 700;
        const tick = (t) => { const k = Math.min(1, (t - start) / dur), e = 1 - Math.pow(1 - k, 3); node.textContent = Math.round(from + (to - from) * e); if (k < 1) requestAnimationFrame(tick); };
        requestAnimationFrame(tick);
        node.parentElement.classList.remove('is-bump'); void node.offsetWidth; node.parentElement.classList.add('is-bump');
    }

    // ── Station cards: built once, then updated in place so rings and bars animate ──
    const cards = new Map();
    const R = 52, C = 2 * Math.PI * R;

    function buildCard(s) {
        const c = el(single ? 'article' : 'a', 'vxtv__card');
        if (!single) { c.href = stationUrl.replace('__ID__', s.id); }
        c.dataset.kind = s.status === 'idle' ? 'idle' : 'active';
        const head = el('div', 'vxtv__head');
        const name = el('span', 'vxtv__name'); head.append(name);
        const pill = el('span', 'vxtv__pill'); head.append(pill);
        c.append(head);
        const refs = { name, pill };
        if (s.status === 'idle') {
            const idle = el('div', 'vxtv__idle');
            idle.append(el('span', 'vxtv__idle-icon', '◇'), el('p', null, 'No show assigned'), el('small', null, 'Waiting for the next show'));
            c.append(idle);
            return { c, refs };
        }
        refs.show = el('p', 'vxtv__show'); c.append(refs.show);
        const meta = el('div', 'vxtv__meta');
        refs.stream = el('span'); refs.started = el('span'); refs.people = el('span', 'vxtv__people');
        meta.append(refs.stream, refs.started, refs.people); c.append(meta);

        const main = el('div', 'vxtv__main');
        const who = el('div', 'vxtv__who');
        refs.letter = el('span', 'vxtv__letter'); refs.buyer = el('em', 'vxtv__buyer'); refs.whoLabel = el('small', 'vxtv__who-label');
        const whoText = el('div', 'vxtv__who-text'); whoText.append(refs.whoLabel, refs.buyer);
        who.append(refs.letter, whoText);
        const ring = svg('svg', { viewBox: '0 0 120 120', class: 'vxtv__ring', role: 'img' });
        ring.append(svg('circle', { cx: 60, cy: 60, r: R, class: 'vxtv__ring-bg' }));
        refs.arc = svg('circle', { cx: 60, cy: 60, r: R, class: 'vxtv__ring-fg', 'stroke-dasharray': C, 'stroke-dashoffset': C });
        ring.append(refs.arc);
        refs.ring = ring;
        const ringWrap = el('div', 'vxtv__ring-wrap');
        refs.pct = el('b', 'vxtv__pct'); refs.count = el('small', 'vxtv__count');
        const ringText = el('div', 'vxtv__ring-text'); ringText.append(refs.pct, refs.count);
        ringWrap.append(ring, ringText);
        main.append(who, ringWrap); c.append(main);

        refs.nextWrap = el('div', 'vxtv__next');
        refs.nextWrap.append(el('span', 'vxtv__next-label', 'Next'));
        refs.nextList = el('div', 'vxtv__next-list'); refs.nextWrap.append(refs.nextList);
        c.append(refs.nextWrap);

        const foot = el('div', 'vxtv__foot');
        refs.timer = el('span', 'vxtv__timer'); refs.buyers = el('span', 'vxtv__buyers');
        foot.append(refs.timer, refs.buyers); c.append(foot);
        const bar = el('div', 'vxtv__bar-line'); refs.fill = el('span'); bar.append(refs.fill); c.append(bar);
        return { c, refs };
    }

    function updateCard(entry, s, received) {
        const { c, refs } = entry;
        c.className = 'vxtv__card vxtv__card--' + s.status + (s.issue ? ' vxtv__card--issue' : '');
        c.setAttribute('aria-label', s.name + ': ' + (statusLabel[s.status] || s.status) + (s.show ? ', ' + s.show : ''));
        set(refs.name, s.name);
        set(refs.pill, (s.issue && s.status !== 'idle' ? 'Issue · ' : '') + (statusLabel[s.status] || s.status));
        refs.pill.className = 'vxtv__pill vxtv__pill--' + (s.issue && s.status !== 'idle' ? 'issue' : s.status);
        if (s.status === 'idle') return;
        set(refs.show, s.show);
        set(refs.stream, s.stream ? 'Stream ' + s.stream : '');
        set(refs.started, s.started ? 'Started ' + s.started : 'Not started');
        refs.people.replaceChildren(...(s.initials || []).map(i => el('i', null, i)), el('span', null, s.packer || 'Unassigned'));
        // Between buyers (none selected yet) the next one in line is shown as "up next".
        const upNext = !s.letter && (s.status === 'packing' || s.status === 'paused') && s.next && s.next.length ? s.next[0] : null;
        const letter = s.letter || (upNext ? upNext.letter : (s.status === 'ready' ? '–' : '·'));
        c.classList.toggle('vxtv__card--between', !!upNext);
        if (refs.letter.textContent !== letter) {
            refs.letter.textContent = letter;
            if (!calm) { refs.letter.classList.remove('is-flip'); void refs.letter.offsetWidth; refs.letter.classList.add('is-flip'); }
        }
        set(refs.buyer, s.buyer || (upNext ? upNext.buyer : (s.status === 'ready' ? 'Waiting to start' : '')));
        set(refs.whoLabel, s.buyer ? (s.status === 'paused' ? 'Paused on' : 'Now packing') : upNext ? 'Up next' : '');
        const pct = s.pct || 0;
        refs.arc.setAttribute('stroke-dashoffset', String(C * (1 - pct / 100)));
        refs.ring.setAttribute('aria-label', pct + '% packed');
        set(refs.pct, s.total ? pct + '%' : '—');
        set(refs.count, (s.done ?? 0) + ' / ' + (s.total ?? '?') + ' ' + (s.unit || ''));
        refs.fill.style.width = pct + '%';
        refs.nextList.replaceChildren(...(s.next || []).slice(upNext ? 1 : 0).map(n => { const chip = el('span', 'vxtv__chip'); chip.append(el('b', null, n.letter), el('span', null, n.buyer)); return chip; }));
        refs.nextWrap.hidden = !((s.next || []).slice(upNext ? 1 : 0).length);
        set(refs.buyers, s.buyers_total ? s.buyers_done + ' / ' + s.buyers_total + ' buyers' : '');
        entry.elapsed = s.elapsed; entry.ticking = !!s.ticking; entry.received = received; entry.pause = s.pause_reason || '';
        paintTimer(entry);
    }

    function paintTimer(entry) {
        if (!entry.refs.timer) return;
        if (entry.elapsed === null || entry.elapsed === undefined) { set(entry.refs.timer, ''); return; }
        const live = entry.ticking ? (Date.now() - entry.received) / 1000 : 0;
        set(entry.refs.timer, (entry.ticking ? '⏱ ' : 'Paused' + (entry.pause ? ' (' + entry.pause + ')' : '') + ' · ') + fmt(entry.elapsed + live));
    }

    // ── Pages rotate when there are more stations than fit ───────────────
    let page = 0, pageTimer = null, order = [];
    function showPage(p) {
        const pages = Math.max(1, Math.ceil(order.length / perPage));
        page = ((p % pages) + pages) % pages;
        order.forEach((id, i) => { const card = cards.get(id).c; card.hidden = Math.floor(i / perPage) !== page; });
        pagesNav.hidden = pages < 2;
        pagesNav.replaceChildren(...Array.from({ length: pages }, (_, i) => {
            const b = el('button', 'vxtv__dot' + (i === page ? ' is-on' : ''));
            b.type = 'button'; b.setAttribute('aria-label', 'Page ' + (i + 1) + ' of ' + pages); if (i === page) b.setAttribute('aria-current', 'true');
            b.addEventListener('click', () => { showPage(i); restartRotation(); });
            return b;
        }));
        if (!calm && pages > 1) { board.classList.remove('is-turn'); void board.offsetWidth; board.classList.add('is-turn'); }
    }
    function restartRotation() {
        clearInterval(pageTimer);
        if (!single && order.length > perPage) pageTimer = setInterval(() => showPage(page + 1), 12000);
    }

    function render(data) {
        const received = Date.now();
        set(document.getElementById('vxtv-clock'), data.now);
        const list = single ? data.stations.slice(0, 1) : data.stations;
        const ids = list.map(s => s.id);
        for (const [id, entry] of cards) if (!ids.includes(id)) { entry.c.remove(); cards.delete(id); }
        list.forEach(s => {
            let entry = cards.get(s.id);
            const kind = s.status === 'idle' ? 'idle' : 'active';
            if (entry && entry.c.dataset.kind !== kind) { const fresh = buildCard(s); entry.c.replaceWith(fresh.c); entry = fresh; cards.set(s.id, entry); }
            if (!entry) { entry = buildCard(s); cards.set(s.id, entry); }
            updateCard(entry, s, received);
        });
        board.replaceChildren(...ids.map(id => cards.get(id).c));
        const cols = single || density === 'rows' ? 1 : Math.min(density === 'large' ? 2 : 4, Math.max(1, Math.min(list.length, perPage)));
        board.style.setProperty('--cols', cols);
        const changed = order.join() !== ids.join();
        order = ids;
        if (changed) { showPage(page); restartRotation(); } else showPage(page);

        if (data.summary) for (const [k, v] of Object.entries(data.summary)) document.querySelectorAll('[data-sum="' + k + '"]').forEach(n => countTo(n, v));
        if (data.summary) document.querySelectorAll('.vxtv__sum-issue').forEach(n => n.classList.toggle('is-alert', data.summary.issues > 0));
        renderTicker(data.activity || []);
        cheerFor(data.activity || []);
    }

    // ── Celebrate a show finishing ────────────────────────────────────────
    // Only shows that finish while the screen is up: the first feed sets the mark.
    let cheerMark = null, cheerQueue = [], cheering = false;
    const cheerEl = document.getElementById('vxtv-cheer');
    function cheerFor(items) {
        const top = items.reduce((m, i) => Math.max(m, i.id), 0);
        if (cheerMark === null) { cheerMark = top; return; }
        items.filter(i => i.type === 'finished' && i.id > cheerMark).sort((a, b) => a.id - b.id).forEach(i => cheerQueue.push(i));
        cheerMark = Math.max(cheerMark, top);
        nextCheer();
    }
    function nextCheer() {
        if (cheering || !cheerQueue.length) return;
        cheering = true;
        const i = cheerQueue.shift();
        set(cheerEl.querySelector('[data-cheer="title"]'), i.show || 'A show');
        set(cheerEl.querySelector('[data-cheer="meta"]'), [i.station, i.packages ? i.packages + ' packages' : null].filter(Boolean).join(' · '));
        cheerEl.querySelectorAll('.vxtv-cheer__bit').forEach(n => n.remove());
        if (!calm) {
            const colors = ['#a78bfa', '#86efac', '#fbbf24', '#60a5fa', '#f472b6', '#fff'];
            for (let k = 0; k < 90; k++) {
                const b = el('span', 'vxtv-cheer__bit');
                b.style.left = (Math.random() * 100) + 'vw';
                b.style.background = colors[k % colors.length];
                b.style.animationDuration = (2.6 + Math.random() * 2.4) + 's';
                b.style.animationDelay = (Math.random() * 1.2) + 's';
                cheerEl.append(b);
            }
        }
        cheerEl.classList.remove('is-out');
        cheerEl.hidden = false;
        setTimeout(() => cheerEl.classList.add('is-out'), 6400);
        setTimeout(() => { cheerEl.hidden = true; cheering = false; nextCheer(); }, 7000);
    }

    // ── Recent activity ticker ───────────────────────────────────────────
    let tickerKey = '';
    function renderTicker(items) {
        const key = items.map(i => i.id).join();
        ticker.hidden = items.length === 0;
        if (key === tickerKey) return;
        tickerKey = key;
        const row = () => items.map(i => {
            const it = el('span', 'vxtv__tick vxtv__tick--' + i.type);
            it.append(el('time', null, i.at));
            if (i.station) it.append(el('strong', null, i.station));
            if (i.letter) it.append(el('b', 'vxtv__tick-letter', i.letter));
            if (i.buyer) it.append(el('span', null, i.buyer));
            it.append(el('em', null, i.text));
            return it;
        });
        track.replaceChildren(...row(), ...(calm ? [] : row()));
        track.style.setProperty('--dur', Math.max(20, items.length * 6) + 's');
    }

    // ── Feed polling with backoff ─────────────────────────────────────────
    let delay = 5000, timer;
    async function poll() {
        clearTimeout(timer);
        try {
            const r = await fetch(feed, { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (r.status === 401 || r.status === 419) { setTimeout(() => location.reload(), 30000); throw new Error('signed out'); }
            if (!r.ok) throw new Error('HTTP ' + r.status);
            render(await r.json());
            offline.hidden = true; document.getElementById('vxtv-live').classList.remove('is-off');
            delay = 5000;
        } catch (e) {
            offline.hidden = false; document.getElementById('vxtv-live').classList.add('is-off');
            delay = Math.min(delay * 2, 60000);
        }
        timer = setTimeout(poll, delay);
    }

    render(@json($initial));
    timer = setTimeout(poll, 5000);
    setInterval(() => cards.forEach(paintTimer), 1000);
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
