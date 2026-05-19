import * as fabricModule from 'fabric';
const fabric = fabricModule.fabric ?? fabricModule.default?.fabric ?? fabricModule.default ?? fabricModule;
import html2canvas from 'html2canvas';

// ─── State ────────────────────────────────────────────────────────────────────
let reviewModeActive = false;   // persists across page loads via localStorage
let canvasActive     = false;   // canvas overlay is shown
let sessionId        = null;
let hasUploadedImage = false;   // track if user uploaded an image (skip page screenshot)

let fabricCanvas  = null;
let overlayEl     = null;
let toolbarEl     = null;
let browsingPanel = null;       // floating panel shown in browsing mode

let currentTool    = 'select';
let currentColor   = '#e11d48';
let history        = [];
let mouseDownPt    = null;
let tempShape      = null;
let isDrawingRect  = false;
let isDrawingArrow = false;

// ─── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    sessionId = localStorage.getItem('vortex_review_session_id') || null;
    injectFab();
    if (localStorage.getItem('vortex_review_active') === '1' && sessionId) {
        reviewModeActive = true;
        updateFabState();
        showBrowsingUI();
    }
});

// ─── FAB ──────────────────────────────────────────────────────────────────────
function injectFab() {
    const wrap = document.createElement('div');
    wrap.innerHTML = `
        <button id="review-toggle-btn"
            style="position:fixed;bottom:24px;left:24px;z-index:99998;
                   display:flex;align-items:center;gap:8px;border:none;
                   border-radius:999px;padding:10px 18px;font-size:12px;font-weight:700;
                   color:white;background:#1e293b;cursor:pointer;
                   box-shadow:0 8px 24px rgba(0,0,0,0.25);
                   transition:background 0.2s,transform 0.15s;"
            onmouseenter="this.style.transform='scale(1.05)'"
            onmouseleave="this.style.transform='scale(1)'"
            title="Toggle Review Mode">
            <svg style="width:14px;height:14px;flex-shrink:0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span id="review-fab-label">Review Mode</span>
        </button>`;
    document.body.appendChild(wrap);
    document.getElementById('review-toggle-btn').addEventListener('click', handleFabClick);
}

function updateFabState() {
    const btn   = document.getElementById('review-toggle-btn');
    const label = document.getElementById('review-fab-label');
    if (!btn || !label) return;

    if (reviewModeActive) {
        btn.style.background = '#be123c';
        label.textContent    = 'Exit Review';
    } else {
        btn.style.background = '#1e293b';
        label.textContent    = 'Review Mode';
    }
}

async function handleFabClick() {
    if (reviewModeActive) {
        exitReviewMode();
    } else {
        await enterReviewMode();
    }
}

