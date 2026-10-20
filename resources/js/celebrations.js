// Celebrations: a Pokémon pops up for about a second when someone gets
// something done. No server work and nothing loaded up front: the page's
// Livewire traffic already says which button was pressed (the Filament action
// or component method), so when that action finishes cleanly (its modal
// closed, no validation errors, no red or amber toast) the matching moment
// plays. A sprite is fetched only when it is about to be shown.
//
// Milestones (first report, record show, payday, pallet complete, everyone
// paid) are sent by the server as `vx-fx` events and get a couple of seconds.
//
// Each person chooses All / Milestones only / Off on their profile; reduced
// motion gets a still card instead of animation. Click a milestone or press
// Esc to skip. During a catch event (App\Support\Season::EVENTS) some
// Pokémon are swapped for the season's: Gengar instead of Machamp in October.
import '../css/celebrations.css';

const calm = window.matchMedia('(prefers-reduced-motion: reduce)');

// ── The person's level and the running event, from the page ──────────────
function config() {
    try { return JSON.parse(document.getElementById('vx-fx')?.textContent || '{}'); } catch { return {}; }
}
/** 'all' | 'milestones' | 'off' */
export function fxLevel() { return config().level || 'all'; }
export const allowSmall = () => fxLevel() === 'all';
export const allowBig = () => fxLevel() !== 'off';
function seasonal(mon) { return config().event?.swap?.[mon] || mon; }

// ── What plays for each kind of moment ───────────────────────────────────
// [Pokémon, motion, speech, icon]. Motions: hop, stamp, coins, sparkle,
// teleport, fly, carry, spin, scan.
const FX = {
    // Streamers
    stream_link: ['porygon', 'spin', 'Link saved!', '🔗'],
    attachments: ['smeargle', 'hop', 'Snap!', '📸'],
    review: ['chansey', 'stamp', 'Looks good!'],
    ack_revision: ['slowpoke', 'hop', 'Got it…'],
    ask: ['psyduck', 'hop', 'Psy…? Request sent!'],
    deduction: ['meowth', 'coins', 'Request sent!'],
    packet: ['kangaskhan', 'carry', 'Packet in!', '✉️'],
    missing: ['growlithe', 'hop', 'On the case!', '❗'],
    feedback: ['chatot', 'hop', 'Thanks!', '🎵'],
    profile: ['bronzong', 'hop', 'Saved!', '🔒'],
    // Shows
    show_added: ['hoothoot', 'hop', 'Scheduled!', '📌'],
    streamers_assigned: ['machoke', 'hop', 'Team set!', '🙌'],
    channel_confirmed: ['magnemite', 'spin', 'Locked in!'],
    detected: ['noctowl', 'sparkle', 'Found them!'],
    suggestion: ['alakazam', 'sparkle', 'Nice match!'],
    approved: ['chansey', 'stamp', 'APPROVED'],
    approved_many: ['chansey', 'stamp', 'ALL APPROVED'],
    sent_back: ['slowbro', 'hop', 'Sent back'],
    reopened: ['abra', 'teleport', 'Reopened'],
    fulfillment_reviewed: ['machamp', 'carry', 'Reviewed!', '📦'],
    show_closed: ['kadabra', 'teleport', "That's a wrap!", '🎬'],
    show_cancelled: ['jigglypuff', 'hop', 'Cancelled', '💤'],
    flag_cleared: ['swablu', 'fly', 'All clear!', '🚩'],
    item_matched: ['ditto', 'spin', 'Matched!'],
    titles_tagged: ['unown', 'hop', 'Tagged!', '🏷️'],
    // Receiving and inventory
    pallet_created: ['snorlax', 'hop', 'Pallet in!', '📦'],
    lines_added: ['bidoof', 'hop', 'Stacked!', '📦'],
    mapped: ['ditto', 'spin', 'Mapped!'],
    received: ['machamp', 'carry', 'Received!', '📦'],
    container_broken: ['onix', 'hop', 'Cracked open!', '📦'],
    costs: ['meowth', 'coins', 'Costs in!'],
    short: ['psyduck', 'hop', 'Noted short'],
    archived: ['slowking', 'hop', 'Filed away', '🗂️'],
    restored: ['celebi', 'teleport', 'Restored!'],
    stock_added: ['bidoof', 'hop', 'Stock added!', '📦'],
    item_created: ['bidoof', 'hop', 'New item!', '✨'],
    transferred: ['abra', 'teleport', 'Moved!', '📦'],
    inventory_setup: ['sableye', 'sparkle', 'Stocked up!', '💎'],
    storage_setup: ['sudowoodo', 'hop', 'Shelf built!', '🪵'],
    count_saved: ['alakazam', 'sparkle', 'Counted!'],
    damaged: ['voltorb', 'hop', 'Logged'],
    returns: ['wingull', 'fly', 'Off to returns', '📦'],
    sku: ['porygon', 'spin', 'New SKU!'],
    merged: ['ditto', 'spin', 'Merged!'],
    sheet_imported: ['ditto', 'sparkle', 'Imported!'],
    labels: ['magnemite', 'hop', 'Printing!', '🏷️'],
    reconciled: ['bronzong', 'hop', 'Balanced!', '⚖️'],
    // Fulfillment
    package_created: ['bidoof', 'hop', 'Boxed!', '📦'],
    package_sealed: ['pelipper', 'fly', 'Off it goes!', '📦'],
    fulfillment_done: ['pelipper', 'fly', 'All packed!', '📦'],
    assigned: ['machoke', 'hop', 'Handed off!'],
    resolved: ['growlithe', 'hop', 'Found it!'],
    // Payroll
    run_created: ['kangaskhan', 'hop', 'New pay run!', '🗓️'],
    validated: ['alakazam', 'sparkle', 'All good!'],
    recalculated: ['porygon', 'spin', 'Recalculated!'],
    adjusted: ['meowth', 'coins', 'Adjusted'],
    rates_saved: ['bronzong', 'hop', 'Rates locked in!'],
    finalized: ['kangaskhan', 'stamp', 'FINAL'],
    paid: ['meowth', 'coins', 'Paid!'],
    payment_recorded: ['meowth', 'coins', 'Payment in!'],
    packet_approved: ['kangaskhan', 'stamp', 'APPROVED'],
    // Report filed on time (within two hours of the show ending)
    on_time: ['pidgey', 'fly', 'On time!', '✉️'],
    // Barcode scans: a Poké Ball ding, with a combo every ten
    scan: [null, 'scan'],
};

