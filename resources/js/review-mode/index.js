import * as fabricModule from 'fabric';
const fabric = fabricModule.fabric ?? fabricModule.default?.fabric ?? fabricModule.default ?? fabricModule;
import html2canvas from 'html2canvas';

// ── State ──────────────────────────────────────────────────────────────────────
let reviewModeActive    = false;
let canvasActive        = false;
let sessionId           = null;
let projectId           = null;
let hasUploadedImage    = false;
let browsingExpanded    = false;
let pageScreenshotDataUrl = null;  // captured once when canvas opens

let fabricCanvas        = null;
let overlayEl           = null;
let toolbarEl           = null;
let browsingChip        = null;
let browsingPopover     = null;
let pickerActive        = false;
let pickerBoxEl         = null;
let pickerMode          = 'annotate';
let seedAnnotationPoint = null;

let currentTool    = 'select';
let currentColor   = '#e11d48';
let strokeWidth    = 3;
let history        = [];
let mouseDownPt    = null;
let tempShape      = null;
let isDrawingRect  = false;
let isDrawingArrow = false;

// ── Design tokens (match app.css premium theme) ────────────────────────────────
const T = {
    bg:        '#ffffff',
    bgDark:    '#111318',
    border:    'rgb(0 0 0 / .09)',
    borderDark:'rgb(255 255 255 / .1)',
    text:      '#111318',
    muted:     '#6b7280',
    subtle:    '#9ca3af',
    accent:    '#29e7e7',
    accentSoft:'rgba(41,231,231,.12)',
    accentBorder:'rgba(41,231,231,.28)',
    danger:    '#dc2626',
    success:   '#16a34a',
    radius:    '10px',
    radiusLg:  '14px',
    shadow:    '0 8px 24px rgba(0,0,0,.14),0 1px 3px rgba(0,0,0,.08)',
    shadowLg:  '0 20px 48px rgba(0,0,0,.20),0 4px 12px rgba(0,0,0,.10)',
    transition:'all 0.14s ease',
    font:      "'Inter',system-ui,sans-serif",
};

function currentReviewUrl() {
    return `${window.location.origin}${window.location.pathname}${window.location.search}`;
}

function normalizeSessionId(value) {
    const raw = typeof value === 'string' ? value.trim() : String(value ?? '').trim();

    if (!raw || raw === 'null' || raw === 'undefined') {
        return null;
    }

    const numeric = Number.parseInt(raw, 10);

    return Number.isFinite(numeric) && numeric > 0 ? String(numeric) : null;
}

// SVG icon helper ───────────────────────────────────────────────────────────────
const svg = (path, size = 16, extra = '') =>
    `<svg xmlns="http://www.w3.org/2000/svg" width="${size}" height="${size}" viewBox="0 0 24 24"
     fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
     stroke-linejoin="round" ${extra}>${path}</svg>`;

const ICONS = {
    review:  svg('<path d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5"/><path d="M17.5 3.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 7.5-7.5z"/>'),
    exit:    svg('<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>'),
    cursor:  svg('<path d="M5 3l14 9-7 1-4 7-3-17z"/>'),
    pen:     svg('<path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/>'),
    box:     svg('<rect x="3" y="3" width="18" height="18" rx="2"/>'),
    arrow:   svg('<line x1="5" y1="19" x2="19" y2="5"/><polyline points="8 5 19 5 19 16"/>'),
    text:    svg('<polyline points="4 7 4 4 20 4 20 7"/><line x1="9" y1="20" x2="15" y2="20"/><line x1="12" y1="4" x2="12" y2="20"/>'),
    image:   svg('<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/>'),
    undo:    svg('<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 5.5 5.5v0a5.5 5.5 0 0 1-5.5 5.5H11"/>'),
    trash:   svg('<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>'),
    save:    svg('<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>'),
    pin:     svg('<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>'),
    note:    svg('<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>'),
    expand:  svg('<polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/>'),
    thin:    svg('<line x1="6" y1="12" x2="18" y2="12" stroke-width="2"/>'),
    medium:  svg('<line x1="6" y1="12" x2="18" y2="12" stroke-width="4"/>'),
    thick:   svg('<line x1="6" y1="12" x2="18" y2="12" stroke-width="6"/>'),
};

// ── Init ───────────────────────────────────────────────────────────────────────
function isReviewModuleEnabled() {
    const modules = window.VortexModules ?? {};

    return (modules.reviews ?? true) && (modules.showReviewButton ?? true);
}

function moveFabToDockIfAvailable() {
    const wrap = document.getElementById('review-fab-wrap');
    const dock = document.getElementById('vortexops-top-actions');

    if (!wrap || !dock || wrap.parentElement === dock) {
        return;
    }

    wrap.style.order = '-1';
    dock.insertBefore(wrap, dock.firstChild);
}

function initializeReviewUi() {
    if (!isReviewModuleEnabled()) {
        document.getElementById('review-fab-wrap')?.remove();
        removeBrowsingUI();
        reviewModeActive = false;
        return;
    }

    sessionId = normalizeSessionId(localStorage.getItem('vortex_review_session_id'));
    projectId = localStorage.getItem('vortex_project_id') || null;
    localStorage.removeItem('vortex_review_fab_minimized');

    if (!document.getElementById('review-fab-wrap')) {
        injectFab();
    } else {
        moveFabToDockIfAvailable();
        updateFabState();
    }

    if (localStorage.getItem('vortex_review_active') === '1') {
        reviewModeActive = true;
        updateFabState();

        if (!document.getElementById('review-chip')) {
            showBrowsingUI();
        }
    } else {
        reviewModeActive = false;
        removeBrowsingUI();
        updateFabState();
    }
}

