// The little "?" help buttons (App\Support\HelpTopics): one centered modal,
// filled from the topics the page carries in #vx-help-topics.
import '../css/help.css';

let topics = null;
const load = () => {
    if (topics) return topics;
    try { topics = JSON.parse(document.getElementById('vx-help-topics')?.textContent || '{}'); } catch { topics = {}; }
    return topics;
};

function open(topic, opener) {
    const help = load()[topic];
    if (!help) return;
    document.querySelector('.vx-help-dialog')?.remove();
    const dialog = document.createElement('dialog');
    dialog.className = 'vx-help-dialog';
    dialog.setAttribute('data-vx-dialog', '');
    dialog.setAttribute('aria-labelledby', 'vx-help-title');
    const list = (items, tag) => items?.length ? `<${tag}>${items.map((i) => `<li>${esc(i)}</li>`).join('')}</${tag}>` : '';
    dialog.innerHTML = `
        <div class="vx-help-head"><span class="vx-help-badge" aria-hidden="true">?</span><h2 id="vx-help-title">${esc(help.title)}</h2>
            <button type="button" class="vx-help-x" data-close aria-label="Close">×</button></div>
        <p class="vx-help-intro">${esc(help.intro)}</p>
        ${help.steps?.length ? `<h3>How it works</h3>${list(help.steps, 'ol')}` : ''}
        ${help.tips?.length ? `<h3>Good to know</h3>${list(help.tips, 'ul')}` : ''}
        <div class="vx-help-foot"><button type="button" data-close class="vx-help-ok">Got it</button></div>`;
    document.body.appendChild(dialog);
    let closing = false;
    const close = () => { if (closing) return; closing = true; dialog.classList.add('is-closing'); setTimeout(() => { dialog.close(); dialog.remove(); opener?.focus?.(); }, 140); };
    dialog.addEventListener('click', (e) => { if (e.target.closest('[data-close]') || e.target === dialog) close(); });
    dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(); });
    dialog.addEventListener('keydown', (e) => { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); close(); } });
    dialog.showModal();
    dialog.querySelector('.vx-help-ok').focus();
}

function esc(s) { const d = document.createElement('div'); d.textContent = String(s ?? ''); return d.innerHTML; }

document.addEventListener('click', (e) => {
    const btn = e.target.closest?.('[data-vx-help]');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    open(btn.dataset.vxHelp, btn);
}, true);
document.addEventListener('livewire:navigated', () => { topics = null; });
