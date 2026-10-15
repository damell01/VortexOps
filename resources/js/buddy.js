// The dashboard buddy: the Pokémon peeking over the bottom edge of the streamer
// home banner. Everything here is small and occasional, and one place decides
// what it is doing so the reactions never pile on top of each other:
//
//  - it leans toward the cursor while it moves over the banner, and perks up
//    when the cursor reaches it (one "look" behaviour)
//  - a click or tap says hello in a bubble, then it ducks away and peeks back
//    from another free spot along the edge
//  - after a quiet spell it ducks down and now and then peeks its eyes out;
//    when someone comes back it pops up and stretches (one idle timer)
//  - it ducks when the banner scrolls away and is back when you return
//  - morning is sleepy, night is resting, the day is alert
//  - it gets excited once when the next show is 15 minutes away, and gives a
//    little hop when the clock-in/out goes through
//
// The sprite is a plain animated GIF, so "looking" is the whole buddy turning
// and leaning, not its eyes moving.

const calm = window.matchMedia('(prefers-reduced-motion: reduce)');
const finePointer = window.matchMedia('(hover: hover) and (pointer: fine)');
const IDLE_MS = 45_000;
const PEEK_MS = 20_000;
const SOON_MS = 15 * 60_000;

let current = null;

function greeting(name) {
    const h = new Date().getHours();
    const hi = h < 5 ? 'Up late' : h < 12 ? 'Good morning' : h < 17 ? 'Good afternoon' : h < 21 ? 'Good evening' : 'Hey night owl';
    const lines = [`${hi}, ${name}!`, `Hi ${name}! Ready for the next show?`, `${hi}! Keep up the good work.`, `Hey ${name}! 👋`];
    return lines[Math.floor(Math.random() * lines.length)];
}

function mood() {
    const h = new Date().getHours();
    return h >= 5 && h < 11 ? 'sleepy' : h >= 21 || h < 5 ? 'resting' : 'alert';
}