// Milestones: [Pokémon, title, line]. A line can use {name} / {amount}.
const BIG = {
    first_report: ['charmander', 'Your journey begins!', 'First report filed. Your Pokédex is open.', ['bulbasaur', 'squirtle']],
    record_show: ['charizard', 'NEW RECORD!', 'Your best show yet: {amount}'],
    payday: ['meowth', 'Pay Day!', '{amount} is on its way to you'],
    pallet_complete: ['machamp', 'Pallet complete!', 'Every case is in.'],
    all_paid: ['pikachu', "Everyone's paid!", 'Every pay run this week is paid.'],
    new_streamer: [null, 'Welcome{name}!', 'Added to the team.'],
};

// ── Which button means which moment ──────────────────────────────────────
// Filament action names and Livewire method names, as the page sends them.
const NAMES = {
    // streamers
    saveLink: 'stream_link', add_attachments: 'attachments', submitStreamerReview: 'review',
    acknowledge_revision: 'ack_revision', request_edit: 'ask', submitPacket: 'packet', savePacket: 'packet',
    // shows
    schedule_show: 'show_added', addShow: 'show_added', createShow: 'show_added',
    assign_streamers: 'streamers_assigned', confirm_channel: 'channel_confirmed',
    detect_streamer: 'detected', detect_streamers: 'detected', detect_streamers_all: 'detected',
    accept_suggestion: 'suggestion', confirm_high_confidence: 'suggestion',
    approve: 'approved', approve_report: 'approved', admin_approve: 'approved', fast_approve: 'approved', approve_edit_request: 'approved',
    bulk_admin_approve: 'approved_many',
    reject: 'sent_back', reject_report: 'sent_back', send_back: 'sent_back', bulk_send_back: 'sent_back', decline_edit_request: 'sent_back', needs_info: 'sent_back',
    reopen_for_edit: 'reopened', reopen: 'reopened',
    mark_fulfillment_reviewed: 'fulfillment_reviewed', close_show: 'show_closed', cancel_show: 'show_cancelled',
    clear_revision_flag: 'flag_cleared', clear_attribution_flag: 'flag_cleared', tag_titles: 'titles_tagged',
    // receiving and inventory
    add_lines: 'lines_added', add_items: 'lines_added',
    map_line: 'mapped', quick_map: 'mapped', linkLine: 'mapped',
    receive: 'received', receive_all: 'received', receive_now: 'received', review_and_receive: 'received',
    receiveLine: 'received', receivePartialLine: 'received', receiveAllOfLine: 'received', receiveSomeOfLine: 'received', bulkReceivePallet: 'received',
    break_container: 'container_broken', fill_costs: 'costs', edit_costs: 'costs', applyCostAdjust: 'costs',
    close_short: 'short', markLineAsShort: 'short', markLineShort: 'short',
    archive: 'archived', archive_received_pallet: 'archived',
    restore: 'restored', restore_item: 'restored', restore_selected: 'restored',
    add_stock: 'stock_added', addStock: 'stock_added', applyAdjust: 'stock_added',
    executeTransfer: 'transferred', setUpInventory: 'inventory_setup', streamerLocation: 'inventory_setup', businessStorage: 'storage_setup',
    saveCount: 'count_saved', saveAll: 'count_saved', update_counts: 'count_saved',
    mark_damaged: 'damaged', move_to_returns: 'returns', regenerate_sku: 'sku',
    reassign_to_product: 'merged', merge: 'merged', generateLabels: 'labels', mark_reconciled: 'reconciled',
    complete_pallet: 'pallet_complete', finalizePallet: 'pallet_complete',
    // scans
    submitScan: 'scan', submitBarcode: 'scan', barcodeScanned: 'scan', handleBarcodeScan: 'scan', scanBarcode: 'scan', submitManual: 'scan', scan: 'scan', scanItem: 'scan', packOne: 'scan',
    // fulfillment
    createPackage: 'package_created', createPackageFromShipment: 'package_created', addPackageFromShipment: 'package_created',
    sealPackage: 'package_sealed', completeFulfillment: 'fulfillment_done', packRemaining: 'fulfillment_done',
    assign_fulfillment: 'assigned', mark_resolved: 'resolved',
    // payroll
    new_pay_run: 'run_created', validate_run: 'validated', recalculate: 'recalculated',
    override_payout: 'adjusted', adjust_balance: 'adjusted', save_rates: 'rates_saved',
    finalize: 'finalized', mark_paid: 'paid', record_payment: 'payment_recorded', approvePacket: 'packet_approved',
};