function scheduleReviewUiInit() {
    window.requestAnimationFrame(() => {
        initializeReviewUi();

        window.setTimeout(() => {
            moveFabToDockIfAvailable();
            updateFabState();
        }, 40);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', scheduleReviewUiInit);
} else {
    scheduleReviewUiInit();
}

document.addEventListener('livewire:navigated', scheduleReviewUiInit);

async function ensureSessionSelected() {
    if (sessionId) {
        return sessionId;
    }

    try {
        const response = await fetch('/admin/review/sessions', {
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const sessions = await response.json();
            const latest = Array.isArray(sessions) ? sessions[0] : null;

            if (latest?.id) {
                sessionId = normalizeSessionId(latest.id);

                if (!sessionId) {
                    return null;
                }

                localStorage.setItem('vortex_review_session_id', sessionId);
                loadSessionChipLabel();

                return sessionId;
            }
        }
    } catch { /* ignore */ }

    return null;
}

// ── FAB ────────────────────────────────────────────────────────────────────────
function injectFab() {
    const dock = document.getElementById('vortexops-top-actions');
    const wrap = document.createElement('div');
    wrap.id = 'review-fab-wrap';
    Object.assign(wrap.style, dock ? {
        position: 'relative',
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        order: '-1',
    } : {
        position: 'fixed',
        top: '0.875rem',
        right: '10.5rem',
        zIndex: '46',
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
    });
    wrap.innerHTML = `
        <button id="review-toggle-btn" title="Review Mode"
            style="display:flex;align-items:center;gap:7px;
                   padding:0.38rem 0.72rem;border:1px solid rgba(148,163,184,.24);border-radius:999px;
                   background:rgba(255,255,255,0.94);color:#334155;
                   font-family:${T.font};font-size:0.74rem;font-weight:700;
                   letter-spacing:-0.01em;cursor:pointer;
                   backdrop-filter:blur(10px);
                   box-shadow:0 1px 6px rgba(0,0,0,0.10);
                   transition:${T.transition};">
            <span id="review-fab-icon" style="display:flex;align-items:center;flex-shrink:0;opacity:.9;">
                ${ICONS.review}
            </span>
            <span id="review-fab-label">Review</span>
        </button>`;
    (dock ?? document.body).appendChild(wrap);
    wrap.querySelector('#review-toggle-btn')?.addEventListener('click', handleFabClick);
    updateFabState();
}

function updateFabState() {
    const wrap  = document.getElementById('review-fab-wrap');
    const btn   = document.getElementById('review-toggle-btn');
    const icon  = document.getElementById('review-fab-icon');
    const label = document.getElementById('review-fab-label');
    if (!wrap || !btn || !icon || !label) return;

    const docked = wrap.parentElement?.id === 'vortexops-top-actions';
    wrap.style.position = docked ? 'relative' : 'fixed';
    wrap.style.top = docked ? 'auto' : '0.875rem';
    wrap.style.right = docked ? 'auto' : '10.5rem';
    wrap.style.zIndex = docked ? 'auto' : '46';
    btn.style.padding = docked ? '0.38rem 0.72rem' : '0.42rem 0.8rem';
    label.style.display = '';
    btn.title = 'Review Mode';

    if (reviewModeActive) {
        btn.style.background = 'linear-gradient(135deg,#6d28d9,#29e7e7)';
        btn.style.color = '#ffffff';
        btn.style.borderColor = 'rgba(41,231,231,.36)';
        btn.style.boxShadow  = '0 6px 18px rgba(41,231,231,.22)';
        icon.innerHTML  = ICONS.exit;
        label.textContent = 'Exit Review';
    } else {
        btn.style.background = 'rgba(255,255,255,0.94)';
        btn.style.color = docked ? '#334155' : '#3b1b72';
        btn.style.borderColor = docked ? 'rgba(148,163,184,.24)' : 'rgba(41,231,231,.20)';
        btn.style.boxShadow  = '0 1px 6px rgba(0,0,0,0.10)';
        icon.innerHTML  = ICONS.review;
        label.textContent = 'Review';
    }
}

async function handleFabClick() {
    if (reviewModeActive) exitReviewMode();
    else enterReviewMode();
}

// ── Enter / Exit ───────────────────────────────────────────────────────────────
function enterReviewMode() {
    reviewModeActive = true;
    localStorage.setItem('vortex_review_active', '1');
    updateFabState();
    showBrowsingUI();
}

function exitReviewMode() {
    if (canvasActive) closeCanvas(false);
    removeBrowsingUI();
    reviewModeActive = false;
    localStorage.removeItem('vortex_review_active');
    updateFabState();
}

// ── Browsing UI — compact chip + popover ───────────────────────────────────────
function showBrowsingUI() {
    removeBrowsingUI();

    // Compact chip
    browsingChip = document.createElement('div');
    browsingChip.id = 'review-chip';
    Object.assign(browsingChip.style, {
        position: 'fixed',
        top: '3.5rem',
        right: '1rem',
        zIndex: '99997',
        display: 'flex',
        alignItems: 'center',
        gap: '8px',
        padding: '6px 12px',
        background: T.accentSoft,
        border: `1px solid ${T.accentBorder}`,
        borderRadius: '999px',
        fontFamily: T.font,
        fontSize: '11px',
        fontWeight: '600',
        color: '#3b1b72',
        cursor: 'pointer',
        backdropFilter: 'blur(8px)',
        boxShadow: '0 2px 8px rgba(41,231,231,.16)',
        transition: T.transition,
        userSelect: 'none',
    });

    browsingChip.innerHTML = `
        <span style="width:6px;height:6px;border-radius:50%;background:#29e7e7;box-shadow:0 0 0 3px rgba(41,231,231,.2);flex-shrink:0;"></span>
        <span id="review-chip-label">Review Active</span>
        <span id="review-chip-expand" style="opacity:.6;display:flex;align-items:center;margin-left:2px;">${svg('<polyline points="6 9 12 15 18 9"/>', 12)}</span>`;

    document.body.appendChild(browsingChip);
    browsingChip.addEventListener('click', toggleBrowsingPopover);

    // Inject action buttons around the chip (visible inline)
    injectChipActions();
    loadSessionChipLabel();
}

function injectChipActions() {
    // Add three quick-action buttons just above the chip
    const actions = document.createElement('div');
    actions.id = 'review-chip-actions';
    Object.assign(actions.style, {
        position: 'fixed',
        top: '6rem',
        right: '1rem',
        zIndex: '99997',
        display: 'flex',
        flexDirection: 'column',
        gap: '6px',
        fontFamily: T.font,
    });

    const btns = [
        { id: 'chip-annotate', icon: ICONS.pen,    label: 'Markup Page',  click: () => openCanvas(false) },
        { id: 'chip-pick',     icon: ICONS.pin,    label: 'Pick Spot',    click: () => activatePickerMode('annotate') },
        { id: 'chip-note',     icon: ICONS.note,   label: 'Quick Note',   click: () => activatePickerMode('quick_note') },
    ];

    btns.forEach(({ id, icon, label, click }) => {
        const btn = document.createElement('button');
        btn.id = id;
        btn.title = label;
        Object.assign(btn.style, {
            display: 'flex', alignItems: 'center', gap: '7px',
            padding: '7px 12px',
            background: T.bg,
            border: `1px solid ${T.border}`,
            borderRadius: '999px',
            fontFamily: T.font,
            fontSize: '11px',
            fontWeight: '600',
            color: T.text,
            cursor: 'pointer',
            boxShadow: T.shadow,
            whiteSpace: 'nowrap',
            transition: T.transition,
        });
        btn.innerHTML = `<span style="display:flex;align-items:center;opacity:.7;">${icon.replace('width="16" height="16"', 'width="13" height="13"')}</span>${label}`;
        btn.addEventListener('mouseenter', () => { btn.style.borderColor = T.accentBorder; btn.style.color = T.accent; });
        btn.addEventListener('mouseleave', () => { btn.style.borderColor = T.border; btn.style.color = T.text; });
        btn.addEventListener('click', click);
        actions.appendChild(btn);
    });

    document.body.appendChild(actions);
}

function toggleBrowsingPopover() {
    browsingExpanded = !browsingExpanded;
    const expandIcon = document.getElementById('review-chip-expand');

    if (browsingExpanded) {
        if (expandIcon) expandIcon.style.transform = 'rotate(180deg)';
        showBrowsingPopover();
    } else {
        if (expandIcon) expandIcon.style.transform = '';
        browsingPopover?.remove();
        browsingPopover = null;
    }
}

function showBrowsingPopover() {
    browsingPopover?.remove();

    browsingPopover = document.createElement('div');
    browsingPopover.id = 'review-popover';
    Object.assign(browsingPopover.style, {
        position: 'fixed',
        top: '152px',
        right: '20px',
        zIndex: '99997',
        width: '260px',
        background: T.bg,
        border: `1px solid ${T.border}`,
        borderRadius: T.radiusLg,
        boxShadow: T.shadowLg,
        fontFamily: T.font,
        overflow: 'hidden',
    });

    browsingPopover.innerHTML = `
        <div style="padding:10px 12px;background:${T.accentSoft};border-bottom:1px solid ${T.accentBorder};display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:700;color:${T.accent};letter-spacing:0.04em;text-transform:uppercase;">Review Mode</span>
            <span id="review-popover-session" style="font-size:10px;color:${T.muted};background:rgba(0,0,0,.05);padding:2px 8px;border-radius:999px;">Loading…</span>
        </div>
        <div id="review-popover-annotations" style="max-height:180px;overflow-y:auto;">
            <div style="padding:14px 12px;color:${T.subtle};font-size:11px;text-align:center;">
                Loading page annotations…
            </div>
        </div>
        <div style="padding:8px 10px;border-top:1px solid rgba(0,0,0,.06);display:flex;gap:5px;">
            <a href="/review" target="_blank"
               style="flex:1;text-align:center;font-size:10px;font-weight:600;color:${T.muted};text-decoration:none;
                      padding:5px;border-radius:6px;border:1px solid rgba(0,0,0,.08);display:block;
                      transition:${T.transition};">
                Project Hub ↗
            </a>
            <button id="review-popover-change-session"
                style="flex:1;font-size:10px;font-weight:600;color:${T.muted};
                       background:none;border:1px solid rgba(0,0,0,.08);border-radius:6px;
                       padding:5px;cursor:pointer;transition:${T.transition};">
                Change Session
            </button>
        </div>`;

    document.body.appendChild(browsingPopover);
    document.getElementById('review-popover-change-session')?.addEventListener('click', async () => {
        const s = await pickSession();
        if (s) {
            sessionId = normalizeSessionId(s);

            if (!sessionId) {
                showToast('Could not switch review session.', 'danger');
                return;
            }

            localStorage.setItem('vortex_review_session_id', sessionId);
            loadSessionChipLabel();
            loadPopoverAnnotations();
        }
    });

    loadPopoverSession();
    loadPopoverAnnotations();
}

function removeBrowsingUI() {
    browsingChip?.remove();
    browsingChip = null;
    document.getElementById('review-chip-actions')?.remove();
    browsingPopover?.remove();
    browsingPopover = null;
    browsingExpanded = false;
    deactivatePickerMode();
}

async function loadSessionChipLabel() {
    const el = document.getElementById('review-chip-label');
    if (!el) return;

    if (!sessionId) {
        el.textContent = 'Review Active';
        // Still load page annotations for badge (no session = no items, but call to clear stale badge)
        loadPageAnnotationsForBadge();
        return;
    }

    try {
        const r = await fetch('/admin/review/sessions', { headers: { Accept: 'application/json' } });
        const sessions = await r.json();
        const s = sessions.find(x => String(x.id) === String(sessionId));
        if (s) el.textContent = (s.project?.name ? s.project.name + ': ' : '') + s.title.substring(0, 22);
    } catch { /* ignore */ }

    loadPageAnnotationsForBadge();
}

async function loadPageAnnotationsForBadge() {
    if (!sessionId) return;
    try {
        const r   = await fetch(`/admin/review/items?session_id=${sessionId}`, { headers: { Accept: 'application/json' } });
        const all = await r.json();
        const pageItems = all.filter(i => i.page_url === currentReviewUrl());
        updateChipBadge(pageItems);
    } catch { /* ignore */ }
}

async function loadPopoverSession() {
    const badge = document.getElementById('review-popover-session');
    if (!badge || !sessionId) { if (badge) badge.textContent = 'No session'; return; }
    try {
        const r = await fetch('/admin/review/sessions', { headers: { Accept: 'application/json' } });
        const sessions = await r.json();
        const s = sessions.find(x => String(x.id) === String(sessionId));
        if (s) badge.textContent = s.title;
    } catch { badge.textContent = 'Session #' + sessionId; }
}

async function loadPopoverAnnotations() {
    const container = document.getElementById('review-popover-annotations');
    if (!container || !sessionId) {
        if (container) container.innerHTML = `<div style="padding:14px 12px;color:${T.subtle};font-size:11px;text-align:center;">No session active.</div>`;
        return;
    }

    let allItems = [], pageItems = [];
    try {
        const r = await fetch(`/admin/review/items?session_id=${sessionId}`, { headers: { Accept: 'application/json' } });
        allItems  = await r.json();
        pageItems = allItems.filter(item => item.page_url === currentReviewUrl());
    } catch {
        container.innerHTML = `<div style="padding:14px 12px;color:${T.subtle};font-size:11px;text-align:center;">Could not load annotations.</div>`;
        return;
    }

    // Update chip badge
    updateChipBadge(pageItems);

    if (!pageItems.length) {
        container.innerHTML = `<div style="padding:14px 12px;color:${T.subtle};font-size:11px;text-align:center;">No annotations on this page yet.</div>`;
        return;
    }

    const statusDot = { open: '#ef4444', in_progress: '#f59e0b', fixed: '#22c55e', approved: '#10b981', rejected: '#9ca3af', wont_fix: '#9ca3af' };
    const typeIcon  = { annotation: '✏', bug: '⬤', suggestion: '◆', question: '?' };
    const pageUrl   = encodeURIComponent(currentReviewUrl());

    let html = `<div style="padding:7px 12px 3px;font-size:10px;font-weight:700;color:${T.subtle};text-transform:uppercase;letter-spacing:.04em;">${pageItems.length} on this page</div>`;

    pageItems.slice(0, 6).forEach(item => {
        const dot  = statusDot[item.status] ?? '#9ca3af';
        const icon = typeIcon[item.type] ?? '⬤';
        const txt  = (item.comment || item.page_title || 'Annotation').substring(0, 40);
        const reviewUrl = `/review/items/${item.id}`;
        html += `
        <div style="padding:7px 10px 7px 12px;border-top:1px solid rgba(0,0,0,.05);display:flex;gap:8px;align-items:flex-start;">
            <span style="font-size:9px;color:${T.muted};flex-shrink:0;line-height:1.8;">${icon}</span>
            <div style="min-width:0;flex:1;">
                <div style="font-size:11px;font-weight:600;color:${T.text};white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${txt}</div>
                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <span style="width:5px;height:5px;border-radius:50%;background:${dot};flex-shrink:0;display:inline-block;"></span>
                    <span style="font-size:10px;color:${T.subtle};">${(item.status || '').replace('_', ' ')}</span>
                </div>
            </div>
            <a href="${reviewUrl}" target="_blank"
               style="flex-shrink:0;display:flex;align-items:center;justify-content:center;
                      width:22px;height:22px;border-radius:5px;border:1px solid rgba(0,0,0,.08);
                      color:${T.muted};text-decoration:none;transition:${T.transition};"
               title="Open in Project Hub"
               onmouseenter="this.style.borderColor='${T.accentBorder}';this.style.color='${T.accent}'"
               onmouseleave="this.style.borderColor='rgba(0,0,0,.08)';this.style.color='${T.muted}'">
                ${svg('<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/>', 12)}
            </a>
        </div>`;
    });

    if (pageItems.length > 6) {
        html += `<div style="padding:6px 12px;font-size:10px;color:${T.subtle};text-align:center;">+${pageItems.length - 6} more in Project Hub</div>`;
    }
    container.innerHTML = html;
}

function updateChipBadge(pageItems) {
    // Remove existing badge
    document.getElementById('review-chip-badge')?.remove();

    const openCount = pageItems.filter(i => i.status === 'open' || i.status === 'in_progress').length;
    if (!openCount || !browsingChip) return;

    const badge = document.createElement('span');
    badge.id = 'review-chip-badge';
    Object.assign(badge.style, {
        display: 'inline-flex', alignItems: 'center', justifyContent: 'center',
        minWidth: '17px', height: '17px',
        background: '#ef4444',
        color: 'white',
        borderRadius: '999px',
        fontSize: '9px',
        fontWeight: '800',
        padding: '0 4px',
        lineHeight: '1',
        marginLeft: '2px',
    });
    badge.textContent = openCount;

    const expandIcon = document.getElementById('review-chip-expand');
    if (expandIcon) browsingChip.insertBefore(badge, expandIcon);
    else browsingChip.appendChild(badge);
}

// ── Open / Close Canvas ────────────────────────────────────────────────────────
async function openCanvas(uploadMode = false, options = {}) {
    canvasActive        = true;
    hasUploadedImage    = false;
    pageScreenshotDataUrl = null;
    seedAnnotationPoint = options.seedPoint ?? null;
    const autoOpenSave = options.autoOpenSave ?? false;

    deactivatePickerMode();
    removeBrowsingUI();

    let screenshotFailed = false;
    if (!uploadMode) {
        const spinner = showCaptureSpinner();
        try {
            pageScreenshotDataUrl = await capturePageNow();
        } catch {
            screenshotFailed = true;
        }
        spinner.remove();
    }

    buildOverlay();
    buildToolbar();
    if (screenshotFailed) showScreenshotFailToast();

    // Inject the captured screenshot as a locked background layer
    if (pageScreenshotDataUrl) {
        await insertScreenshotBackground(pageScreenshotDataUrl);
    }

    if (seedAnnotationPoint) addSeedMarker(seedAnnotationPoint);
    if (uploadMode)     setTimeout(triggerImageUpload, 200);
    if (autoOpenSave)   setTimeout(() => openSaveModal({ type: 'annotation' }), 140);
}

function showScreenshotFailToast() {
    const el = document.createElement('div');
    el.id = 'review-screenshot-toast';
    Object.assign(el.style, {
        position: 'fixed', bottom: '80px', left: '50%', transform: 'translateX(-50%)',
        zIndex: '99999', display: 'flex', alignItems: 'center', gap: '10px',
        padding: '10px 16px', borderRadius: '10px',
        background: 'rgba(15,23,42,.95)', border: '1px solid rgba(255,255,255,.12)',
        color: 'rgba(255,255,255,.9)', fontFamily: T.font, fontSize: '12px',
        boxShadow: '0 8px 24px rgba(0,0,0,.3)',
        backdropFilter: 'blur(8px)',
    });
    el.innerHTML = `
        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="none" stroke="#f59e0b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        <span>Couldn't capture screenshot — you can still annotate or <strong>submit text-only</strong>.</span>
        <button onclick="this.parentNode.remove()" style="margin-left:6px;background:none;border:none;color:rgba(255,255,255,.5);cursor:pointer;font-size:14px;line-height:1;">×</button>`;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 6000);
}

function showCaptureSpinner() {
    ensureSpinKeyframe();
    const el = document.createElement('div');
    el.id = 'review-capture-spinner';
    Object.assign(el.style, {
        position: 'fixed', inset: '0', zIndex: '99999',
        background: 'rgba(15,23,42,.65)',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        backdropFilter: 'blur(3px)',
    });
    el.innerHTML = `
        <div style="display:flex;flex-direction:column;align-items:center;gap:14px;">
            <div style="width:28px;height:28px;border:3px solid rgba(255,255,255,.2);
                        border-top-color:white;border-radius:50%;
                        animation:review-spin .65s linear infinite;"></div>
            <p style="color:rgba(255,255,255,.9);font-size:13px;font-weight:600;
                      font-family:${T.font};letter-spacing:-0.01em;">Capturing page…</p>
        </div>`;
    document.body.appendChild(el);
    return el;
}

function ensureSpinKeyframe() {
    if (document.getElementById('review-spin-style')) return;
    const s = document.createElement('style');
    s.id = 'review-spin-style';
    s.textContent = '@keyframes review-spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);
}

async function capturePageNow() {
    // Hide any review UI before capture
    const hide = ['#review-fab-wrap', '#review-chip', '#review-chip-actions',
                   '#review-popover', '#review-capture-spinner'];
    const hidden = hide.map(sel => {
        const el = document.querySelector(sel);
        if (el) { el.style.visibility = 'hidden'; }
        return el;
    }).filter(Boolean);

    try {
        const root = getCaptureRoot();
        const attempts = [
            {
                element: root,
                options: {
                    useCORS: true,
                    allowTaint: false,
                    logging: false,
                    backgroundColor: '#f8fafc',
                    foreignObjectRendering: false,
                    scale: Math.min(window.devicePixelRatio || 1, 2),
                    width: window.innerWidth,
                    height: window.innerHeight,
                    x: window.scrollX,
                    y: window.scrollY,
                    scrollX: -window.scrollX,
                    scrollY: -window.scrollY,
                    windowWidth: document.documentElement.clientWidth,
                    windowHeight: document.documentElement.clientHeight,
                    ignoreElements: el => isReviewElement(el),
                    onclone: stripUnsafeMediaFromClone,
                },
            },
            {
                element: document.body,
                options: {
                    useCORS: true,
                    allowTaint: false,
                    logging: false,
                    backgroundColor: '#f8fafc',
                    foreignObjectRendering: false,
                    scale: 1,
                    width: window.innerWidth,
                    height: window.innerHeight,
                    x: window.scrollX,
                    y: window.scrollY,
                    scrollX: -window.scrollX,
                    scrollY: -window.scrollY,
                    ignoreElements: el => isReviewElement(el),
                    onclone: stripUnsafeMediaFromClone,
                },
            },
            {
                element: document.documentElement,
                options: {
                    useCORS: true,
                    allowTaint: false,
                    logging: false,
                    backgroundColor: '#f8fafc',
                    foreignObjectRendering: false,
                    scale: 1,
                    width: window.innerWidth,
                    height: window.innerHeight,
                    x: window.scrollX,
                    y: window.scrollY,
                    scrollX: -window.scrollX,
                    scrollY: -window.scrollY,
                    ignoreElements: el => isReviewElement(el),
                    onclone: stripUnsafeMediaFromClone,
                },
            },
        ];

        for (const attempt of attempts) {
            try {
                const canvas = await html2canvas(attempt.element, attempt.options);

                if (canvas?.width && canvas?.height) {
                    return canvas.toDataURL('image/jpeg', 0.88);
                }
            } catch { /* try fallback */ }
        }

        throw new Error('capture-failed');
    } finally {
        hidden.forEach(el => { el.style.visibility = ''; });
    }
}

function getCaptureRoot() {
    return document.querySelector('.fi-layout')
        ?? document.querySelector('.fi-page')
        ?? document.body
        ?? document.documentElement;
}

function stripUnsafeMediaFromClone(clonedDocument) {
    try {
        clonedDocument.querySelectorAll(
            '#review-fab-wrap,' +
            '#review-chip,' +
            '#review-chip-actions,' +
            '#review-popover,' +
            '#review-capture-spinner,' +
            '#review-toolbar,' +
            '#review-overlay,' +
            '#review-save-modal,' +
            '#vortexops-top-actions'
        ).forEach(el => el.remove());

        clonedDocument.querySelectorAll('img').forEach(img => {
            const src = img.getAttribute('src') ?? '';

            if (isUnsafeCaptureUrl(src)) {
                img.setAttribute('src', '');
                img.style.visibility = 'hidden';
            }
        });

        clonedDocument.querySelectorAll('[style]').forEach(el => {
            const style = el.getAttribute('style') ?? '';
            const sanitized = style.replace(/background(?:-image)?:[^;]*url\([^;]+\)[^;]*;?/gi, '');

            if (sanitized !== style) {
                el.setAttribute('style', sanitized);
            }
        });

        clonedDocument.querySelectorAll('*').forEach(el => {
            const backgroundImage = el.style?.backgroundImage;

            if (backgroundImage && isUnsafeCssBackground(backgroundImage)) {
                el.style.backgroundImage = 'none';
            }
        });
    } catch {
        // Best effort only.
    }
}

function isUnsafeCssBackground(value) {
    const urls = [...String(value).matchAll(/url\((['"]?)(.*?)\1\)/gi)].map(match => match[2]);

    return urls.some(url => isUnsafeCaptureUrl(url));
}

function isUnsafeCaptureUrl(url) {
    const raw = String(url ?? '').trim();

    if (!raw || raw.startsWith('data:') || raw.startsWith('blob:') || raw.startsWith('/')) {
        return false;
    }

    try {
        const resolved = new URL(raw, window.location.href);

        return resolved.origin !== window.location.origin;
    } catch {
        return false;
    }
}

function isReviewElement(el) {
    if (!(el instanceof Element)) return false;

    return !!el.closest(
        '#review-fab-wrap,' +
        '#review-chip,' +
        '#review-chip-actions,' +
        '#review-popover,' +
        '#review-capture-spinner,' +
        '#review-toolbar,' +
        '#review-overlay,' +
        '#review-save-modal,' +
        '#vortexops-top-actions'
    );
}

function closeCanvas(returnToBrowsing = true) {
    overlayEl?.remove();
    toolbarEl?.remove();
    fabricCanvas?.dispose();
    overlayEl = toolbarEl = fabricCanvas = null;
    history = [];
    mouseDownPt = tempShape = null;
    isDrawingRect = isDrawingArrow = false;
    canvasActive = false;
    if (returnToBrowsing && reviewModeActive) showBrowsingUI();
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && canvasActive) { e.preventDefault(); closeCanvas(); }
    if ((e.ctrlKey || e.metaKey) && e.key === 'z' && canvasActive) { e.preventDefault(); undoLast(); }
    if ((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'z' && canvasActive) { e.preventDefault(); /* redo noop */ }
    if (canvasActive && !e.ctrlKey && !e.metaKey) handleToolShortcuts(e);
});

// ── Overlay ────────────────────────────────────────────────────────────────────
function buildOverlay() {
    overlayEl = document.createElement('div');
    overlayEl.id = 'review-overlay';
    Object.assign(overlayEl.style, {
        position: 'fixed', inset: '0', zIndex: '99990',
        background: 'rgba(15,23,42,.08)', overflow: 'hidden',
    });
    const canvasEl = document.createElement('canvas');
    canvasEl.id = 'review-fabric';
    Object.assign(canvasEl.style, { position: 'absolute', inset: '0', width: '100%', height: '100%' });
    overlayEl.appendChild(canvasEl);
    document.body.appendChild(overlayEl);

    fabricCanvas = new fabric.Canvas('review-fabric', {
        selection: true,
        width: window.innerWidth,
        height: window.innerHeight,
        backgroundColor: null,
    });
    setTool('select');
    bindCanvasEvents();
}

async function insertScreenshotBackground(dataUrl) {
    await new Promise(resolve => {
        fabric.Image.fromURL(dataUrl, img => {
            img.set({
                selectable: false,
                evented: false,
                left: 0,
                top: 0,
                originX: 'left',
                originY: 'top',
                hasBorders: false,
                hasControls: false,
            });
            img.scaleX = fabricCanvas.width / img.width;
            img.scaleY = fabricCanvas.height / img.height;
            fabricCanvas.insertAt(img, 0);
            fabricCanvas.requestRenderAll();
            resolve();
        }, { crossOrigin: 'anonymous' });
    });
}

function addSeedMarker(seedPoint) {
    if (!fabricCanvas || !seedPoint) return;
    const { x, y, elementRect } = seedPoint;
    if (elementRect?.width && elementRect?.height) {
        const p = 8;
        const rect = new fabric.Rect({
            left: Math.max(0, elementRect.left - p),
            top:  Math.max(0, elementRect.top  - p),
            width:  elementRect.width  + p * 2,
            height: elementRect.height + p * 2,
            fill: 'rgba(124,58,237,.07)',
            stroke: '#7c3aed', strokeWidth: 2,
            strokeDashArray: [8, 5],
            rx: 10, ry: 10, selectable: true,
        });
        fabricCanvas.add(rect);
    }
    const pin = new fabric.Circle({
        left: x - 8, top: y - 8, radius: 8,
        fill: currentColor, stroke: '#ffffff', strokeWidth: 3, selectable: true,
    });
    fabricCanvas.add(pin);
    fabricCanvas.setActiveObject(pin);
    fabricCanvas.requestRenderAll();
    pushHistory();
}

// ── Picker Mode ────────────────────────────────────────────────────────────────
function activatePickerMode(mode = 'annotate') {
    if (pickerActive || canvasActive) return;
    pickerActive = true;
    pickerMode   = mode;

    pickerBoxEl = document.createElement('div');
    pickerBoxEl.id = 'review-picker-highlight';
    Object.assign(pickerBoxEl.style, {
        position: 'fixed', zIndex: '99996',
        border: '2px solid #7c3aed', borderRadius: '8px',
        background: 'rgba(124,58,237,.07)',
        pointerEvents: 'none',
        boxShadow: '0 0 0 1px rgba(255,255,255,.8) inset',
        transition: 'all .08s ease',
    });
    document.body.appendChild(pickerBoxEl);

    document.addEventListener('mousemove', handlePickerMove, true);
    document.addEventListener('click',     handlePickerClick, true);
    document.body.style.cursor = 'crosshair';
    showToast(mode === 'quick_note' ? 'Click the spot for a quick note' : 'Click the element to annotate');
}

function deactivatePickerMode() {
    if (!pickerActive && !pickerBoxEl) return;
    pickerActive = false;
    document.removeEventListener('mousemove', handlePickerMove, true);
    document.removeEventListener('click',     handlePickerClick, true);
    pickerBoxEl?.remove();
    pickerBoxEl  = null;
    pickerMode   = 'annotate';
    document.body.style.cursor = '';
}

function handlePickerMove(e) {
    if (!pickerActive || !pickerBoxEl) return;
    const t = e.target;
    if (!(t instanceof Element) || isReviewUi(t)) { pickerBoxEl.style.display = 'none'; return; }
    const rect = t.getBoundingClientRect();
    if (rect.width < 4 || rect.height < 4) { pickerBoxEl.style.display = 'none'; return; }
    Object.assign(pickerBoxEl.style, {
        display: 'block',
        left: `${rect.left}px`, top: `${rect.top}px`,
        width: `${rect.width}px`, height: `${rect.height}px`,
    });
}

function handlePickerClick(e) {
    if (!pickerActive) return;
    const t = e.target;
    if (!(t instanceof Element) || isReviewUi(t)) return;
    e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation?.();
    const rect = t.getBoundingClientRect();
    const seedPoint = { x: e.clientX, y: e.clientY,
        elementRect: { left: rect.left, top: rect.top, width: rect.width, height: rect.height } };
    openCanvas(false, { seedPoint, autoOpenSave: pickerMode === 'quick_note' });
}

function isReviewUi(t) {
    return !!t.closest('#review-chip,#review-chip-actions,#review-popover,#review-toolbar,#review-overlay,#review-save-modal,#review-toggle-btn,#review-fab-wrap,#review-picker-highlight');
}

// ── Toolbar (Figma-style horizontal bar at top) ────────────────────────────────
const TOOLS = [
    { id: 'select', label: 'Select (V)',   icon: ICONS.cursor },
    { id: 'draw',   label: 'Pen (P)',      icon: ICONS.pen },
    { id: 'box',    label: 'Box (B)',      icon: ICONS.box },
    { id: 'arrow',  label: 'Arrow (A)',    icon: ICONS.arrow },
    { id: 'text',   label: 'Text (T)',     icon: ICONS.text },
];

const COLORS = [
    { hex: '#e11d48', name: 'Rose' },
    { hex: '#2563eb', name: 'Blue' },
    { hex: '#16a34a', name: 'Green' },
    { hex: '#d97706', name: 'Amber' },
    { hex: '#29e7e7', name: 'Aqua' },
    { hex: '#7c3aed', name: 'Violet' },
    { hex: '#000000', name: 'Black' },
];

const STROKES = [
    { w: 2, icon: ICONS.thin },
    { w: 3, icon: ICONS.medium },
    { w: 5, icon: ICONS.thick },
];

function buildToolbar() {
    toolbarEl = document.createElement('div');
    toolbarEl.id = 'review-toolbar';

    Object.assign(toolbarEl.style, {
        position: 'fixed', top: '14px', left: '50%',
        transform: 'translateX(-50%)',
        zIndex: '99995',
        display: 'flex', alignItems: 'center', gap: '4px',
        padding: '6px 8px',
        background: T.bg,
        border: `1px solid ${T.border}`,
        borderRadius: T.radiusLg,
        boxShadow: T.shadowLg,
        fontFamily: T.font,
        maxWidth: 'calc(100vw - 32px)',
    });

    // Left cluster: close + back
    toolbarEl.appendChild(makeVDiv());
    toolbarEl.appendChild(makeTBtn(ICONS.exit, 'Exit (Esc)', closeCanvas,
        { color: T.danger, hoverBg: 'rgba(220,38,38,.07)' }));
    toolbarEl.appendChild(makeSep());

    // Tool buttons
    TOOLS.forEach(({ id, label, icon }) => {
        const btn = makeTBtn(icon, label, () => setTool(id));
        btn.dataset.tool = id;
        toolbarEl.appendChild(btn);
    });

    toolbarEl.appendChild(makeSep());

    // Color swatches
    COLORS.forEach(({ hex, name }) => {
        const s = document.createElement('button');
        s.title = name;
        s.dataset.swatch = hex;
        Object.assign(s.style, {
            width: '18px', height: '18px', borderRadius: '50%',
            background: hex, border: '2px solid transparent',
            cursor: 'pointer', flexShrink: '0', transition: T.transition,
        });
        if (hex === currentColor) {
            s.style.border = '2px solid ' + T.text;
            s.style.outline = '2px solid white';
            s.style.outlineOffset = '-4px';
        }
        s.addEventListener('click', () => {
            currentColor = hex;
            applyColor(hex);
            toolbarEl.querySelectorAll('[data-swatch]').forEach(sw => {
                const on = sw.dataset.swatch === hex;
                sw.style.border = on ? '2px solid ' + T.text : '2px solid transparent';
                sw.style.outline = on ? '2px solid white' : '';
                sw.style.outlineOffset = on ? '-4px' : '';
            });
        });
        toolbarEl.appendChild(s);
    });

    toolbarEl.appendChild(makeSep());

    // Stroke widths
    STROKES.forEach(({ w, icon }) => {
        const btn = makeTBtn(icon, `Stroke width: ${w}px`, () => {
            strokeWidth = w;
            if (fabricCanvas?.isDrawingMode) fabricCanvas.freeDrawingBrush.width = w;
            toolbarEl.querySelectorAll('[data-stroke]').forEach(b => {
                b.style.background = parseInt(b.dataset.stroke) === w ? T.accentSoft : 'transparent';
            });
        });
        btn.dataset.stroke = w;
        if (w === strokeWidth) btn.style.background = T.accentSoft;
        toolbarEl.appendChild(btn);
    });

    toolbarEl.appendChild(makeSep());

    // Utility buttons
    toolbarEl.appendChild(makeTBtn(ICONS.image, 'Upload image', triggerImageUpload));
    toolbarEl.appendChild(makeTBtn(ICONS.undo,  'Undo (⌘Z)',   undoLast));
    toolbarEl.appendChild(makeTBtn(ICONS.trash,  'Clear all',   () => { fabricCanvas.clear(); history = []; hasUploadedImage = false; }));

    toolbarEl.appendChild(makeSep());

    // Save button (accent)
    const saveBtn = makeTBtn(ICONS.save, 'Save annotation', () => openSaveModal());
    Object.assign(saveBtn.style, {
        background: T.accent, color: 'white', borderRadius: '7px', padding: '6px 12px',
    });
    saveBtn.addEventListener('mouseenter', () => saveBtn.style.opacity = '.88');
    saveBtn.addEventListener('mouseleave', () => saveBtn.style.opacity = '1');
    // Add "Save" label to this one
    const saveLabel = document.createElement('span');
    saveLabel.textContent = 'Save';
    Object.assign(saveLabel.style, { fontSize: '12px', fontWeight: '700', marginLeft: '5px', letterSpacing: '-0.01em' });
    saveBtn.appendChild(saveLabel);
    toolbarEl.appendChild(saveBtn);

    document.body.appendChild(toolbarEl);
    highlightActiveTool();

    // Keyboard shortcuts in annotation mode
}

function makeVDiv() {
    const d = document.createElement('div');
    Object.assign(d.style, { width: '2px' });
    return d;
}

function makeSep() {
    const s = document.createElement('div');
    Object.assign(s.style, { width: '1px', height: '20px', background: T.border, margin: '0 3px', flexShrink: '0' });
    return s;
}

function makeTBtn(icon, title, onClick, opts = {}) {
    const btn = document.createElement('button');
    btn.title = title;
    btn.innerHTML = typeof icon === 'string' ? icon : '';
    Object.assign(btn.style, {
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        width: '30px', height: '30px',
        borderRadius: '7px', border: 'none',
        background: 'transparent',
        color: opts.color || T.text,
        cursor: 'pointer',
        flexShrink: '0',
        transition: T.transition,
    });
    btn.addEventListener('mouseenter', () => { if (!btn.dataset.active) btn.style.background = opts.hoverBg || 'rgba(0,0,0,.06)'; });
    btn.addEventListener('mouseleave', () => { if (!btn.dataset.active) btn.style.background = btn.style.background === 'rgb(0 0 0 / 0.06)' ? 'transparent' : btn.style.background; });
    btn.addEventListener('click', onClick);
    return btn;
}

function highlightActiveTool() {
    toolbarEl?.querySelectorAll('[data-tool]').forEach(btn => {
        const on = btn.dataset.tool === currentTool;
        btn.style.background  = on ? T.accentSoft : 'transparent';
        btn.style.color       = on ? T.accent : T.text;
        btn.dataset.active    = on ? 'true' : '';
    });
}

function handleToolShortcuts(e) {
    if (!canvasActive) return;
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
    if (e.target.closest && e.target.closest('[contenteditable]')) return;
    const map = { v: 'select', p: 'draw', b: 'box', a: 'arrow', t: 'text' };
    if (map[e.key.toLowerCase()]) setTool(map[e.key.toLowerCase()]);
}

// ── Image Upload ───────────────────────────────────────────────────────────────
function triggerImageUpload() {
    const input = document.createElement('input');
    input.type = 'file'; input.accept = 'image/*';
    input.onchange = e => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const url = ev.target.result;
            fabric.Image.fromURL(url, img => {
                const scale = Math.min(window.innerWidth * 0.8 / img.width, window.innerHeight * 0.8 / img.height, 1);
                img.scale(scale);
                fabricCanvas.add(img);
                fabricCanvas.centerObject(img);
                img.setCoords();
                fabricCanvas.requestRenderAll();
                hasUploadedImage = true;
                pushHistory();
            });
        };
        reader.readAsDataURL(file);
    };
    input.click();
}

// ── Tool Switching ─────────────────────────────────────────────────────────────
function setTool(tool) {
    currentTool = tool;
    if (!fabricCanvas) return;
    fabricCanvas.isDrawingMode = tool === 'draw';
    fabricCanvas.selection     = tool === 'select';
    fabricCanvas.defaultCursor = tool === 'text' ? 'text' : (tool === 'select' ? 'default' : 'crosshair');
    if (tool === 'draw') {
        fabricCanvas.freeDrawingBrush.color = currentColor;
        fabricCanvas.freeDrawingBrush.width = strokeWidth;
    }
    if (fabricCanvas.getActiveObjects().length && tool !== 'select') {
        fabricCanvas.discardActiveObject();
        fabricCanvas.requestRenderAll();
    }
    highlightActiveTool();
}

function applyColor(color) {
    if (!fabricCanvas) return;
    if (currentTool === 'draw') fabricCanvas.freeDrawingBrush.color = color;
    fabricCanvas.getActiveObjects().forEach(obj => {
        obj.set(obj.type === 'i-text' || obj.type === 'text' ? 'fill' : 'stroke', color);
    });
    if (fabricCanvas.getActiveObjects().length) fabricCanvas.requestRenderAll();
}

// ── Canvas Events ──────────────────────────────────────────────────────────────
function bindCanvasEvents() {
    fabricCanvas.on('mouse:down', ({ e }) => {
        const pt = fabricCanvas.getPointer(e);
        if (currentTool === 'text') {
            const t = new fabric.IText('Type here…', {
                left: pt.x, top: pt.y,
                fontSize: 18, fill: currentColor,
                fontFamily: 'sans-serif', selectable: true, editable: true,
            });
            fabricCanvas.add(t);
            fabricCanvas.setActiveObject(t);
            t.enterEditing();
            pushHistory();
            return;
        }
        if (currentTool === 'box' || currentTool === 'arrow') {
            mouseDownPt    = pt;
            isDrawingRect  = currentTool === 'box';
            isDrawingArrow = currentTool === 'arrow';
        }
    });

    fabricCanvas.on('mouse:move', ({ e }) => {
        if (!mouseDownPt) return;
        const pt = fabricCanvas.getPointer(e);
        if (isDrawingRect) {
            tempShape?.remove();
            tempShape = new fabric.Rect({
                left: Math.min(mouseDownPt.x, pt.x), top: Math.min(mouseDownPt.y, pt.y),
                width: Math.abs(pt.x - mouseDownPt.x), height: Math.abs(pt.y - mouseDownPt.y),
                fill: 'transparent', stroke: currentColor, strokeWidth,
                selectable: false, evented: false,
            });
            fabricCanvas.add(tempShape);
            fabricCanvas.requestRenderAll();
        }
        if (isDrawingArrow) {
            tempShape?.remove();
            tempShape = buildArrow(mouseDownPt.x, mouseDownPt.y, pt.x, pt.y, currentColor, false);
            fabricCanvas.add(tempShape);
            fabricCanvas.requestRenderAll();
        }
    });

    fabricCanvas.on('mouse:up', () => {
        if (tempShape) { tempShape.set({ selectable: true, evented: true }); tempShape = null; pushHistory(); }
        mouseDownPt = null; isDrawingRect = false; isDrawingArrow = false;
    });

    fabricCanvas.on('object:added', () => { if (fabricCanvas.isDrawingMode) pushHistory(); });
}

function buildArrow(x1, y1, x2, y2, color, selectable = true) {
    const angle = (Math.atan2(y2 - y1, x2 - x1) * 180) / Math.PI;
    const line  = new fabric.Line([x1, y1, x2, y2], { stroke: color, strokeWidth, selectable: false, evented: false });
    const head  = new fabric.Triangle({
        width: 14, height: 18, fill: color,
        left: x2, top: y2, angle: angle + 90,
        originX: 'center', originY: 'center',
        selectable: false, evented: false,
    });
    return new fabric.Group([line, head], { selectable, evented: selectable });
}

// ── History ────────────────────────────────────────────────────────────────────
function pushHistory() {
    history.push(JSON.stringify(fabricCanvas));
    if (history.length > 50) history.shift();
}

function undoLast() {
    if (!history.length) return;
    history.pop();
    const prev = history[history.length - 1];
    if (prev) fabricCanvas.loadFromJSON(prev, () => fabricCanvas.requestRenderAll());
    else fabricCanvas.clear();
}

// ── Screenshot capture ─────────────────────────────────────────────────────────
// The page screenshot is already baked into the fabric canvas as the first layer,
// so we just export the canvas directly — no second html2canvas call needed.
async function captureForSave() {
    try {
        // Find annotation-only objects (skip the background image at index 0 if present)
        const objects  = fabricCanvas.getObjects();
        const hasAnnotations = objects.length > (pageScreenshotDataUrl ? 1 : 0);

        if (!hasAnnotations) {
            // No drawings — save just the page screenshot
            return pageScreenshotDataUrl ?? null;
        }

        if (pageScreenshotDataUrl) {
            return await mergePageScreenshotWithAnnotations();
        }

        // Determine crop region around annotations (skip bg image)
        const annotObjs = pageScreenshotDataUrl ? objects.slice(1) : objects;
        const bounds    = getAnnotationBounds(annotObjs);

        if (!bounds) {
            return fabricCanvas.toDataURL({ format: 'jpeg', quality: 0.88 });
        }

        // Crop the canvas around the annotations (+ padding)
        const pad = 40;
        const left   = Math.max(0, bounds.left   - pad);
        const top    = Math.max(0, bounds.top    - pad);
        const width  = Math.min(fabricCanvas.width  - left, bounds.width  + pad * 2);
        const height = Math.min(fabricCanvas.height - top,  bounds.height + pad * 2);

        return fabricCanvas.toDataURL({ format: 'jpeg', quality: 0.90,
            left, top, width: Math.max(80, width), height: Math.max(80, height) });
    } catch { return null; }
}

async function mergePageScreenshotWithAnnotations() {
    const background = pageScreenshotDataUrl;

    if (!background) {
        return fabricCanvas.toDataURL({
            format: 'jpeg',
            quality: 0.92,
            left: 0,
            top: 0,
            width: fabricCanvas.width,
            height: fabricCanvas.height,
        });
    }

    const overlay = exportAnnotationOverlay();

    if (!overlay) {
        return background;
    }

    const [backgroundImage, overlayImage] = await Promise.all([
        loadImageElement(background),
        loadImageElement(overlay),
    ]);

    const output = document.createElement('canvas');
    output.width = fabricCanvas.width;
    output.height = fabricCanvas.height;

    const context = output.getContext('2d');
    context.fillStyle = '#f8fafc';
    context.fillRect(0, 0, output.width, output.height);
    context.drawImage(backgroundImage, 0, 0, output.width, output.height);
    context.drawImage(overlayImage, 0, 0, output.width, output.height);

    return output.toDataURL('image/jpeg', 0.92);
}

function exportAnnotationOverlay() {
    const backgroundObject = pageScreenshotDataUrl ? fabricCanvas.getObjects()[0] : null;

    if (!backgroundObject) {
        return fabricCanvas.toDataURL({
            format: 'png',
            left: 0,
            top: 0,
            width: fabricCanvas.width,
            height: fabricCanvas.height,
        });
    }

    const originalVisibility = backgroundObject.visible;
    backgroundObject.visible = false;
    fabricCanvas.requestRenderAll();

    const dataUrl = fabricCanvas.toDataURL({
        format: 'png',
        left: 0,
        top: 0,
        width: fabricCanvas.width,
        height: fabricCanvas.height,
    });

    backgroundObject.visible = originalVisibility;
    fabricCanvas.requestRenderAll();

    return dataUrl;
}

function loadImageElement(src) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = () => resolve(image);
        image.onerror = () => reject(new Error('image-load-failed'));
        image.src = src;
    });
}

function getAnnotationBounds(objects) {
    if (!objects?.length) return null;
    let minX = Infinity, minY = Infinity, maxX = 0, maxY = 0;
    objects.forEach(o => {
        const r = o.getBoundingRect();
        minX = Math.min(minX, r.left); minY = Math.min(minY, r.top);
        maxX = Math.max(maxX, r.left + r.width); maxY = Math.max(maxY, r.top + r.height);
    });
    if (!isFinite(minX)) return null;
    return { left: minX, top: minY, width: maxX - minX, height: maxY - minY };
}

// ── Save Modal ─────────────────────────────────────────────────────────────────
function openSaveModal(options = {}) {
    document.getElementById('review-save-modal')?.remove();
    const suggestedSessionTitle = `Quick Review - ${new Date().toLocaleDateString()}`;

    const modal = document.createElement('div');
    modal.id = 'review-save-modal';
    Object.assign(modal.style, {
        position: 'fixed', inset: '0', zIndex: '99999',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        background: 'rgba(0,0,0,.45)', backdropFilter: 'blur(4px)',
    });

    modal.innerHTML = `
        <div style="background:${T.bg};border-radius:${T.radiusLg};width:420px;max-width:90vw;
                    box-shadow:${T.shadowLg};border:1px solid ${T.border};overflow:hidden;font-family:${T.font};">

            <div style="padding:16px 20px 14px;border-bottom:1px solid rgba(0,0,0,.07);">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <h3 style="font-size:15px;font-weight:700;color:${T.text};margin:0;letter-spacing:-0.02em;">Save Annotation</h3>
                    <button id="review-modal-close"
                        style="display:flex;align-items:center;justify-content:center;
                               width:26px;height:26px;border-radius:6px;border:none;
                               background:rgba(0,0,0,.06);color:${T.muted};cursor:pointer;
                               transition:${T.transition};">
                        ${ICONS.exit.replace('width="16" height="16"', 'width="14" height="14"')}
                    </button>
                </div>
                <p style="font-size:11px;color:${T.subtle};margin:4px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    ${window.location.pathname}${window.location.search ? window.location.search.substring(0, 30) : ''}
                </p>
            </div>

            <div style="padding:16px 20px;">
                <label style="display:block;font-size:12px;font-weight:600;color:${T.text};margin-bottom:6px;letter-spacing:-0.005em;">
                    What's the issue or comment?
                </label>
                <textarea id="review-comment-input" rows="3"
                    placeholder="${options.placeholder ?? 'Describe the issue, suggestion, or leave a note…'}"
                    style="width:100%;border:1px solid rgba(0,0,0,.12);border-radius:8px;
                           padding:9px 11px;font-size:13px;line-height:1.5;color:${T.text};
                           resize:vertical;outline:none;box-sizing:border-box;font-family:${T.font};
                           transition:border-color .14s,box-shadow .14s;background:white;"></textarea>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:10px;">
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;color:${T.muted};margin-bottom:4px;">Type</label>
                        <select id="review-type-select"
                            style="width:100%;border:1px solid rgba(0,0,0,.12);border-radius:7px;
                                   padding:7px 9px;font-size:12px;font-family:${T.font};
                                   background:white;color:${T.text};outline:none;cursor:pointer;">
                            <option value="annotation">Annotation</option>
                            <option value="bug">Bug</option>
                            <option value="suggestion">Suggestion</option>
                            <option value="question">Question</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-size:11px;font-weight:600;color:${T.muted};margin-bottom:4px;">Priority</label>
                        <select id="review-priority-select"
                            style="width:100%;border:1px solid rgba(0,0,0,.12);border-radius:7px;
                                   padding:7px 9px;font-size:12px;font-family:${T.font};
                                   background:white;color:${T.text};outline:none;cursor:pointer;">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>

                ${!sessionId ? `
                <div style="margin-top:10px;padding:10px;background:rgba(124,58,237,.06);border:1px solid rgba(124,58,237,.18);border-radius:8px;">
                    <p style="font-size:11px;font-weight:600;color:${T.accent};margin:0 0 6px;">No session selected — create one to save this annotation</p>
                    <input id="review-new-session-inline" type="text" placeholder="Session name, e.g. Phase 3 Review"
                        style="width:100%;border:1px solid rgba(0,0,0,.12);border-radius:7px;padding:7px 9px;
                               font-size:12px;box-sizing:border-box;font-family:${T.font};outline:none;background:white;"/>
                </div>` : ''}

                <div id="review-save-error" style="display:none;color:${T.danger};font-size:12px;margin-top:8px;font-weight:500;"></div>
            </div>

            <div style="padding:12px 20px 14px;border-top:1px solid rgba(0,0,0,.07);display:flex;justify-content:flex-end;gap:7px;background:rgba(0,0,0,.02);">
                <button id="review-cancel-btn"
                    style="padding:8px 16px;border-radius:7px;border:1px solid rgba(0,0,0,.12);
                           background:white;font-size:13px;font-weight:600;cursor:pointer;
                           color:${T.muted};font-family:${T.font};transition:${T.transition};">
                    Cancel
                </button>
                <button id="review-save-btn"
                    style="padding:8px 20px;border-radius:7px;border:none;
                           background:${T.accent};color:white;font-size:13px;font-weight:700;
                           cursor:pointer;font-family:${T.font};letter-spacing:-0.01em;
                           box-shadow:0 2px 6px rgba(124,58,237,.35);transition:${T.transition};">
                    Save Annotation
                </button>
            </div>
        </div>`;

    document.body.appendChild(modal);

    // Textarea focus ring
    const ta = modal.querySelector('#review-comment-input');
    ta?.addEventListener('focus', () => { ta.style.borderColor = T.accentBorder; ta.style.boxShadow = `0 0 0 3px rgba(124,58,237,.15)`; });
    ta?.addEventListener('blur',  () => { ta.style.borderColor = 'rgba(0,0,0,.12)'; ta.style.boxShadow = 'none'; });

    document.getElementById('review-modal-close').addEventListener('click', () => modal.remove());
    document.getElementById('review-cancel-btn').addEventListener('click',  () => modal.remove());
    document.getElementById('review-save-btn').addEventListener('click',    () => submitAnnotation(modal));
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });

    if (options.type) {
        const sel = document.getElementById('review-type-select');
        if (sel) sel.value = options.type;
    }

    if (!sessionId) {
        const sessionInput = document.getElementById('review-new-session-inline');
        if (sessionInput && !sessionInput.value.trim()) {
            sessionInput.value = suggestedSessionTitle;
        }
    }

    document.getElementById('review-comment-input')?.focus();
}

