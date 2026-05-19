import { fabric } from 'fabric';
import html2canvas from 'html2canvas';

// ─── State ────────────────────────────────────────────────────────────────────
let active         = false;
let fabricCanvas   = null;
let overlayEl      = null;
let toolbarEl      = null;
let sessionId      = null;
let currentTool    = 'select';
let currentColor   = '#e11d48';
let isDrawingRect  = false;
let isDrawingArrow = false;
let rectStart      = null;
let arrowLine      = null;
let arrowHead      = null;
let history        = [];

// ─── Bootstrap ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    sessionId = localStorage.getItem('vortex_review_session_id') || null;
    injectToggleButton();
});

// ─── Toggle button (bottom-left FAB) ─────────────────────────────────────────
function injectToggleButton() {
    const btn = document.createElement('div');
    btn.id    = 'review-mode-fab';
    btn.innerHTML = `
        <button id="review-toggle-btn"
            class="fixed bottom-6 left-6 z-[99998] flex items-center gap-2 rounded-full
                   bg-gray-800 px-4 py-2.5 text-xs font-semibold text-white shadow-xl
                   transition-all duration-200 hover:scale-105 hover:bg-gray-700"
            title="Toggle Review Mode">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            <span id="review-fab-label">Review Mode</span>
        </button>`;
    document.body.appendChild(btn);
    document.getElementById('review-toggle-btn').addEventListener('click', handleToggle);
    updateFabState();
}

function updateFabState() {
    const label = document.getElementById('review-fab-label');
    const btn   = document.getElementById('review-toggle-btn');
    if (!label || !btn) return;
    if (active) {
        label.textContent = 'Exit Review';
        btn.classList.replace('bg-gray-800', 'bg-rose-600');
        btn.classList.replace('hover:bg-gray-700', 'hover:bg-rose-700');
    } else {
        label.textContent = 'Review Mode';
        btn.classList.replace('bg-rose-600', 'bg-gray-800');
        btn.classList.replace('hover:bg-rose-700', 'hover:bg-gray-700');
    }
}

// ─── Toggle logic ─────────────────────────────────────────────────────────────
async function handleToggle() {
    if (active) {
        deactivate();
    } else {
        await activate();
    }
}

async function activate() {
    if (!sessionId) {
        const chosen = await pickSession();
        if (!chosen) return;
        sessionId = chosen;
        localStorage.setItem('vortex_review_session_id', sessionId);
    }

    // Capture screenshot BEFORE overlay appears
    const screenshot = await captureScreen();

    buildOverlay(screenshot);
    buildToolbar();
    active = true;
    updateFabState();
}

function deactivate() {
    overlayEl?.remove();
    toolbarEl?.remove();
    fabricCanvas?.dispose();
    overlayEl  = null;
    toolbarEl  = null;
    fabricCanvas = null;
    active     = false;
    updateFabState();
}

// ─── Screen capture ───────────────────────────────────────────────────────────
async function captureScreen() {
    try {
        const canvas = await html2canvas(document.documentElement, {
            useCORS: true,
            allowTaint: true,
            scale: 1,
            width: window.innerWidth,
            height: window.innerHeight,
            scrollX: 0,
            scrollY: 0,
        });
        return canvas.toDataURL('image/jpeg', 0.85);
    } catch {
        return null;
    }
}

// ─── Overlay ──────────────────────────────────────────────────────────────────
function buildOverlay(screenshot) {
    overlayEl = document.createElement('div');
    overlayEl.id = 'review-overlay';
    Object.assign(overlayEl.style, {
        position: 'fixed', inset: '0',
        zIndex: '99990',
        overflow: 'hidden',
    });

    // Background screenshot
    if (screenshot) {
        const bg = document.createElement('img');
        bg.src = screenshot;
        Object.assign(bg.style, {
            position: 'absolute', inset: '0',
            width: '100%', height: '100%',
            objectFit: 'cover', userSelect: 'none',
            pointerEvents: 'none',
        });
        overlayEl.appendChild(bg);
    } else {
        overlayEl.style.background = 'rgba(0,0,0,0.4)';
    }

    // Fabric canvas element
    const canvasEl = document.createElement('canvas');
    canvasEl.id = 'review-fabric';
    Object.assign(canvasEl.style, {
        position: 'absolute', inset: '0',
        width: '100%', height: '100%',
    });
    overlayEl.appendChild(canvasEl);
    document.body.appendChild(overlayEl);

    // Init Fabric
    fabricCanvas = new fabric.Canvas('review-fabric', {
        selection: true,
        width: window.innerWidth,
        height: window.innerHeight,
    });

    setTool('select');
    bindCanvasEvents();
}