// Names that mean different things on different pages: [path, name, moment].
const ON_PAGE = [
    [/\/edit-profile/, 'save', 'profile'],
    [/\/payment-structures/, 'save', 'rates_saved'],
    [/\/quick-add-container-scan/, 'save', 'stock_added'],
    [/\/import-inventory-sheet/, 'apply', 'sheet_imported'],
    [/\/import-inventory-sheet/, 'import', 'sheet_imported'],
    [/\/pallets\/create/, 'create', 'pallet_created'],
    [/\/shows\/create/, 'create', 'show_added'],
    [/\/streamers\/create/, 'create', 'new_streamer'],
    [/\/inventory-items\/create/, 'create', 'item_created'],
    [/\/inventory-locations\/create/, 'create', 'storage_setup'],
    [/\/deduction-requests\/create/, 'create', 'deduction'],
    [/\/missing-item-reports\/create/, 'create', 'missing'],
    [/\/weekly-payout-batches\/create/, 'create', 'run_created'],
];
// One component's own methods: [Livewire component name, method, moment].
const ON_COMPONENT = [
    [/feedback-widget/, 'submit', 'feedback'],
];
// Whatnot syncing and owner/system pages stay quiet.
const QUIET = /\/(whatnot|app-settings|system-health|users|roles|feature-flags|log-viewer|activity-logs|horizon|ai-|demo-data|navigation-manager|email-log|import-status|pay-run-backfill|show-data-audit|notification-settings|businesses)/;