// ─── Enter / Exit Review Mode ─────────────────────────────────────────────────
async function enterReviewMode() {
    if (!sessionId) {
        const chosen = await pickSession();
        if (!chosen) return;
        sessionId = String(chosen);
        localStorage.setItem('vortex_review_session_id', sessionId);
    }
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

// ─── Browsing Mode UI ─────────────────────────────────────────────────────────
function showBrowsingUI() {
    removeBrowsingUI();

    browsingPanel = document.createElement('div');
    browsingPanel.id = 'review-browse-panel';
    Object.assign(browsingPanel.style, {
        position: 'fixed',
        bottom: '70px',
        left: '16px',
        zIndex: '99997',
        width: '230px',
        background: 'white',
        borderRadius: '14px',
        boxShadow: '0 12px 32px rgba(0,0,0,0.18)',
        overflow: 'hidden',
        fontFamily: 'system-ui, sans-serif',
    });

    const isAdminPage = window.location.pathname.startsWith('/admin');

    browsingPanel.innerHTML = `
        <div style="padding:10px 12px;background:#7c3aed;display:flex;align-items:center;justify-content:space-between;">
            <span style="font-size:11px;font-weight:700;color:white;letter-spacing:0.05em;">REVIEW MODE ACTIVE</span>
            <span id="review-session-badge" style="background:rgba(255,255,255,0.2);color:white;font-size:10px;padding:2px 8px;border-radius:999px;">Loading…</span>
        </div>

        <div style="padding:10px 12px;border-bottom:1px solid #f3f4f6;">
            <button id="review-open-canvas-btn"
                style="width:100%;padding:8px;background:#7c3aed;color:white;border:none;border-radius:8px;
                       font-size:12px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                <svg style="width:12px;height:12px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                </svg>
                Annotate This Page
            </button>
            <button id="review-upload-annotate-btn"
                style="width:100%;padding:6px;background:transparent;color:#7c3aed;border:1px solid #e9d5ff;border-radius:8px;
                       font-size:11px;font-weight:600;cursor:pointer;margin-top:6px;display:flex;align-items:center;justify-content:center;gap:5px;">
                <svg style="width:11px;height:11px" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
                Upload Image &amp; Annotate
            </button>
        </div>

        <div id="review-page-annotations" style="max-height:140px;overflow-y:auto;">
            <div style="padding:10px 12px;color:#9ca3af;font-size:11px;text-align:center;">
                Checking for annotations…
            </div>
        </div>

        <div style="padding:8px 12px;border-top:1px solid #f3f4f6;display:flex;gap:6px;">
            ${isAdminPage ? '' : '<a href="/admin" style="flex:1;text-align:center;font-size:10px;font-weight:600;color:#6b7280;text-decoration:none;padding:5px;border-radius:6px;border:1px solid #e5e7eb;display:block;" target="_blank">Admin ↗</a>'}
            <a href="/review" style="flex:1;text-align:center;font-size:10px;font-weight:600;color:#6b7280;text-decoration:none;padding:5px;border-radius:6px;border:1px solid #e5e7eb;display:block;" target="_blank">Review Portal ↗</a>
        </div>`;

    document.body.appendChild(browsingPanel);

    document.getElementById('review-open-canvas-btn').addEventListener('click', () => openCanvas(false));
    document.getElementById('review-upload-annotate-btn').addEventListener('click', () => openCanvas(true));

    loadSessionInfo();
    loadPageAnnotations();
}

function removeBrowsingUI() {
    browsingPanel?.remove();
    browsingPanel = null;
}

async function loadSessionInfo() {
    const badge = document.getElementById('review-session-badge');
    if (!badge || !sessionId) return;
    try {
        const r        = await fetch('/admin/review/sessions', { headers: { Accept: 'application/json' } });
        const sessions = await r.json();
        const session  = sessions.find(s => String(s.id) === String(sessionId));
        if (session) badge.textContent = session.title;
    } catch { /* ignore */ }
}

async function loadPageAnnotations() {
    const container = document.getElementById('review-page-annotations');
    if (!container || !sessionId) return;

    let items = [];
    try {
        const r   = await fetch(`/admin/review/items?session_id=${sessionId}`, { headers: { Accept: 'application/json' } });
        const all = await r.json();
        items     = all.filter(item => item.page_url === window.location.href);
    } catch {
        container.innerHTML = '<div style="padding:10px 12px;color:#9ca3af;font-size:11px;text-align:center;">Could not load annotations.</div>';
        return;
    }

    if (!items.length) {
        container.innerHTML = '<div style="padding:10px 12px;color:#9ca3af;font-size:11px;text-align:center;">No annotations on this page yet.</div>';
        return;
    }

    const typeIcons   = { annotation: '✏️', bug: '🐛', suggestion: '💡', question: '❓' };
    const statusColor = { open: '#ef4444', in_progress: '#f59e0b', fixed: '#22c55e', approved: '#10b981', rejected: '#9ca3af', wont_fix: '#9ca3af' };

    let html = `<div style="padding:6px 12px 4px;font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">${items.length} annotation${items.length !== 1 ? 's' : ''} on this page</div>`;
    items.slice(0, 6).forEach(item => {
        const icon  = typeIcons[item.type] ?? '📌';
        const dot   = statusColor[item.status] ?? '#9ca3af';
        const label = (item.comment || item.page_title || 'Annotation').substring(0, 38);
        const status = (item.status || '').replace('_', ' ');
        html += `<div style="padding:7px 12px;border-top:1px solid #f9fafb;display:flex;gap:8px;align-items:flex-start;">
            <span style="font-size:14px;flex-shrink:0;line-height:1;">${icon}</span>
            <div style="min-width:0;flex:1;">
                <div style="font-size:11px;font-weight:600;color:#1f2937;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${label}</div>
                <div style="display:flex;align-items:center;gap:4px;margin-top:2px;">
                    <span style="width:5px;height:5px;border-radius:50%;background:${dot};display:inline-block;flex-shrink:0;"></span>
                    <span style="font-size:10px;color:#9ca3af;">${status}</span>
                </div>
            </div>
        </div>`;
    });
    if (items.length > 6) {
        html += `<div style="padding:6px 12px;font-size:10px;color:#9ca3af;text-align:center;">+${items.length - 6} more in Review Portal</div>`;
    }
    container.innerHTML = html;
}

// ─── Open / Close Canvas ──────────────────────────────────────────────────────
function openCanvas(uploadMode = false) {
    canvasActive     = true;
    hasUploadedImage = false;
    removeBrowsingUI();
    buildOverlay();
    buildToolbar();

    if (uploadMode) {
        setTimeout(() => triggerImageUpload(), 200);
    }
}

function closeCanvas(returnToBrowsing = true) {
    overlayEl?.remove();
    toolbarEl?.remove();
    fabricCanvas?.dispose();
    overlayEl = toolbarEl = fabricCanvas = null;
    history   = [];
    mouseDownPt = tempShape = null;
    isDrawingRect = isDrawingArrow = false;
    canvasActive = false;

    if (returnToBrowsing && reviewModeActive) showBrowsingUI();
}

// Escape key exits canvas mode
document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && canvasActive) closeCanvas();
});