// ─── Toolbar ──────────────────────────────────────────────────────────────────
const TOOLS = [
    { id: 'select',  label: 'Select',  icon: '↖' },
    { id: 'draw',    label: 'Draw',    icon: '✏️' },
    { id: 'box',     label: 'Box',     icon: '⬜' },
    { id: 'arrow',   label: 'Arrow',   icon: '→' },
    { id: 'text',    label: 'Text',    icon: 'T' },
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

    // Tool buttons
    TOOLS.forEach(({ id, label, icon }) => {
        const btn = makeToolBtn(icon, label, () => setTool(id));
        btn.dataset.tool = id;
        toolbarEl.appendChild(btn);
    });

    // Separator
    const sep = document.createElement('div');
    Object.assign(sep.style, { height: '1px', background: '#e5e7eb', margin: '4px 0' });
    toolbarEl.appendChild(sep);

    // Color swatches
    COLORS.forEach(color => {
        const swatch = document.createElement('button');
        Object.assign(swatch.style, {
            width: '28px', height: '28px',
            borderRadius: '50%',
            background: color,
            border: color === currentColor ? '3px solid #1e293b' : '2px solid transparent',
            cursor: 'pointer',
            margin: '0 auto',
            display: 'block',
            transition: 'border 0.1s',
        });
        swatch.addEventListener('click', () => {
            currentColor = color;
            applyColor(color);
            toolbarEl.querySelectorAll('[data-swatch]').forEach(s => {
                s.style.border = s.dataset.swatch === color
                    ? '3px solid #1e293b' : '2px solid transparent';
            });
        });
        swatch.dataset.swatch = color;
        toolbarEl.appendChild(swatch);
    });

    // Separator
    const sep2 = document.createElement('div');
    Object.assign(sep2.style, { height: '1px', background: '#e5e7eb', margin: '4px 0' });
    toolbarEl.appendChild(sep2);

    // Undo
    toolbarEl.appendChild(makeToolBtn('↩', 'Undo', undoLast));

    // Clear
    toolbarEl.appendChild(makeToolBtn('🗑', 'Clear', () => {
        fabricCanvas.clear();
        history = [];
    }));

    // Save
    const saveBtn = makeToolBtn('💾', 'Save annotation', openSaveModal);
    saveBtn.style.background = '#7c3aed';
    saveBtn.style.color = 'white';
    saveBtn.style.borderRadius = '10px';
    saveBtn.style.padding = '8px';
    toolbarEl.appendChild(saveBtn);

    document.body.appendChild(toolbarEl);
    highlightActiveTool();
}

function makeToolBtn(icon, title, onClick) {
    const btn = document.createElement('button');
    btn.textContent = icon;
    btn.title = title;
    Object.assign(btn.style, {
        width: '36px', height: '36px',
        borderRadius: '8px',
        border: 'none',
        background: 'transparent',
        fontSize: '16px',
        cursor: 'pointer',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        transition: 'background 0.1s',
        margin: '0 auto',
    });
    btn.addEventListener('mouseenter', () => { if (!btn.dataset.active) btn.style.background = '#f3f4f6'; });
    btn.addEventListener('mouseleave', () => { if (!btn.dataset.active) btn.style.background = 'transparent'; });
    btn.addEventListener('click', onClick);
    return btn;
}

function highlightActiveTool() {
    toolbarEl?.querySelectorAll('[data-tool]').forEach(btn => {
        const isActive = btn.dataset.tool === currentTool;
        btn.style.background = isActive ? '#ede9fe' : 'transparent';
        btn.dataset.active   = isActive ? 'true' : '';
    });
}

// ─── Tool switching ───────────────────────────────────────────────────────────
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
    if (currentTool === 'draw') {
        fabricCanvas.freeDrawingBrush.color = color;
    }
    const active = fabricCanvas.getActiveObjects();
    active.forEach(obj => {
        if (obj.type === 'i-text' || obj.type === 'text') {
            obj.set('fill', color);
        } else {
            obj.set('stroke', color);
        }
    });
    if (active.length) fabricCanvas.requestRenderAll();
}