async function submitAnnotation(modal) {
    const comment  = document.getElementById('review-comment-input').value.trim();
    const type     = document.getElementById('review-type-select').value;
    const priority = document.getElementById('review-priority-select').value;
    const errEl    = document.getElementById('review-save-error');
    const saveBtn  = document.getElementById('review-save-btn');

    if (!comment) {
        errEl.textContent = 'Please add a comment or description.';
        errEl.style.display = 'block';
        document.getElementById('review-comment-input')?.focus();
        return;
    }

    sessionId = await ensureSessionSelected();

    // Inline / automatic session creation if no session
    if (!sessionId) {
        const nameInput = document.getElementById('review-new-session-inline');
        const title     = nameInput?.value?.trim() || `Quick Review - ${new Date().toLocaleDateString()}`;
        if (!title) {
            errEl.textContent = 'Please enter a session name to save this annotation.';
            errEl.style.display = 'block';
            nameInput?.focus();
            return;
        }
        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const r    = await fetch('/admin/review/sessions', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({ title, project_id: projectId ? parseInt(projectId, 10) : null }),
            });
            if (!r.ok) {
                const err = await r.json().catch(() => ({}));
                throw new Error(err.message || `Server error ${r.status}`);
            }
            const s = await r.json();
            sessionId = normalizeSessionId(s.id);

            if (!sessionId) {
                throw new Error('Session was created but no valid id was returned.');
            }

            localStorage.setItem('vortex_review_session_id', sessionId);
            loadSessionChipLabel();
        } catch (e) {
            errEl.textContent = 'Could not create session: ' + e.message;
            errEl.style.display = 'block';
            return;
        }
    }

    errEl.style.display = 'none';
    saveBtn.textContent = 'Saving…';
    saveBtn.disabled    = true;
    saveBtn.style.opacity = '.7';

    try {
        const screenshotDataUrl = await captureForSave();
        const fabricJson        = JSON.stringify(fabricCanvas.toJSON());
        const csrf              = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const normalizedSessionId = normalizeSessionId(sessionId);

        if (!normalizedSessionId) {
            throw new Error('A review session could not be prepared. Please try again.');
        }

        sessionId = normalizedSessionId;

        const resp = await fetch('/admin/review/items', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                body: JSON.stringify({
                review_session_id: Number.parseInt(sessionId, 10),
                page_url:    currentReviewUrl(),
                page_title:  document.title,
                screenshot:  screenshotDataUrl,
                fabric_json: fabricJson,
                comment, type, priority,
            }),
        });

        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}));
            throw new Error(err.message || `Server error ${resp.status}`);
        }

        modal.remove();
        showToast('Annotation saved');

        // Clear drawings but keep the page screenshot background
        const bgImg = pageScreenshotDataUrl ? fabricCanvas.getObjects()[0] : null;
        fabricCanvas.clear();
        if (bgImg) { fabricCanvas.insertAt(bgImg, 0); fabricCanvas.requestRenderAll(); }
        history = [];
        hasUploadedImage = false;
    } catch (err) {
        errEl.textContent   = err.message;
        errEl.style.display = 'block';
        saveBtn.textContent = 'Save Annotation';
        saveBtn.disabled    = false;
        saveBtn.style.opacity = '1';
    }
}