// ─── Overlay (semi-transparent — page stays visible) ──────────────────────────
function buildOverlay() {
    overlayEl = document.createElement('div');
    overlayEl.id = 'review-overlay';
    Object.assign(overlayEl.style, {
        position: 'fixed', inset: '0',
        zIndex: '99990',
        background: 'rgba(15, 23, 42, 0.18)',
        overflow: 'hidden',
    });

    const canvasEl = document.createElement('canvas');
    canvasEl.id = 'review-fabric';
    Object.assign(canvasEl.style, { position: 'absolute', inset: '0', width: '100%', height: '100%' });
    overlayEl.appendChild(canvasEl);
    document.body.appendChild(overlayEl);

    fabricCanvas = new fabric.Canvas('review-fabric', {
        selection:       true,
        width:           window.innerWidth,
        height:          window.innerHeight,
        backgroundColor: null,
    });

    setTool('select');
    bindCanvasEvents();
}

// ─── Toolbar ──────────────────────────────────────────────────────────────────
const TOOLS = [
    { id: 'select', label: 'Select',    icon: '↖' },
    { id: 'draw',   label: 'Freehand',  icon: '✏️' },
    { id: 'box',    label: 'Box',       icon: '⬜' },
    { id: 'arrow',  label: 'Arrow',     icon: '→' },
    { id: 'text',   label: 'Text',      icon: 'T' },
];

const COLORS = ['#e11d48', '#2563eb', '#16a34a', '#d97706', '#7c3aed', '#000000'];

function buildToolbar() {
    toolbarEl = document.createElement('div');
    toolbarEl.id = 'review-toolbar';
    Object.assign(toolbarEl.style, {
        position: 'fixed',
        top: '50%', left: '16px',
        transform: 'translateY(-50%)',
        zIndex: '99995',
        background: 'white',
        borderRadius: '16px',
        padding: '12px 8px',
        display: 'flex',
        flexDirection: 'column',
        gap: '4px',
        boxShadow: '0 20px 40px rgba(0,0,0,0.25)',
        minWidth: '52px',
    });

    // Done button at top
    const doneBtn = makeToolBtn('✕', 'Done (Esc)', () => closeCanvas());
    doneBtn.title = 'Exit annotation mode (Esc)';
    doneBtn.style.background = '#fee2e2';
    doneBtn.style.borderRadius = '10px';
    doneBtn.style.color = '#dc2626';
    toolbarEl.appendChild(doneBtn);

    toolbarEl.appendChild(makeSep());

    // Drawing tools
    TOOLS.forEach(({ id, label, icon }) => {
        const btn = makeToolBtn(icon, label, () => setTool(id));
        btn.dataset.tool = id;
        toolbarEl.appendChild(btn);
    });

    toolbarEl.appendChild(makeSep());

    // Colors
    COLORS.forEach(color => {
        const swatch = document.createElement('button');
        Object.assign(swatch.style, {
            width: '26px', height: '26px',
            borderRadius: '50%',
            background: color,
            border: color === currentColor ? '3px solid #1e293b' : '2px solid transparent',
            cursor: 'pointer',
            margin: '0 auto', display: 'block',
            transition: 'border 0.1s',
        });
        swatch.dataset.swatch = color;
        swatch.addEventListener('click', () => {
            currentColor = color;
            applyColor(color);
            toolbarEl.querySelectorAll('[data-swatch]').forEach(s => {
                s.style.border = s.dataset.swatch === color ? '3px solid #1e293b' : '2px solid transparent';
            });
        });
        toolbarEl.appendChild(swatch);
    });

    toolbarEl.appendChild(makeSep());

    // Upload image
    toolbarEl.appendChild(makeToolBtn('🖼', 'Upload image to annotate', triggerImageUpload));

    // Undo
    toolbarEl.appendChild(makeToolBtn('↩', 'Undo', undoLast));

    // Clear
    toolbarEl.appendChild(makeToolBtn('🗑', 'Clear all', () => {
        fabricCanvas.clear();
        history = [];
        hasUploadedImage = false;
    }));

    toolbarEl.appendChild(makeSep());

    // Save
    const saveBtn = makeToolBtn('💾', 'Save annotation', openSaveModal);
    Object.assign(saveBtn.style, {
        background: '#7c3aed', color: 'white',
        borderRadius: '10px', padding: '8px',
    });
    toolbarEl.appendChild(saveBtn);

    document.body.appendChild(toolbarEl);
    highlightActiveTool();
}