function momentFor(name, componentName) {
    const path = location.pathname;
    if (QUIET.test(path)) return null;
    for (const [re, n, kind] of ON_COMPONENT) if (n === name && re.test(componentName || '')) return kind;
    for (const [re, n, kind] of ON_PAGE) if (n === name && re.test(path)) return kind;
    return NAMES[name] || null;
}

// ── Where it pops: by the button that was pressed ────────────────────────
let point = null, pointAt = 0;
document.addEventListener('pointerdown', (e) => { point = { x: e.clientX, y: e.clientY }; pointAt = Date.now(); }, { capture: true, passive: true });
function anchor() {
    const w = innerWidth, h = innerHeight;
    let { x, y } = point && Date.now() - pointAt < 8000 ? point : { x: w / 2, y: h - 120 };
    const field = document.activeElement?.matches?.('input, textarea') ? document.activeElement.getBoundingClientRect() : null;
    if (field && Date.now() - pointAt > 1500) { x = field.right - 30; y = field.top; }
    return { x: Math.min(w - 70, Math.max(70, x)), y: Math.min(h - 40, Math.max(110, y)) };
}

function img(species, cls) {
    const el = new Image();
    el.className = cls; el.alt = ''; el.decoding = 'async';
    el.src = `/img/pokemon3d/${Math.random() < 0.02 ? 'shiny/' : ''}${species}.gif`;
    return el;
}
function el(tag, cls, text) {
    const n = document.createElement(tag);
    n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
}

// ── Small moments: about a second ────────────────────────────────────────
let combo = 0, comboAt = 0;
function small(kind, extra = {}) {
    if (!allowSmall()) return;
    const fx = FX[kind];
    if (!fx) return;
    if (document.querySelector('.vx-fx-big') || (kind === 'paid' && document.querySelector('.vx-pika'))) return; // something bigger is already playing
    const [mon, motion, say, icon] = fx;
    if (motion === 'scan') return scanDing();
    const { x, y } = anchor();
    const box = el('div', `vx-fx vx-fx--${motion}${calm.matches ? ' is-calm' : ''}`);
    box.setAttribute('aria-hidden', 'true');
    box.style.left = `${x}px`; box.style.top = `${y}px`;
    const mo = img(seasonal(mon), 'vx-fx-mon');
    box.append(mo);
    if (icon) box.append(el('i', 'vx-fx-icon', icon));
    if (motion === 'coins') for (let i = 0; i < 5; i++) { const c = el('i', 'vx-fx-coin', '🪙'); c.style.setProperty('--i', i); box.append(c); }
    if (motion === 'sparkle') for (let i = 0; i < 3; i++) { const s = el('i', 'vx-fx-spark', '✨'); s.style.setProperty('--i', i); box.append(s); }
    if (motion === 'teleport') box.append(el('i', 'vx-fx-ring'));
    if (say) box.append(el('b', motion === 'stamp' ? 'vx-fx-stamp' : 'vx-fx-say', extra.say || say));
    const ev = config().event;
    if (ev && motion === 'stamp') box.append(el('i', 'vx-fx-season', ev.emoji));
    // Shown once the sprite is ready, so nothing blinks in half-drawn.
    const show = () => { if (!box.isConnected) { document.body.append(box); setTimeout(() => box.remove(), calm.matches ? 1400 : 1250); } };
    mo.decode?.().then(show, show) ?? show();
    setTimeout(show, 250);
}

