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
import workerUrl from 'pdfjs-dist/build/pdf.worker.min.mjs?url';

pdfjs.GlobalWorkerOptions.workerSrc = workerUrl;

const instances = new Map();

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
    const state = { flip: null, urls: [], cancelled: false, task: null };
    instances.set(root, state);

    try {
        state.task = pdfjs.getDocument({ url: root.dataset.src, withCredentials: true });
        const pdf = await state.task.promise;
        // Two-page spreads on wide screens, one page on phones; render sharp
        // enough for the device without building huge images.
        const stage = root.clientWidth || 800;
        const pageCss = stage >= 520 ? stage / 2 : stage;
        const target = Math.min(1800, Math.max(900, Math.round(pageCss * (window.devicePixelRatio || 1) * 1.25)));

        const pages = [];
        for (let n = 1; n <= pdf.numPages; n++) {
            if (state.cancelled) return;
            status.textContent = `Preparing page ${n} of ${pdf.numPages}…`;
            pages.push(await renderPage(pdf, n, target));
        }
        pdf.destroy();
        if (state.cancelled) return;
        state.urls = pages.map((p) => p.url);

        const elements = pages.map((p, i) => {
            const el = document.createElement('div');
            el.className = 'vx-flip-page';
            el.dataset.density = i === 0 || i === pages.length - 1 ? 'hard' : 'soft';
            const img = document.createElement('img');
            img.src = p.url;
            img.alt = `${root.dataset.title || 'Guide'} – page ${i + 1}`;
            img.draggable = false;
            el.appendChild(img);
            return el;
        });

        const { width, height } = pages[0];
        state.flip = new PageFlip(book, {
            width, height, size: 'stretch',
            minWidth: 260, maxWidth: 1100,
            minHeight: Math.round(260 * height / width), maxHeight: Math.round(1100 * height / width),
            showCover: true, usePortrait: true, autoSize: true,
            maxShadowOpacity: 0.35, flippingTime: 650, mobileScrollSupport: true,
        });
        state.flip.loadFromHTML(elements);

        const total = pages.length;
        const update = () => {
            const i = state.flip.getCurrentPageIndex();
            const spread = state.flip.getOrientation() === 'landscape' && i > 0 && i + 1 < total;
            indicator.textContent = spread ? `Pages ${i + 1}–${i + 2} of ${total}` : `Page ${i + 1} of ${total}`;
            root.querySelector('[data-prev]').disabled = i === 0;
            root.querySelector('[data-next]').disabled = i + (spread ? 2 : 1) >= total;
        };
        state.flip.on('flip', update);
        state.flip.on('changeOrientation', update);

        root.querySelector('[data-prev]').addEventListener('click', () => state.flip.flipPrev());
        root.querySelector('[data-next]').addEventListener('click', () => state.flip.flipNext());
        root.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') { e.preventDefault(); state.flip.flipPrev(); }
            if (e.key === 'ArrowRight') { e.preventDefault(); state.flip.flipNext(); }
        });
        const fullscreen = root.querySelector('[data-fullscreen]');
        if (root.requestFullscreen) {
            fullscreen.addEventListener('click', () => document.fullscreenElement ? document.exitFullscreen() : root.requestFullscreen());
        } else {
            fullscreen.hidden = true;
        }

        status.hidden = true;
        controls.hidden = false;
        root.classList.add('is-ready');
        update();
    } catch (error) {
        if (state.cancelled) return;
        console.error('Guide flip view failed', error);
        status.textContent = 'The flip view could not load on this device. Choose Plain PDF, Open in browser, or Download PDF.';
        status.classList.add('is-error');
    }
}

function unmount(root, state) {
    state.cancelled = true;
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