function makeSep() {
    const sep = document.createElement('div');
    Object.assign(sep.style, { height: '1px', background: '#e5e7eb', margin: '4px 0' });
    return sep;
}

function makeToolBtn(icon, title, onClick) {
    const btn = document.createElement('button');
    btn.title = title;
    btn.innerHTML = typeof icon === 'string' && icon.length <= 2 ? icon : icon;
    Object.assign(btn.style, {
        width: '36px', height: '36px',
        borderRadius: '8px', border: 'none',
        background: 'transparent',
        fontSize: '16px', cursor: 'pointer',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        transition: 'background 0.1s', margin: '0 auto',
    });
    btn.addEventListener('mouseenter', () => { if (!btn.dataset.active) btn.style.background = '#f3f4f6'; });
    btn.addEventListener('mouseleave', () => { if (!btn.dataset.active) btn.style.background = btn.style.background === 'rgb(243, 244, 246)' ? 'transparent' : btn.style.background; });
    btn.addEventListener('click', onClick);
    return btn;
}

function highlightActiveTool() {
    toolbarEl?.querySelectorAll('[data-tool]').forEach(btn => {
        const on = btn.dataset.tool === currentTool;
        btn.style.background  = on ? '#ede9fe' : 'transparent';
        btn.dataset.active     = on ? 'true' : '';
    });
}