function scanDing() {
    const now = Date.now();
    combo = now - comboAt < 5000 ? combo + 1 : 1;
    comboAt = now;
    if (calm.matches) return;
    const { x, y } = anchor();
    const box = el('div', 'vx-fx vx-fx--scan');
    box.setAttribute('aria-hidden', 'true');
    box.style.left = `${x}px`; box.style.top = `${y}px`;
    box.append(el('span', 'vx-fx-ball'));
    if (combo > 1) box.append(el('b', 'vx-fx-combo', `×${combo}`));
    document.body.append(box);
    setTimeout(() => box.remove(), 800);
    if (combo % 10 === 0) {
        const pool = config().event?.pool;
        const mon = pool?.length ? pool[Math.floor(Math.random() * pool.length)] : 'pikachu';
        const pop = el('div', 'vx-fx vx-fx--hop');
        pop.setAttribute('aria-hidden', 'true');
        pop.style.left = `${x}px`; pop.style.top = `${y - 10}px`;
        pop.append(img(mon, 'vx-fx-mon'), el('b', 'vx-fx-say', `${combo} in a row!`));
        document.body.append(pop);
        setTimeout(() => pop.remove(), 1250);
    }
}

// ── Milestones: a couple of seconds, at most once a day each ─────────────
// A keyed one (a payday, a record show) plays once ever; the rest once a day.
function once(key, forever = false) {
    try {
        const k = `vx-fx-big:${key}`, mark = forever ? 'done' : new Date().toDateString();
        if (localStorage.getItem(k) === mark) return false;
        localStorage.setItem(k, mark);
    } catch {}
    return true;
}
function big(kind, extra = {}) {
    if (!allowBig()) return;
    const fx = BIG[kind];
    if (!fx || !once(kind + (extra.key ? `:${extra.key}` : ''), !!extra.key)) return;
    document.querySelector('.vx-fx-big')?.remove();
    const [mon, title, line, friends] = fx;
    const fill = (t) => t.replace('{name}', extra.name ? `, ${extra.name}` : '').replace('{amount}', extra.amount || '');
    const box = el('div', `vx-fx-big vx-fx-big--${kind}${calm.matches ? ' is-calm' : ''}`);
    box.setAttribute('role', 'status');
    const stage = el('div', 'vx-fx-big-stage');
    if (mon) {
        stage.append(img(seasonal(mon), 'vx-fx-big-mon'));
        (friends || []).forEach((f) => stage.append(img(f, 'vx-fx-big-mon is-friend')));
    } else {
        stage.append(el('span', 'vx-fx-ball vx-fx-ball--big'));
    }
    const text = el('div', 'vx-fx-big-text');
    text.append(el('b', '', fill(title)), el('span', '', fill(line)));
    box.append(stage, text);
    const close = () => { box.classList.add('is-out'); setTimeout(() => box.remove(), 250); };
    box.addEventListener('click', close);
    document.body.append(box);
    if (!calm.matches) burst(box);
    setTimeout(close, calm.matches ? 2600 : 2300);
}
function burst(host) {
    const colours = ['#7c3aed', '#a78bfa', '#f59e0b', '#c026d3', '#38bdf8', '#f472b6'];
    const ev = config().event?.key;
    if (ev === 'halloween') colours.splice(0, 3, '#f97316', '#7c3aed', '#22c55e');
    for (let i = 0; i < 22; i++) {
        const p = el('i', 'vx-fx-bit');
        const a = (i / 22) * Math.PI * 2, d = 90 + (i % 4) * 30;
        p.style.setProperty('--dx', `${Math.cos(a) * d}px`);
        p.style.setProperty('--dy', `${Math.sin(a) * d * 0.7}px`);
        p.style.background = colours[i % colours.length];
        host.append(p);
    }
}

