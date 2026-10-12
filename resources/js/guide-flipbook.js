// Page-turning reader for the PDF guides on Help & Guides.
//
// The PDF stays the single source: pdf.js renders each page to an image and
// StPageFlip binds them into a book, so the flip view always matches what
// "Download PDF" hands out. Loaded on demand from app.js only when a
// [data-vx-flipbook] element is on the page.
import { PageFlip } from 'page-flip/dist/js/page-flip.module.js';
import 'page-flip/src/Style/stPageFlip.css';
import '../css/guide-flipbook.css';
import * as pdfjs from 'pdfjs-dist';
// Run pdf.js's parser in this bundle instead of loading pdf.worker.mjs as a
// separate file: Apache serves .mjs with a non-JavaScript MIME type, which
// browsers refuse for module workers, so the reader failed in production.
// The guides are a few pages, so parsing on the main thread is fine.
import * as pdfjsWorker from 'pdfjs-dist/build/pdf.worker.min.mjs';

globalThis.pdfjsWorker = pdfjsWorker;

const instances = new Map();

// Below this width there is no room for a readable two-page spread.
const SPREAD_MIN = 700;
const MIN_PAGE = 280;
// Cap on rendered pixels per page edge, so a 200% zoom on a dense screen
// cannot build images the device struggles to hold.
const MAX_RENDER = 2400;
const ZOOM = [
    { fit: 'page', label: 'Whole page' },
    { fit: 'width', scale: 1, label: 'Fit width' },
    { fit: 'width', scale: 1.5, label: '150%' },
    { fit: 'width', scale: 2, label: '200%' },
];
const DEFAULT_ZOOM = 1;

const renderPage = async (pdf, number, targetWidth) => {
    const page = await pdf.getPage(number);
    const base = page.getViewport({ scale: 1 });
    const viewport = page.getViewport({ scale: targetWidth / base.width });
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(viewport.width);
    canvas.height = Math.round(viewport.height);
    await page.render({ canvasContext: canvas.getContext('2d'), viewport }).promise;
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    page.cleanup();
    return { url: URL.createObjectURL(blob), width: base.width, height: base.height };
};

