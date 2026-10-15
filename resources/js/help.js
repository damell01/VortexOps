// The "?" help (App\Support\HelpTopics): one centered modal for a page's
// "How this page works" and for every field's "?". The words load once, on
// the first click. Related topics open in the same modal, with Back; a page
// that has a walkthrough (guided-help.js) offers "Show me around".
import '../css/help.css';
import { startTour } from './guided-help.js';

const TOPICS_URL = '/admin/help-topics.json';
let topics = null;
let loading = null;

function load() {
    if (topics) return Promise.resolve(topics);
    loading ??= fetch(TOPICS_URL, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : {}))
        .catch(() => ({}))
        .then((t) => { topics = t; loading = null; return t; });
    return loading;
}

function esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }
const list = (items, tag) => (items?.length ? `<${tag}>${items.map((i) => `<li>${esc(i)}</li>`).join('')}</${tag}>` : '');

/** The walkthrough for this page, if it has one. */
function pageTour() {
    try {
        const tours = JSON.parse(document.getElementById('vx-guide-data')?.textContent || '{}');
        const here = location.pathname.replace(/\/$/, '');
        return Object.values(tours).find((t) => (t.path || '').replace(/\/$/, '') === here) || null;
    } catch { return null; }
}

function body(help, canGoBack) {
    const sections = (help.sections || []).map((s) => `<h3>${esc(s.heading)}</h3>${list(s.items, 'ul class="vx-help-plain"')}`).join('');
    const related = (help.related || []).filter((k) => topics?.[k]);
    const tour = pageTour();
    return `
        <div class="vx-help-head">
            ${canGoBack ? '<button type="button" class="vx-help-back" data-back aria-label="Back">‹</button>' : ''}
            <span class="vx-help-badge" aria-hidden="true">?</span><h2 id="vx-help-title">${esc(help.title)}</h2>
            <button type="button" class="vx-help-x" data-close aria-label="Close">×</button>
        </div>
        <p class="vx-help-intro">${esc(help.intro)}</p>
        ${help.steps?.length ? `<h3>How it works</h3>${list(help.steps, 'ol')}` : ''}
        ${sections}
        ${help.tips?.length ? `<h3>Good to know</h3>${list(help.tips, 'ul class="vx-help-tips"')}` : ''}
        ${related.length ? `<h3>Related guides</h3><div class="vx-help-related">${related.map((k) => `<button type="button" data-topic="${esc(k)}">${esc(topics[k].title)} <span aria-hidden="true">→</span></button>`).join('')}</div>` : ''}
        <div class="vx-help-foot">
            ${tour ? '<button type="button" data-tour class="vx-help-tour">Show me around</button>' : ''}
            <button type="button" data-close class="vx-help-ok">Got it</button>
        </div>`;
}

async function open(topic, opener) {
    await load();
    if (!topics?.[topic]) return;
    document.querySelector('.vx-help-dialog')?.remove();
    const dialog = document.createElement('dialog');
    dialog.className = 'vx-help-dialog';
    dialog.setAttribute('data-vx-dialog', '');
    dialog.setAttribute('aria-labelledby', 'vx-help-title');
    const trail = [topic];
    const show = () => {
        dialog.innerHTML = body(topics[trail.at(-1)], trail.length > 1);
        dialog.scrollTop = 0;
        dialog.querySelector('.vx-help-ok')?.focus();
    };
    document.body.appendChild(dialog);
    let closing = false;
    const close = (after) => {
        if (closing) return;
        closing = true;
        dialog.classList.add('is-closing');
        setTimeout(() => { dialog.close(); dialog.remove(); after ? after() : opener?.focus?.(); }, 140);
    };
    dialog.addEventListener('click', (e) => {
        if (e.target === dialog || e.target.closest('[data-close]')) return close();
        if (e.target.closest('[data-back]')) { trail.pop(); return show(); }
        const next = e.target.closest('[data-topic]')?.dataset.topic;
        if (next) { trail.push(next); return show(); }
        if (e.target.closest('[data-tour]')) { const tour = pageTour(); close(() => tour && startTour(tour)); }
    });
    dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(); });
    dialog.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } });
    show();
    dialog.showModal();
    dialog.querySelector('.vx-help-ok')?.focus();
}

document.addEventListener('click', (e) => {
    const btn = e.target.closest?.('[data-vx-help]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    open(btn.dataset.vxHelp, btn);
}, true);