// ─── Image Upload ─────────────────────────────────────────────────────────────
function triggerImageUpload() {
    const input = document.createElement('input');
    input.type   = 'file';
    input.accept = 'image/*';
    input.onchange = e => {
        const file = e.target.files?.[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => {
            const url = ev.target.result;
            fabric.Image.fromURL(url, img => {
                const maxW = window.innerWidth * 0.8;
                const maxH = window.innerHeight * 0.8;
                const scale = Math.min(maxW / img.width, maxH / img.height, 1);
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

// ─── Tool Switching ───────────────────────────────────────────────────────────
function setTool(tool) {
    currentTool = tool;
    if (!fabricCanvas) return;

    fabricCanvas.isDrawingMode = tool === 'draw';
    fabricCanvas.selection     = tool === 'select';
    fabricCanvas.defaultCursor = tool === 'text' ? 'text' : (tool === 'select' ? 'default' : 'crosshair');

    if (tool === 'draw') {
        fabricCanvas.freeDrawingBrush.color = currentColor;
        fabricCanvas.freeDrawingBrush.width = 3;
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
        if (obj.type === 'i-text' || obj.type === 'text') {
            obj.set('fill', color);
        } else {
            obj.set('stroke', color);
        }
    });
    if (fabricCanvas.getActiveObjects().length) fabricCanvas.requestRenderAll();
}

// ─── Canvas Events ────────────────────────────────────────────────────────────
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
                fill: 'transparent', stroke: currentColor, strokeWidth: 3,
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
        if (tempShape) {
            tempShape.set({ selectable: true, evented: true });
            tempShape = null;
            pushHistory();
        }
        mouseDownPt = null; isDrawingRect = false; isDrawingArrow = false;
    });

    fabricCanvas.on('object:added', () => {
        if (fabricCanvas.isDrawingMode) pushHistory();
    });
}

function buildArrow(x1, y1, x2, y2, color, selectable = true) {
    const angle = (Math.atan2(y2 - y1, x2 - x1) * 180) / Math.PI;
    const line  = new fabric.Line([x1, y1, x2, y2], { stroke: color, strokeWidth: 3, selectable: false, evented: false });
    const head  = new fabric.Triangle({
        width: 14, height: 18, fill: color,
        left: x2, top: y2, angle: angle + 90,
        originX: 'center', originY: 'center',
        selectable: false, evented: false,
    });
    return new fabric.Group([line, head], { selectable, evented: selectable });
}

// ─── History ──────────────────────────────────────────────────────────────────
function pushHistory() {
    history.push(JSON.stringify(fabricCanvas));
    if (history.length > 50) history.shift();
}

function undoLast() {
    if (!history.length) return;
    history.pop();
    const prev = history[history.length - 1];
    if (prev) {
        fabricCanvas.loadFromJSON(prev, () => fabricCanvas.requestRenderAll());
    } else {
        fabricCanvas.clear();
    }
}

// ─── Screenshot Capture ───────────────────────────────────────────────────────
async function captureForSave() {
    // If user uploaded an image, just export the fabric canvas directly
    if (hasUploadedImage) {
        try {
            return fabricCanvas.toDataURL({ format: 'jpeg', quality: 0.85 });
        } catch { return null; }
    }

    // Otherwise: hide overlay, screenshot page, composite fabric on top
    try {
        overlayEl.style.visibility = 'hidden';
        const pageCanvas = await html2canvas(document.documentElement, {
            useCORS: true, allowTaint: true, scale: 1,
            width: window.innerWidth, height: window.innerHeight,
            scrollX: 0, scrollY: 0,
        });
        overlayEl.style.visibility = '';

        // Composite annotations on top
        const ctx = pageCanvas.getContext('2d');
        if (fabricCanvas?.lowerCanvasEl) {
            ctx.drawImage(fabricCanvas.lowerCanvasEl, 0, 0);
        }
        return pageCanvas.toDataURL('image/jpeg', 0.85);
    } catch {
        if (overlayEl) overlayEl.style.visibility = '';
        return null;
    }
}

// ─── Save Modal ───────────────────────────────────────────────────────────────
function openSaveModal() {
    document.getElementById('review-save-modal')?.remove();

    const modal = document.createElement('div');
    modal.id = 'review-save-modal';
    Object.assign(modal.style, {
        position: 'fixed', inset: '0',
        zIndex: '99999',
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        background: 'rgba(0,0,0,0.55)',
    });

    modal.innerHTML = `
        <div style="background:white;border-radius:16px;padding:24px;width:440px;max-width:90vw;
                    box-shadow:0 24px 48px rgba(0,0,0,0.3);font-family:system-ui,sans-serif;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0;">Save Annotation</h3>
                <button id="review-modal-close"
                    style="background:none;border:none;font-size:18px;cursor:pointer;color:#9ca3af;padding:0 4px;">✕</button>
            </div>
            <p style="font-size:11px;color:#9ca3af;margin:0 0 14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${window.location.href}</p>

            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px;">Comment</label>
            <textarea id="review-comment-input" rows="3"
                placeholder="Describe the issue, suggestion, or question…"
                style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;
                       font-size:13px;resize:vertical;outline:none;box-sizing:border-box;font-family:inherit;"></textarea>

            <div style="display:flex;gap:8px;margin-top:10px;">
                <div style="flex:1;">
                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px;">Type</label>
                    <select id="review-type-select"
                        style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:6px 8px;font-size:12px;background:white;">
                        <option value="annotation">✏️ Annotation</option>
                        <option value="bug">🐛 Bug</option>
                        <option value="suggestion">💡 Suggestion</option>
                        <option value="question">❓ Question</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px;">Priority</label>
                    <select id="review-priority-select"
                        style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:6px 8px;font-size:12px;background:white;">
                        <option value="normal">Normal</option>
                        <option value="high">🔴 High</option>
                        <option value="low">Low</option>
                    </select>
                </div>
            </div>

            <div id="review-save-error" style="display:none;color:#dc2626;font-size:12px;margin-top:8px;"></div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px;">
                <button id="review-cancel-btn"
                    style="padding:8px 16px;border-radius:8px;border:1px solid #d1d5db;
                           background:white;font-size:13px;font-weight:500;cursor:pointer;">Cancel</button>
                <button id="review-save-btn"
                    style="padding:8px 22px;border-radius:8px;border:none;
                           background:#7c3aed;color:white;font-size:13px;font-weight:600;cursor:pointer;">Save</button>
            </div>
        </div>`;

    document.body.appendChild(modal);

    document.getElementById('review-modal-close').addEventListener('click', () => modal.remove());
    document.getElementById('review-cancel-btn').addEventListener('click', () => modal.remove());
    document.getElementById('review-save-btn').addEventListener('click', () => submitAnnotation(modal));
    modal.addEventListener('click', e => { if (e.target === modal) modal.remove(); });
}

async function submitAnnotation(modal) {
    const comment  = document.getElementById('review-comment-input').value.trim();
    const type     = document.getElementById('review-type-select').value;
    const priority = document.getElementById('review-priority-select').value;
    const errEl    = document.getElementById('review-save-error');
    const saveBtn  = document.getElementById('review-save-btn');

    errEl.style.display = 'none';
    saveBtn.textContent = 'Saving…';
    saveBtn.disabled    = true;

    try {
        const screenshotDataUrl = await captureForSave();
        const fabricJson        = JSON.stringify(fabricCanvas.toJSON());
        const csrf              = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

        const resp = await fetch('/admin/review/items', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                review_session_id: parseInt(sessionId, 10),
                page_url:          window.location.href,
                page_title:        document.title,
                screenshot:        screenshotDataUrl,
                fabric_json:       fabricJson,
                comment,
                type,
                priority,
            }),
        });

        if (!resp.ok) {
            const err = await resp.json().catch(() => ({}));
            throw new Error(err.message || `Server error ${resp.status}`);
        }

        modal.remove();
        showToast('Annotation saved ✓');

        // Clear canvas but stay in annotating mode for the next annotation
        fabricCanvas.clear();
        history = [];
        hasUploadedImage = false;
    } catch (err) {
        errEl.textContent   = err.message;
        errEl.style.display = 'block';
        saveBtn.textContent = 'Save';
        saveBtn.disabled    = false;
    }
}

