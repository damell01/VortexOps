// Show Leaderboard page only. This file is tiny on purpose: the 3D arena
// (Three.js, models, effects) is fetched the first time someone presses
// "Enter arena", so no other page — and no visit that never presses it — pays
// for it. Controls and names stay ordinary page elements outside the canvas.
const roots = new WeakSet();

function webglOk() {
    try {
        const c = document.createElement('canvas');
        return !!(c.getContext('webgl2') || c.getContext('webgl'));
    } catch {
        return false;
    }
}

function readLineup(root) {
    const el = root.closest('.vxl')?.querySelector('script[data-vxa-lineup]');
    try { return el ? JSON.parse(el.textContent) : null; } catch { return null; }
}

const store = {
    get(k) { try { return localStorage.getItem(k); } catch { return null; } },
    set(k, v) { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
};

function mount(root) {
    if (roots.has(root)) return;
    roots.add(root);
    if (!webglOk()) { root.hidden = true; return; }

    const $ = (s) => root.querySelector(s);
    const stage = $('[data-vxa-stage]'), labels = $('[data-vxa-labels]'), note = $('[data-vxa-note]');
    const enter = $('[data-vxa-enter]'), skip = $('[data-vxa-skip]'), replay = $('[data-vxa-replay]'), mute = $('[data-vxa-mute]');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const compact = root.dataset.mode === 'compact';
    let arena = null, lineup = readLineup(root), muted = true;

    const seenKey = () => 'vxa-champion-' + (lineup?.period || 'week');
    const refreshEnter = () => {
        if (!lineup?.rows?.length) { root.hidden = true; return; }
        root.hidden = false;
        const fresh = store.get(seenKey()) !== String(lineup.rows[0].id);
        enter.querySelector('span').textContent = reduced ? 'Show 3D podium' : fresh ? 'New champion — enter arena' : 'Enter arena';
        enter.classList.toggle('is-new', fresh && !reduced);
    };

    const drawLabels = (at, picks) => {
        if (!at) { labels.replaceChildren(); return; }
        if (labels.childElementCount !== (picks?.length || 0)) {
            labels.replaceChildren(...picks.map((p) => {
                const d = document.createElement('div');
                d.className = 'vxa-label vxa-label-' + p.rank;
                const b = document.createElement('b'); b.textContent = '#' + p.rank;
                const n = document.createElement('span'); n.textContent = p.name;
                const v = document.createElement('strong'); v.textContent = p.value;
                d.append(b, n, v);
                if (p.standIn) {
                    const s = document.createElement('small');
                    s.textContent = 'Arena stand-in for ' + (p.buddyName || 'their buddy');
                    d.append(s);
                }
                d.dataset.rank = p.rank;
                return d;
            }));
            requestAnimationFrame(() => labels.classList.add('is-in'));
        }
        for (const d of labels.children) {
            const p = at[d.dataset.rank];
            if (p) d.style.transform = `translate(${Math.round(p.x)}px, ${Math.round(p.y)}px) translate(-50%, 0)`;
        }
    };

    const onState = (s) => {
        root.dataset.state = s;
        skip.hidden = s !== 'playing';
        replay.hidden = s !== 'settled' || reduced;
        enter.hidden = s !== 'idle' || compact;
        mute.hidden = compact && (s === 'idle' || s === 'loading');
        note.textContent = s === 'loading' ? 'Loading the arena…' : '';
        root.classList.toggle('is-active', s !== 'idle');
        // A plain class on the board (not a :has() rule) hides the 2D sprites,
        // so moving labels never make the browser re-check the whole section.
        root.closest('.vxl')?.classList.toggle('vxa-on', s !== 'idle');
        if (s === 'settled' && lineup?.rows?.length) store.set(seenKey(), String(lineup.rows[0].id));
    };

    // Every run is the 3-second battle; 'podium' shows the settled podium only.
    async function start(mode = 'quick') {
        stage.hidden = false;
        onState('loading');
        try {
            if (!arena) {
                const { Arena } = await import('./colosseum/arena.js');
                arena = new Arena(stage, lineup.rows, { base: root.dataset.models, onLabels: drawLabels, onState, compact });
                arena.setMuted(muted);
                root.vxaArena = arena; // for browser checks
            }
            labels.classList.remove('is-in');
            drawLabels(null);
            if (mode === 'podium') await arena.showPodium(); else await arena.play(mode);
        } catch (e) {
            console.warn('[colosseum]', e);
            stage.hidden = true;
            onState('idle');
            note.textContent = 'The 3D arena could not load here; the standings below are unchanged.';
        }
    }

    enter.addEventListener('click', () => start('quick'));
    replay.addEventListener('click', () => start('quick'));
    skip.addEventListener('click', () => arena?.skip());
    mute.addEventListener('click', () => {
        muted = !muted;
        mute.setAttribute('aria-pressed', String(!muted));
        mute.querySelector('span').textContent = muted ? 'Sound off' : 'Sound on';
        arena?.setMuted(muted);
    });

    // Period / page changes re-render the lineup next to this (ignored) block:
    // swap the podium in place instead of replaying the fight.
    const section = root.closest('.vxl');
    let last = JSON.stringify(lineup);
    const mo = new MutationObserver(() => {
        const next = readLineup(root);
        const key = JSON.stringify(next);
        section.classList.toggle('vxa-on', root.classList.contains('is-active'));
        if (key === last) return;
        last = key;
        lineup = next;
        refreshEnter();
        if (arena && next?.rows?.length) { labels.classList.remove('is-in'); drawLabels(null); arena.setLineup(next.rows); }
    });
    if (section) mo.observe(section, { childList: true, subtree: true, characterData: true });

    const teardown = () => { mo.disconnect(); arena?.destroy(); arena = null; };
    document.addEventListener('livewire:navigating', teardown, { once: true });
    refreshEnter();

    // Dashboards: nothing loads until the board is on screen and the page has
    // finished its own work; then a new champion gets the 3-second battle and
    // anyone else sees the settled 3D podium.
    if (compact && lineup?.rows?.length) {
        mute.hidden = true;
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
}

function scan() { document.querySelectorAll('[data-vxa]').forEach(mount); }
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan); else scan();
document.addEventListener('livewire:navigated', scan);