export function play(kind, extra = {}) {
    if (BIG[kind]) big(kind, extra); else small(kind, extra);
}
window.vxFx = play;
window.addEventListener('vx-fx', (e) => {
    const d = Array.isArray(e.detail) ? e.detail[0] : e.detail;
    if (d?.kind) play(d.kind, d);
});
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && e.isTrusted) document.querySelectorAll('.vx-fx, .vx-fx-big').forEach((n) => n.remove());
});

// ── Spotting a finished action in the page's own Livewire traffic ────────
let badToastAt = 0;
new MutationObserver((list) => {
    for (const m of list) for (const n of m.addedNodes) {
        if (n instanceof Element && (n.matches('.fi-no-notification.fi-status-danger, .fi-no-notification.fi-status-warning') || n.querySelector?.('.fi-no-notification.fi-status-danger, .fi-no-notification.fi-status-warning'))) {
            badToastAt = Date.now();
            document.querySelectorAll('.vx-fx').forEach((x) => x.remove()); // it didn't go well after all
        }
    }
}).observe(document.documentElement, { childList: true, subtree: true });

const PENDING = 'vx-fx-pending';
function mounted(component) {
    const list = component?.ephemeral?.mountedActions;
    return Array.isArray(list) ? list : [];
}
document.addEventListener('livewire:init', () => {
    window.Livewire?.hook?.('commit', ({ component, commit, succeed }) => {
        const calls = commit?.calls || [];
        if (!calls.length) return;
        const before = mounted(component);
        let name = null, viaModal = false;
        for (const c of calls) {
            if (c.method === 'mountAction' && typeof c.params?.[0] === 'string') name = c.params[0];
            else if (c.method === 'callMountedAction') { name = before[before.length - 1]?.name || name; viaModal = true; }
            else if (!/^[$_]/.test(c.method) && !['mountAction', 'unmountAction'].includes(c.method)) name = c.method;
        }
        const kind = name && momentFor(name, component?.name);
        if (!kind) return;
        const started = Date.now();
        succeed(({ snapshot, effects }) => {
            let snap = snapshot;
            if (typeof snap === 'string') { try { snap = JSON.parse(snap); } catch { snap = null; } }
            const errors = snap?.memo?.errors;
            if (errors && (Array.isArray(errors) ? errors.length : Object.keys(errors).length)) return;
            const after = mounted(component).length;
            if (viaModal ? after >= before.length : after > before.length) return; // the modal is still open
            const fire = () => { if (badToastAt < started) play(kind); };
            if (effects?.redirect) {
                try { sessionStorage.setItem(PENDING, JSON.stringify({ kind, at: Date.now() })); } catch {}
                return;
            }
            setTimeout(fire, 380);
        });
    });
});

// Server-flashed milestones, and a moment carried over a redirect.
function boot() {
    const node = document.getElementById('vx-fx');
    const flashed = config().flash;
    if (node && !node.dataset.played) {
        node.dataset.played = '1';
        // After the "Report submitted" ball and the catch, not on top of them.
        if (flashed?.kind) afterOthers(() => play(flashed.kind, flashed));
        const m = config().milestone;
        if (m?.kind) afterOthers(() => play(m.kind, m), 1200);
    }
    try {
        const p = JSON.parse(sessionStorage.getItem(PENDING) || 'null');
        sessionStorage.removeItem(PENDING);
        if (p?.kind && Date.now() - p.at < 15000) setTimeout(() => { if (!document.querySelector('.fi-no-notification.fi-status-danger')) play(p.kind); }, 450);
    } catch {}
    const ev = config().event?.key;
    if (ev) document.documentElement.dataset.vxEvent = ev; else delete document.documentElement.dataset.vxEvent;
}

function afterOthers(fn, wait = 450) {
    let tries = 0;
    const tick = () => {
        if (document.querySelector('body > .vx-gotcha, .vx-done, .vx-catch, .vx-fx-big') && tries++ < 30) return setTimeout(tick, 350);
        fn();
    };
    setTimeout(tick, wait);
}

if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot, { once: true }); else boot();
document.addEventListener('livewire:navigated', boot);