// ─── Canvas event binding ─────────────────────────────────────────────────────
let mouseDownPt = null;
let tempShape   = null;

function bindCanvasEvents() {
    fabricCanvas.on('mouse:down', ({ e }) => {
        const pt = fabricCanvas.getPointer(e);

        if (currentTool === 'text') {
            const text = new fabric.IText('Type here…', {
                left: pt.x, top: pt.y,
                fontSize: 18,
                fill: currentColor,
                fontFamily: 'sans-serif',
                selectable: true,
                editable: true,
            });
            fabricCanvas.add(text);
            fabricCanvas.setActiveObject(text);
            text.enterEditing();
            pushHistory();
            return;
        }

        if (currentTool === 'box' || currentTool === 'arrow') {
            mouseDownPt = pt;
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
                left:        Math.min(mouseDownPt.x, pt.x),
                top:         Math.min(mouseDownPt.y, pt.y),
                width:       Math.abs(pt.x - mouseDownPt.x),
                height:      Math.abs(pt.y - mouseDownPt.y),
                fill:        'transparent',
                stroke:      currentColor,
                strokeWidth: 3,
                selectable:  false,
                evented:     false,
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
        mouseDownPt    = null;
        isDrawingRect  = false;
        isDrawingArrow = false;
    });

    fabricCanvas.on('object:added', () => {
        if (fabricCanvas.isDrawingMode) pushHistory();
    });
}

function buildArrow(x1, y1, x2, y2, color, selectable = true) {
    const angle = (Math.atan2(y2 - y1, x2 - x1) * 180) / Math.PI;

    const line = new fabric.Line([x1, y1, x2, y2], {
        stroke: color, strokeWidth: 3,
        selectable: false, evented: false,
    });

    const head = new fabric.Triangle({
        width: 14, height: 18,
        fill: color,
        left: x2, top: y2,
        angle: angle + 90,
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
    if (history.length === 0) return;
    history.pop();
    const prev = history[history.length - 1];
    if (prev) {
        fabricCanvas.loadFromJSON(prev, () => fabricCanvas.requestRenderAll());
    } else {
        fabricCanvas.clear();
    }
}

// ─── Save modal ───────────────────────────────────────────────────────────────
function openSaveModal() {
    const existing = document.getElementById('review-save-modal');
    if (existing) existing.remove();

    const modal = document.createElement('div');
    modal.id = 'review-save-modal';
    Object.assign(modal.style, {
        position: 'fixed', inset: '0',
        zIndex: '99999',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        background: 'rgba(0,0,0,0.55)',
    });

    modal.innerHTML = `
        <div style="background:white;border-radius:16px;padding:24px;width:420px;max-width:90vw;box-shadow:0 24px 48px rgba(0,0,0,0.3);">
            <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 4px">Save Annotation</h3>
            <p style="font-size:12px;color:#6b7280;margin:0 0 16px">${window.location.pathname}</p>

            <label style="display:block;font-size:12px;font-weight:600;color:#374151;margin-bottom:6px">Comment</label>
            <textarea id="review-comment-input" rows="3"
                placeholder="Describe the issue or suggestion…"
                style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:8px 10px;font-size:13px;resize:vertical;outline:none;box-sizing:border-box;"
            ></textarea>

            <div style="display:flex;gap:8px;margin-top:8px">
                <div style="flex:1">
                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Type</label>
                    <select id="review-type-select"
                        style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:6px 8px;font-size:12px;">
                        <option value="annotation">Annotation</option>
                        <option value="bug">Bug</option>
                        <option value="suggestion">Suggestion</option>
                        <option value="question">Question</option>
                    </select>
                </div>
                <div style="flex:1">
                    <label style="display:block;font-size:11px;font-weight:600;color:#374151;margin-bottom:4px">Priority</label>
                    <select id="review-priority-select"
                        style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:6px 8px;font-size:12px;">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="low">Low</option>
                    </select>
                </div>
            </div>

            <div id="review-save-error" style="display:none;color:#dc2626;font-size:12px;margin-top:8px;"></div>

            <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:16px">
                <button id="review-cancel-btn"
                    style="padding:8px 16px;border-radius:8px;border:1px solid #d1d5db;background:white;font-size:13px;font-weight:500;cursor:pointer;">
                    Cancel
                </button>
                <button id="review-save-btn"
                    style="padding:8px 20px;border-radius:8px;border:none;background:#7c3aed;color:white;font-size:13px;font-weight:600;cursor:pointer;">
                    Save
                </button>
            </div>
        </div>`;

    document.body.appendChild(modal);

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
        const screenshotDataUrl = await captureCanvas();
        const fabricJson        = JSON.stringify(fabricCanvas.toJSON());

        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        const csrf     = csrfMeta?.content ?? '';

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

        // Clear canvas for next annotation
        fabricCanvas.clear();
        history = [];
    } catch (err) {
        errEl.textContent   = err.message;
        errEl.style.display = 'block';
        saveBtn.textContent = 'Save';
        saveBtn.disabled    = false;
    }
}

async function captureCanvas() {
    return new Promise(resolve => {
        const el = document.getElementById('review-fabric');
        if (!el) { resolve(null); return; }
        const html2 = html2canvas;
        html2(overlayEl, { useCORS: true, scale: 1 })
            .then(c => resolve(c.toDataURL('image/jpeg', 0.8)))
            .catch(() => resolve(null));
    });
}

// ─── Session picker ───────────────────────────────────────────────────────────
async function pickSession() {
    return new Promise(async resolve => {
        let sessions = [];
        try {
            const r = await fetch('/admin/review/sessions', {
                headers: { 'Accept': 'application/json' },
            });
            sessions = await r.json();
        } catch { /* offline */ }

        const modal = document.createElement('div');
        Object.assign(modal.style, {
            position: 'fixed', inset: '0',
            zIndex: '99999',
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
            background: 'rgba(0,0,0,0.5)',
        });

        const sessionOptions = sessions.length
            ? sessions.map(s => `<option value="${s.id}">${s.title} (${s.items_count ?? 0} items)</option>`).join('')
            : '<option disabled>No open sessions — create one below</option>';

        modal.innerHTML = `
            <div style="background:white;border-radius:16px;padding:24px;width:380px;max-width:90vw;box-shadow:0 24px 48px rgba(0,0,0,0.3);">
                <h3 style="font-size:16px;font-weight:700;color:#111827;margin:0 0 12px">Start Review Session</h3>

                ${sessions.length ? `
                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px">Use existing session</label>
                <select id="review-session-select"
                    style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;font-size:13px;margin-bottom:12px;">
                    ${sessionOptions}
                </select>
                <button id="review-use-existing"
                    style="width:100%;padding:9px;border-radius:8px;border:none;background:#7c3aed;color:white;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:12px;">
                    Use Selected Session
                </button>
                <div style="text-align:center;font-size:11px;color:#9ca3af;margin-bottom:12px">— or —</div>
                ` : ''}

                <label style="font-size:12px;font-weight:600;color:#374151;display:block;margin-bottom:6px">Create new session</label>
                <input id="review-new-session-name" type="text" placeholder="Session title…"
                    style="width:100%;border:1px solid #d1d5db;border-radius:8px;padding:7px 10px;font-size:13px;margin-bottom:8px;box-sizing:border-box;"/>
                <button id="review-create-session"
                    style="width:100%;padding:9px;border-radius:8px;border:none;background:#1e293b;color:white;font-size:13px;font-weight:600;cursor:pointer;margin-bottom:8px;">
                    Create &amp; Start
                </button>
                <button id="review-cancel-session"
                    style="width:100%;padding:7px;border-radius:8px;border:1px solid #d1d5db;background:white;font-size:13px;cursor:pointer;">
                    Cancel
                </button>
            </div>`;

        document.body.appendChild(modal);

        const close = val => { modal.remove(); resolve(val); };

        document.getElementById('review-cancel-session')?.addEventListener('click', () => close(null));

        document.getElementById('review-use-existing')?.addEventListener('click', () => {
            const sel = document.getElementById('review-session-select');
            close(sel?.value || null);
        });

        document.getElementById('review-create-session')?.addEventListener('click', async () => {
            const title = document.getElementById('review-new-session-name').value.trim();
            if (!title) return;
            try {
                const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const r    = await fetch('/admin/review/sessions', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ title }),
                });
                const session = await r.json();
                close(String(session.id));
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
        background: '#16a34a',
        color: 'white',
        padding: '10px 20px',
        borderRadius: '999px',
        fontSize: '13px',
        fontWeight: '600',
        boxShadow: '0 4px 12px rgba(0,0,0,0.2)',
        pointerEvents: 'none',
        transition: 'opacity 0.4s',
    });
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 400); }, 2500);
}