// ── Session Picker ─────────────────────────────────────────────────────────────
async function pickSession() {
    return new Promise(async resolve => {
        let sessions = [];
        try {
            const r = await fetch('/admin/review/sessions', { headers: { Accept: 'application/json' } });
            sessions = await r.json();
        } catch { /* offline */ }

        const modal = document.createElement('div');
        Object.assign(modal.style, {
            position: 'fixed', inset: '0', zIndex: '99999',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            background: 'rgba(0,0,0,.45)', backdropFilter: 'blur(4px)',
        });

        const sessionOptions = sessions.map(s => {
            const prefix = s.project?.name ? `${s.project.name} — ` : '';
            return `<option value="${s.id}">${prefix}${s.title} (${s.items_count ?? 0} items)</option>`;
        }).join('');

        modal.innerHTML = `
            <div style="background:${T.bg};border-radius:${T.radiusLg};width:400px;max-width:90vw;
                        box-shadow:${T.shadowLg};border:1px solid ${T.border};overflow:hidden;font-family:${T.font};">
                <div style="padding:16px 20px 14px;border-bottom:1px solid rgba(0,0,0,.07);">
                    <h3 style="font-size:15px;font-weight:700;color:${T.text};margin:0;letter-spacing:-0.02em;">Change Session</h3>
                    <p style="font-size:12px;color:${T.muted};margin:4px 0 0;">Annotations are grouped by session inside Project Hub.</p>
                </div>

                <div style="padding:16px 20px;">
                    ${sessions.length ? `
                    <label style="display:block;font-size:12px;font-weight:600;color:${T.text};margin-bottom:6px;">Existing session</label>
                    <select id="review-session-select"
                        style="width:100%;border:1px solid rgba(0,0,0,.12);border-radius:8px;
                               padding:8px 10px;font-size:13px;margin-bottom:10px;background:white;
                               font-family:${T.font};color:${T.text};outline:none;">
                        ${sessionOptions}
                    </select>
                    <button id="review-use-existing"
                        style="width:100%;padding:9px;border-radius:8px;border:none;
                               background:${T.accent};color:white;font-size:13px;font-weight:700;
                               cursor:pointer;margin-bottom:12px;font-family:${T.font};
                               box-shadow:0 2px 6px rgba(124,58,237,.3);">
                        Use Selected Session
                    </button>
                    <div style="text-align:center;font-size:11px;color:${T.subtle};margin-bottom:12px;">— or create new —</div>
                    ` : ''}

                    <label style="display:block;font-size:12px;font-weight:600;color:${T.text};margin-bottom:6px;">New session name</label>
                    <input id="review-new-session-name" type="text" placeholder="e.g. Phase 3 Feedback Round 1"
                        style="width:100%;border:1px solid rgba(0,0,0,.12);border-radius:8px;
                               padding:8px 10px;font-size:13px;margin-bottom:8px;
                               box-sizing:border-box;font-family:${T.font};outline:none;background:white;"/>
                    <button id="review-create-session"
                        style="width:100%;padding:9px;border-radius:8px;border:none;background:#111318;
                               color:white;font-size:13px;font-weight:700;cursor:pointer;font-family:${T.font};">
                        Create & Use Session
                    </button>
                </div>

                <div style="padding:0 20px 14px;">
                    <button id="review-cancel-session"
                        style="width:100%;padding:8px;border-radius:8px;border:1px solid rgba(0,0,0,.10);
                               background:white;font-size:13px;cursor:pointer;color:${T.muted};font-family:${T.font};">
                        Cancel
                    </button>
                </div>
            </div>`;

        document.body.appendChild(modal);
        const close = val => { modal.remove(); resolve(val); };

        document.getElementById('review-cancel-session').addEventListener('click', () => close(null));
        document.getElementById('review-use-existing')?.addEventListener('click', () => {
            close(document.getElementById('review-session-select')?.value || null);
        });
        document.getElementById('review-create-session').addEventListener('click', async () => {
            const title = document.getElementById('review-new-session-name').value.trim();
            if (!title) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const r    = await fetch('/admin/review/sessions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, Accept: 'application/json' },
                    body: JSON.stringify({ title, project_id: projectId ? parseInt(projectId, 10) : null }),
                });
                if (!r.ok) {
                    const err = await r.json().catch(() => ({}));
                    throw new Error(err.message || `Server error ${r.status}`);
                }
                const s = await r.json();
                close(String(s.id));
            } catch (e) { alert('Could not create session: ' + e.message); }
        });
    });
}