function setup(mon) {
    const hero = mon.closest('.vsh-hero');
    const body = mon.querySelector('.vsh-mon-body');
    const bubble = hero?.querySelector('.vsh-bubble');
    const bubbleText = bubble?.querySelector('.vsh-bubble-text');
    if (!hero || !body) return null;

    const name = mon.dataset.name || 'trainer';
    const timers = new Set();
    const later = (fn, ms) => { const t = setTimeout(() => { timers.delete(t); fn(); }, ms); timers.add(t); return t; };
    let busy = false, ducked = false, idle = false, away = false, idleTimer = 0, peekTimer = 0, bubbleTimer = 0;

    mon.dataset.mood = mood();
    body.classList.remove('is-perk', 'is-hop', 'is-wiggle', 'is-stretch', 'is-cheer');

    // One-off body animations (hop, stretch, wiggle…): restart cleanly each time.
    const play = (cls, ms) => {
        if (calm.matches) return;
        body.classList.remove(cls); void body.offsetWidth; body.classList.add(cls);
        later(() => body.classList.remove(cls), ms);
    };

    const say = (text, ms = 4200) => {
        if (!bubble || !bubbleText) return;
        bubbleText.textContent = text;
        bubble.hidden = false;
        place();
        requestAnimationFrame(() => bubble.classList.add('is-open'));
        clearTimeout(bubbleTimer);
        bubbleTimer = later(() => { bubble.classList.remove('is-open'); later(() => { bubble.hidden = true; }, 200); }, ms);
    };

    // Keep the bubble just above the buddy wherever it is standing.
    function place() {
        if (!bubble || bubble.hidden) return;
        const h = hero.getBoundingClientRect(), m = mon.getBoundingClientRect();
        const width = bubble.offsetWidth, mid = m.left - h.left + m.width / 2;
        // Open toward the roomier side, so it covers as little as possible.
        const want = mid > h.width / 2 ? mid - width + 24 : mid - 24;
        const left = Math.min(Math.max(8, want), h.width - width - 8);
        bubble.classList.toggle('is-right', want === mid - 24);
        bubble.style.left = `${left}px`;
        // No room above (on a phone it sits in the top corner): open underneath instead.
        const below = m.top - h.top < bubble.offsetHeight + 12;
        bubble.style.top = below ? `${m.bottom - h.top + 4}px` : '';
        bubble.style.bottom = below ? '' : `${Math.max(8, h.bottom - m.top + 4)}px`;
    }

    const duck = (down) => { ducked = down; mon.classList.toggle('is-ducked', down); };

    // A free spot along the bottom edge: nothing readable underneath it.
    function freeSpots() {
        const h = hero.getBoundingClientRect(), m = mon.getBoundingClientRect();
        const block = [...hero.querySelectorAll('.vsh-hero-date, .vsh-hero h1, .vsh-hero-copy > p, .vsh-next')].map((el) => {
            if (el.classList.contains('vsh-next')) return el.getBoundingClientRect();
            const r = document.createRange(); r.selectNodeContents(el); return r.getBoundingClientRect();
        });
        const top = h.bottom - m.height, spots = [];
        const home = h.width - parseFloat(getComputedStyle(mon).right || 0) - m.width; // where CSS puts it
        for (let x = 16; x <= h.width - m.width - 12; x += 24) {
            const l = h.left + x, r = l + m.width;
            if (block.every((b) => r + 10 < b.left || l - 10 > b.right || top > b.bottom + 4)) spots.push(x - home);
        }
        return spots;
    }

    function hop() {
        if (busy) return;
        const here = parseFloat(mon.style.getPropertyValue('--bx')) || 0;
        const spots = freeSpots().filter((s) => Math.abs(s - here) > 90);
        if (calm.matches || !spots.length) { play('is-hop', 600); return; }
        busy = true;
        duck(true);
        later(() => {
            mon.classList.add('is-moving');
            mon.style.setProperty('--bx', `${spots[Math.floor(Math.random() * spots.length)]}px`);
            later(() => { mon.classList.remove('is-moving'); duck(false); later(() => { busy = false; place(); }, 380); }, 60);
        }, 380);
    }

    // ── Look: lean toward the cursor, perk up when it arrives ──
    const look = (e) => {
        if (!finePointer.matches || ducked) return;
        const m = mon.getBoundingClientRect();
        const dx = e.clientX - (m.left + m.width / 2), dy = e.clientY - (m.top + m.height / 3);
        const reach = Math.max(1, Math.hypot(dx, dy));
        mon.style.setProperty('--look-x', `${(dx / reach) * 3}px`);
        mon.style.setProperty('--look-r', `${Math.max(-9, Math.min(9, (dx / reach) * 9 * Math.min(1, reach / 260)))}deg`);
        mon.classList.toggle('is-facing', dx > 24); // most sprites face left; turn round when you're to their right
        mon.classList.toggle('is-watching', !!e.target.closest?.('.vsh-next'));
    };
    const unlook = () => { mon.style.removeProperty('--look-x'); mon.style.removeProperty('--look-r'); mon.classList.remove('is-facing', 'is-watching'); };
    const perk = () => { if (!busy && !ducked) play('is-perk', 500); };

    const click = (e) => {
        e.preventDefault();
        wake();
        if (busy) return;
        say(greeting(name), 2600);
        later(hop, 2900);
    };

    // ── Idle: duck after a quiet spell, peek now and then, stretch on return ──
    function goIdle() {
        if (away || busy) { idleTimer = later(goIdle, IDLE_MS); return; }
        idle = true; duck(true);
        const peek = () => {
            if (!idle || away) return;
            mon.classList.add('is-peeking');
            later(() => mon.classList.remove('is-peeking'), 1600);
            peekTimer = later(peek, PEEK_MS);
        };
        peekTimer = later(peek, PEEK_MS / 2);
    }
    function wake() {
        clearTimeout(idleTimer); clearTimeout(peekTimer);
        if (idle) {
            idle = false; mon.classList.remove('is-peeking');
            if (!away) { duck(false); later(() => play('is-stretch', 900), 300); }
        }
        idleTimer = later(goIdle, IDLE_MS);
    }
    let lastWake = 0;
    const activity = () => { const now = Date.now(); if (idle || now - lastWake > 2000) { lastWake = now; wake(); } };

    // ── Scroll: duck while the banner is out of view ──
    const seen = new IntersectionObserver(([entry]) => {
        const gone = entry.intersectionRatio < 0.35;
        if (gone === away) return;
        away = gone;
        if (away) duck(true);
        else if (!idle) { duck(false); later(() => play('is-hop', 600), 320); }
    }, { threshold: [0, 0.35, 1] });
    seen.observe(hero);

    // ── Show day: excited once when the next show is 15 minutes out ──
    const at = Number(mon.dataset.showAt || 0);
    const soonKey = `vx-buddy-soon-${mon.dataset.showId || at}`;
    const checkSoon = () => {
        const left = at - Date.now();
        mon.classList.toggle('is-excited', at > 0 && left > 0 && left <= SOON_MS);
        let told = false; try { told = !!sessionStorage.getItem(soonKey); } catch {}
        if (at > 0 && left > 0 && left <= SOON_MS && !told) {
            try { sessionStorage.setItem(soonKey, '1'); } catch {}
            wake();
            const mins = Math.max(1, Math.round(left / 60_000));
            say(`Your show starts in ${mins} minute${mins === 1 ? '' : 's'}!`, 6500);
            play('is-cheer', 1500);
        }
    };
    const soonTimer = at ? setInterval(checkSoon, 20_000) : 0;

    // ── Clock in/out went through ──
    const clocked = () => { wake(); play('is-cheer', 1500); };

    const events = [
        [hero, 'pointermove', look], [hero, 'pointerleave', unlook], [mon, 'pointerenter', perk], [mon, 'click', click],
        [window, 'clock-changed', clocked], [window, 'resize', place],
        ...['pointerdown', 'keydown', 'scroll', 'pointermove'].map((t) => [window, t, activity]),
    ];
    events.forEach(([el, t, fn]) => el.addEventListener(t, fn, { passive: t !== 'click' }));

    // Arrive: a little wiggle hello, then the regular routine.
    later(() => { play(mon.dataset.mood === 'sleepy' ? 'is-stretch' : 'is-wiggle', 1000); checkSoon(); }, 700);
    idleTimer = later(goIdle, IDLE_MS);

    return {
        mon,
        stop() {
            timers.forEach(clearTimeout); clearInterval(soonTimer); seen.disconnect();
            events.forEach(([el, t, fn]) => el.removeEventListener(t, fn));
        },
    };
}

function boot() {
    const mon = document.querySelector('.vsh-hero-mon[data-buddy]');
    if (current?.mon === mon && mon?.isConnected) return;
    current?.stop();
    current = mon ? setup(mon) : null;
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
document.addEventListener('livewire:navigated', boot);
document.addEventListener('livewire:init', () => window.Livewire?.hook?.('morph.updated', () => queueMicrotask(boot)));
