// Fulfillment live updates. Sections marked data-vxf-pulse redraw when something
// on the floor changes, instead of on a fixed timer. Every few seconds we ask
// /fulfillment/pulse for one small version string (a cache read on the server);
// when it moves we fire a window event the sections listen for:
//
//   <div data-vxf-pulse x-on:vxf-pulse.window="vxfPulseRefresh($el, $wire)">
//
// Inside a Livewire island, pass its name so only that island redraws (a plain
// $refresh re-renders the page and skips islands):
//
//   x-on:vxf-pulse.window="vxfPulseRefresh($el, $wire, 'queue')"
//
// Only while the tab is visible and such a section is on the page.
(() => {
    if (window.vxfPulse) return;
    let version = null, timer = null, busy = false, failures = 0;
    const EVERY = 4000;

    async function tick() {
        timer = null;
        if (document.hidden || !document.querySelector('[data-vxf-pulse]')) return schedule(EVERY * 2);
        if (busy) return schedule(EVERY);
        busy = true;
        try {
            const r = await fetch('/fulfillment/pulse', { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store' });
            if (!r.ok) throw new Error('HTTP ' + r.status);
            const { v } = await r.json();
            if (version !== null && v !== version && v !== '0') window.dispatchEvent(new CustomEvent('vxf-pulse', { detail: { v } }));
            version = v;
            failures = 0;
        } catch (e) {
            failures++;
        }
        busy = false;
        schedule(failures ? Math.min(60000, EVERY * 2 ** failures) : EVERY);
    }
    function schedule(ms) { if (!timer) timer = setTimeout(tick, ms); }

    // Redraw a section unless someone is typing in it right now.
    window.vxfPulseRefresh = (el, wire, island) => {
        const a = document.activeElement;
        if (!wire || (a && el.contains(a) && a.matches('input:not([type=checkbox]):not([type=radio]),textarea') && a.value)) return;
        if (island) wire.$island(island);
        wire.$refresh();
    };

    window.vxfPulse = { now: () => { clearTimeout(timer); timer = null; tick(); } };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) window.vxfPulse.now(); });
    document.addEventListener('livewire:navigated', () => schedule(500));
    schedule(1500);
})();