// ── Toast ──────────────────────────────────────────────────────────────────────
function showToast(msg, type = 'success') {
    document.querySelectorAll('#review-toast').forEach(t => t.remove());
    const colors = {
        success: { bg: T.success, text: 'white' },
        info:    { bg: '#2563eb', text: 'white' },
        warn:    { bg: '#d97706', text: 'white' },
    };
    const c = colors[type] ?? colors.success;
    const t = document.createElement('div');
    t.id = 'review-toast';
    Object.assign(t.style, {
        position: 'fixed', bottom: '80px', left: '50%',
        transform: 'translateX(-50%) translateY(4px)',
        zIndex: '999999',
        background: c.bg, color: c.text,
        padding: '9px 18px', borderRadius: '999px',
        fontSize: '12px', fontWeight: '700', fontFamily: T.font,
        letterSpacing: '-0.01em',
        boxShadow: '0 4px 14px rgba(0,0,0,.2)',
        pointerEvents: 'none',
        transition: 'opacity .3s, transform .3s',
        whiteSpace: 'nowrap',
        opacity: '0',
    });
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => {
        t.style.opacity = '1';
        t.style.transform = 'translateX(-50%) translateY(0)';
    });
    setTimeout(() => {
        t.style.opacity = '0';
        t.style.transform = 'translateX(-50%) translateY(4px)';
        setTimeout(() => t.remove(), 300);
    }, 2600);
}