// ─── Session Picker ───────────────────────────────────────────────────────────
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
            background: 'rgba(0,0,0,0.5)',
        });

        const sessionOptions = sessions.length
            ? sessions.map(s => `<option value="${s.id}">${s.title} (${s.items_count ?? 0} items)</option>`).join('')
            : '<option disabled>No open sessions — create one below</option>';

        modal.innerHTML = `
            <div style="background:white;border-radius:16px;padding:24px;width:400px;max-width:90vw;
                        box-shadow:0 24px 48px rgba(0,0,0,0.3);font-family:system-ui,sans-serif;">
                <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 4px;">Start Review Session</h3>
                <p style="font-size:12px;color:#9ca3af;margin:0 0 16px;">Choose or create a session to group your annotations.</p>

                ${sessions.length ? `
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Use existing session</label>
                <select id="review-session-select"
                    style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;margin-bottom:10px;background:white;">
                    ${sessionOptions}
                </select>
                <button id="review-use-existing"
                    style="width:100%;padding:9px;border-radius:8px;border:none;background:#7c3aed;
                           color:white;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:12px;">
                    Use Selected Session
                </button>
                <div style="text-align:center;font-size:11px;color:#9ca3af;margin-bottom:12px;">— or create new —</div>
                ` : ''}

                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px;">Create new session</label>
                <input id="review-new-session-name" type="text" placeholder="e.g. Homepage Feedback Round 1"
                    style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;
                           font-size:13px;margin-bottom:8px;box-sizing:border-box;"/>
                <button id="review-create-session"
                    style="width:100%;padding:9px;border-radius:8px;border:none;background:#1e293b;
                           color:white;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:8px;">
                    Create &amp; Start
                </button>
                <button id="review-cancel-session"
                    style="width:100%;padding:7px;border-radius:8px;border:1px solid #d1d5db;
                           background:white;font-size:13px;cursor:pointer;">Cancel</button>
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
                    body: JSON.stringify({ title }),
                });
                const s = await r.json();
                close(String(s.id));
            } catch (e) {
                alert('Could not create session: ' + e.message);
            }
        });
    });
}

// ─── Toast ────────────────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.createElement('div');
    Object.assign(t.style, {
        position: 'fixed', bottom: '80px', left: '50%',
        transform: 'translateX(-50%)',
        zIndex: '999999',
        background: '#16a34a', color: 'white',
        padding: '10px 20px', borderRadius: '999px',
        fontSize: '13px', fontWeight: '600',
        boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
        pointerEvents: 'none',
        transition: 'opacity 0.4s',
        whiteSpace: 'nowrap',
    });
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2500);
}
