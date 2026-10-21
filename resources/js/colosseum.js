// The Vortex Colosseum at the top of the leaderboard (Show Leaderboard page and
// dashboards). This file is tiny on purpose: the 3D arena (Three.js, models,
// effects) is fetched only once the board is on screen and the page is idle.
// A new champion gets the 3-second battle; otherwise the settled podium shows.
// Names, amounts and show details are ordinary page elements under each
// podium, placed from where the camera actually puts it.
const roots = new WeakSet();

function webglOk() {
    try {
        const c = document.createElement('canvas');
        return !!(c.getContext('webgl2') || c.getContext('webgl'));
    } catch {
        return false;
    }
}

const store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
};

const el = (tag, cls, text) => {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (text != null) e.textContent = text;
    return e;
};

// Where each place sits before the camera reports back:
// #2 left, #1 centre, #3 right — the same spread the final camera aims for.
const DEFAULT_X = { 1: 0.5, 2: 0.21, 3: 0.79 };

function mount(root) {
    if (roots.has(root)) return;
    roots.add(root);
    const section = root.closest('.vxl');
    const lineupOf = () => {
        try { return JSON.parse(section?.querySelector('script[data-vxa-lineup]')?.textContent || 'null'); } catch { return null; }
    };
    // Without 3D the old top-three cards come back. Livewire re-renders reset
    // the board's classes, so the flag is re-applied after each one.
    let fallback = false;
    const fallBack = () => { fallback = true; section?.classList.add('vxa-fallback'); };
    if (!webglOk()) {
        fallBack();
        if (section) new MutationObserver(() => section.classList.add('vxa-fallback')).observe(section, { childList: true, subtree: true });
        return;
    }

    const stage = root.querySelector('[data-vxa-stage]'), details = root.querySelector('[data-vxa-details]');
    const compact = root.dataset.mode === 'compact';
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    let arena = null, lineup = lineupOf(), muted = true, busy = false;
    const seenKey = () => 'vxa-champion-' + (lineup?.period || 'week');

    /** The three detail blocks under the podiums, #2 · #1 · #3. */
    function drawDetails() {
        const rows = lineup?.rows || [];
        details.replaceChildren(...rows.map((p) => {
            const d = el(p.url ? 'a' : 'div', 'vxa-detail vxa-detail-' + p.rank + (p.mine ? ' is-mine' : ''));
            if (p.url) d.href = p.url;
            d.dataset.rank = p.rank;
            d.style.left = (DEFAULT_X[p.rank] * 100) + '%';
            const name = el('b', 'vxa-name');
            name.append(el('i', 'vxa-medal', '#' + p.rank), el('span', null, p.name));
            if (p.mine) name.append(el('em', 'vxa-you', 'You'));
            d.append(name, el('span', 'vxa-show', p.title), el('strong', 'vxa-score' + (p.loss ? ' is-loss' : ''), p.value), el('small', 'vxa-meta', p.meta));
            d.title = p.title;
            return d;
        }));
    }

    /** Line each block up under its podium (the arena reports where they landed). */
    function placeDetails(at) {
        if (!at) return;
        const w = stage.clientWidth || 1;
        for (const d of details.children) {
            const p = at[d.dataset.rank];
            if (p) d.style.left = ((p.x / w) * 100).toFixed(2) + '%';
        }
        // Each block is as wide as the gap to its neighbour, so they never overlap.
        const xs = Object.values(at).map((p) => p.x).sort((a, b) => a - b);
        const gap = Math.min(...xs.slice(1).map((x, i) => x - xs[i]));
        if (Number.isFinite(gap)) {
            const width = Math.round(Math.max(84, gap * 0.94)) + 'px';
            if (details.style.getPropertyValue('--vxa-w') !== width) details.style.setProperty('--vxa-w', width);
        }
    }

    const setMuteUi = () => section?.querySelectorAll('[data-vxa-mute]').forEach((b) => {
        if (b.getAttribute('aria-pressed') !== String(!muted)) b.setAttribute('aria-pressed', String(!muted));
        b.title = muted ? 'Sound off' : 'Sound on';
    });

    const onState = (s) => {
        root.dataset.state = s;
        if (s === 'settled') {
            delete root.dataset.pending;
            busy = false;
            if (lineup?.rows?.length) store.set(seenKey(), String(lineup.rows[0].id));
        }
    };

    async function start(mode) {
        if (busy) return;
        busy = true;
        if (mode === 'quick') root.dataset.pending = '1';
        try {
            if (!arena) {
                const { Arena } = await import('./colosseum/arena.js');
                arena = new Arena(stage, lineup.rows, { base: root.dataset.models, onLabels: placeDetails, onState, compact });
                arena.setMuted(muted);
                root.vxaArena = arena; // for browser checks
            }
            if (mode === 'podium') await arena.showPodium(); else await arena.play('quick');
        } catch (e) {
            console.warn('[colosseum]', e);
            busy = false;
            delete root.dataset.pending;
            fallBack();
        }
    }

    // The controls sit in the board's header, which Livewire re-renders, so
    // listen on the board itself.
    const onClick = (e) => {
        if (e.target.closest('[data-vxa-replay]')) { if (arena && !busy && !reduced) start('quick'); return; }
        if (e.target.closest('[data-vxa-mute]')) { muted = !muted; arena?.setMuted(muted); setMuteUi(); }
    };
    section?.addEventListener('click', onClick);

    // Clicking a Pokémon opens its show, like clicking its details.
    stage.addEventListener('click', (e) => {
        if (root.dataset.state !== 'settled') return;
        const r = stage.getBoundingClientRect(), x = (e.clientX - r.left) / r.width;
        let best = null, dist = 1;
        for (const d of details.children) {
            const dx = Math.abs(parseFloat(d.style.left) / 100 - x);
            if (dx < dist) { dist = dx; best = d; }
        }
        if (best?.href && dist < 0.16) window.location.href = best.href;
    });

    // Period / page changes re-render the lineup beside this block: redraw the
    // details and swap the podium in place, without replaying the fight.
    let last = JSON.stringify(lineup);
    const mo = new MutationObserver(() => {
        setMuteUi();
        if (fallback) section.classList.add('vxa-fallback');
        const next = lineupOf(), key = JSON.stringify(next);
        if (key === last) return;
        last = key;
        lineup = next;
        if (!lineup?.rows?.length) return;
        drawDetails();
        if (arena && !busy) arena.setLineup(lineup.rows);
    });
    if (section) mo.observe(section, { childList: true, subtree: true, characterData: true });

    document.addEventListener('livewire:navigating', () => {
        mo.disconnect();
        section?.removeEventListener('click', onClick);
        arena?.destroy();
        arena = null;
    }, { once: true });

    if (!lineup?.rows?.length) return;
    drawDetails();
    const idle = window.requestIdleCallback || ((fn) => setTimeout(fn, 600));
    const io = new IntersectionObserver(([e]) => {
        if (!e.isIntersecting) return;
        io.disconnect();
        idle(() => {
            const fresh = store.get(seenKey()) !== String(lineup.rows[0].id);
            start(fresh && !reduced ? 'quick' : 'podium');
        }, { timeout: 2500 });
    }, { rootMargin: '120px' });
    io.observe(root);
}

function scan() { document.querySelectorAll('[data-vxa]').forEach(mount); }
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
document.addEventListener('livewire:navigated', scan);