async function mount(root) {
    const book = root.querySelector('[data-book]');
    const status = root.querySelector('[data-status]');
    const controls = root.querySelector('[data-controls]');
    const indicator = root.querySelector('[data-page]');
    // The element that scrolls; falls back to the root for older markup.
    const scroller = root.querySelector('[data-scroll]') || root;
    const state = { flip: null, urls: [], imgs: [], cancelled: false, task: null, pdf: null, generation: 0, onResize: null };
    instances.set(root, state);

    try {
        state.task = pdfjs.getDocument({ url: root.dataset.src, withCredentials: true });
        const pdf = state.pdf = await state.task.promise;
        const first = (await pdf.getPage(1)).getViewport({ scale: 1 });
        const ratio = first.width / first.height;
        const stageEl = root.querySelector('.vx-flipbook-stage');

        // Layout is measured from the space the reader actually has, so it
        // follows the window, rotation and full screen. Zoom levels are
        // relative to "fit width", which is where the text is readable.
        const layout = () => {
            // clientWidth already leaves out the scrollbar gutter (reserved in
            // CSS), so a fit-width book never spills into a sideways scroll.
            const cs = getComputedStyle(scroller);
            const availW = scroller.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
            const availH = scroller.clientHeight - parseFloat(cs.paddingTop) - parseFloat(cs.paddingBottom)
                - (scroller === root ? (controls.offsetHeight || 64) + 16 : 0);
            // Spread vs single page follows the window, never the zoom, so
            // zooming in on a phone does not suddenly show two pages.
            const perRow = availW >= SPREAD_MIN ? 2 : 1;
            const level = ZOOM[state.zoom];
            const width = level.fit === 'page'
                ? Math.min(availW, Math.max(availH, 200) * ratio * perRow)
                : availW * level.scale;
            return { width: Math.max(MIN_PAGE, Math.round(width)), perRow };
        };

        // Rendered pixels per page = the page's on-screen width × the
        // device pixel ratio, so pages stay sharp on any screen without
        // building images larger than they are shown.
        const pixelsFor = ({ width, perRow }) =>
            Math.min(MAX_RENDER, Math.max(600, Math.ceil((width / perRow) * (window.devicePixelRatio || 1))));

        state.zoom = DEFAULT_ZOOM;
        const initial = layout();
        state.rendered = pixelsFor(initial);

        const pages = [];
        for (let n = 1; n <= pdf.numPages; n++) {
            if (state.cancelled) return;
            status.textContent = `Preparing page ${n} of ${pdf.numPages}…`;
            pages.push(await renderPage(pdf, n, state.rendered));
        }
        if (state.cancelled) return;
        state.urls = pages.map((p) => p.url);

        state.imgs = [];
        const elements = pages.map((p, i) => {
            const el = document.createElement('div');
            el.className = 'vx-flip-page';
            el.dataset.density = 'soft';
            const img = document.createElement('img');
            img.src = p.url;
            img.alt = `${root.dataset.title || 'Guide'} – page ${i + 1}`;
            img.draggable = false;
            el.appendChild(img);
            state.imgs.push(img);
            return el;
        });

        const { width, height } = pages[0];
        stageEl.style.maxWidth = 'none';
        stageEl.style.width = `${initial.width}px`;
        state.flip = new PageFlip(book, {
            width, height, size: 'stretch',
            minWidth: MIN_PAGE / 2, maxWidth: 2400,
            minHeight: Math.round((MIN_PAGE / 2) * height / width), maxHeight: Math.round(2400 * height / width),
            // No cover: the guide opens on pages 1 and 2 side by side.
            showCover: false, usePortrait: true, autoSize: true,
            maxShadowOpacity: 0.35, flippingTime: 650, mobileScrollSupport: true,
            // Clicking or scrolling around a zoomed page must not turn it.
            disableFlipByClick: true,
        });
        state.flip.loadFromHTML(elements);

        // Re-render sharper when the pages are shown larger than they were
        // drawn (zoom, a bigger window, full screen). Visible pages first.
        const sharpen = async (target) => {
            if (target <= state.rendered * 1.05) return;
            const generation = ++state.generation;
            state.rendered = target;
            const start = state.flip.getCurrentPageIndex();
            const order = [...state.imgs.keys()].sort((a, b) => Math.abs(a - start) - Math.abs(b - start));
            for (const i of order) {
                if (state.cancelled || generation !== state.generation) return;
                const { url } = await renderPage(pdf, i + 1, target);
                if (state.cancelled || generation !== state.generation) { URL.revokeObjectURL(url); return; }
                URL.revokeObjectURL(state.urls[i]);
                state.urls[i] = url;
                state.imgs[i].src = url;
            }
        };

        const zoomLabel = root.querySelector('[data-zoom]');
        const settings = state.flip.getSettings();
        const apply = () => {
            const next = layout();
            stageEl.style.width = `${next.width}px`;
            // page-flip picks portrait when the book is narrower than twice
            // its minWidth, and reads that setting on every layout. Point it
            // at our own choice, and drop the fixed min-width it set at start.
            settings.minWidth = next.perRow === 1 ? next.width : 50;
            book.style.minWidth = '0';
            state.flip.update();
            if (zoomLabel) zoomLabel.textContent = ZOOM[state.zoom].label;
            root.querySelector('[data-zoom-out]')?.toggleAttribute('disabled', state.zoom === 0);
            root.querySelector('[data-zoom-in]')?.toggleAttribute('disabled', state.zoom === ZOOM.length - 1);
            sharpen(pixelsFor(next));
            update();
        };
        const setZoom = (z) => { state.zoom = Math.max(0, Math.min(ZOOM.length - 1, z)); apply(); };

        const total = pages.length;
        const update = () => {
            const i = state.flip.getCurrentPageIndex();
            const spread = state.flip.getOrientation() === 'landscape' && i + 1 < total;
            indicator.textContent = spread ? `Pages ${i + 1}–${i + 2} of ${total}` : `Page ${i + 1} of ${total}`;
            root.querySelector('[data-prev]').disabled = i === 0;
            root.querySelector('[data-next]').disabled = i + (spread ? 2 : 1) >= total;
        };
        state.flip.on('flip', () => { update(); scroller.scrollTo({ top: 0 }); });
        state.flip.on('changeOrientation', update);

        root.querySelector('[data-prev]').addEventListener('click', () => state.flip.flipPrev());
        root.querySelector('[data-next]').addEventListener('click', () => state.flip.flipNext());
        root.querySelector('[data-zoom-out]')?.addEventListener('click', () => setZoom(state.zoom - 1));
        root.querySelector('[data-zoom-in]')?.addEventListener('click', () => setZoom(state.zoom + 1));
        root.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { e.preventDefault(); state.flip.flipPrev(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); state.flip.flipNext(); }
            if (e.key === '+' || e.key === '=') { e.preventDefault(); setZoom(state.zoom + 1); }
            if (e.key === '-') { e.preventDefault(); setZoom(state.zoom - 1); }
            if (e.key === '0') { e.preventDefault(); setZoom(DEFAULT_ZOOM); }
        });
        const fullscreen = root.querySelector('[data-fullscreen]');
        if (root.requestFullscreen) {
            fullscreen.addEventListener('click', () => document.fullscreenElement ? document.exitFullscreen() : root.requestFullscreen());
        } else {
            fullscreen.hidden = true;
        }

        let resizeTimer = 0;
        state.onResize = () => { clearTimeout(resizeTimer); resizeTimer = setTimeout(() => root.isConnected && apply(), 150); };
        window.addEventListener('resize', state.onResize);
        document.addEventListener('fullscreenchange', state.onResize);

        status.hidden = true;
        controls.hidden = false;
        root.classList.add('is-ready');
        apply();
    } catch (error) {
        if (state.cancelled) return;
        console.error('Guide flip view failed', error);
        status.textContent = `The flip view could not load (${error?.message || error}). Choose Plain PDF, Open in browser, or Download PDF.`;
        status.classList.add('is-error');
    }
}

function unmount(root, state) {
    state.cancelled = true;
    if (state.onResize) {
        window.removeEventListener('resize', state.onResize);
        document.removeEventListener('fullscreenchange', state.onResize);
    }
    state.pdf?.destroy();
    state.task?.destroy();
    try { state.flip?.destroy(); } catch { /* element already gone */ }
    state.urls.forEach((url) => URL.revokeObjectURL(url));
    instances.delete(root);
}

export function mountAll() {
    instances.forEach((state, root) => { if (!root.isConnected) unmount(root, state); });
    document.querySelectorAll('[data-vx-flipbook]').forEach((root) => {
        if (!instances.has(root)) mount(root);
    });
}
