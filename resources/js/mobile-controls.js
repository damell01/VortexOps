import '../css/mobile-controls.css';

// Enhance native controls without replacing their Livewire/form bindings.
const phone = window.matchMedia('(max-width: 767px)');
const controls = new Map();
const collections = new Map();
let dismiss = null;
function labelFor(select) {
    if (select.getAttribute('aria-label')) return select.getAttribute('aria-label');
    const label = select.labels?.[0];
    if (label) {
        const copy = label.cloneNode(true);
        copy.querySelectorAll('select,button,input').forEach(el => el.remove());
        if (copy.textContent.trim()) return copy.textContent.trim();
    }
    return 'Choose an option';
}
function openPicker(select, trigger) {
    dismiss?.();
    const overlay = document.createElement('div');
    overlay.className = 'vx-picker-overlay';
    const panel = document.createElement('section');
    panel.className = 'vx-mobile-picker-sheet';
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-modal', 'true');
    panel.setAttribute('aria-label', labelFor(select));
    const header = document.createElement('header');
    const title = document.createElement('h2');
    title.textContent = labelFor(select);
    const done = document.createElement('button');
    done.type = 'button'; done.textContent = select.multiple ? 'Done' : 'Close';
    header.append(title, done);
    const search = document.createElement('input');
    search.type = 'search'; search.placeholder = 'Search options…';
    search.setAttribute('aria-label', 'Search options'); search.autocomplete = 'off';
    const list = document.createElement('div'); list.className = 'vx-picker-options';
    const chosen = new Set([...select.selectedOptions].map(o => o.value));
    const previousOverflow = document.body.style.overflow;
    const close = () => {
        overlay.remove(); document.body.style.overflow = previousOverflow;
        trigger.setAttribute('aria-expanded', 'false');
        if (trigger.isConnected) trigger.focus();
        if (dismiss === close) dismiss = null;
        document.removeEventListener('keydown', keyboard, true);
    };
    const commit = () => {
        [...select.options].forEach(o => { o.selected = chosen.has(o.value); });
        select.dispatchEvent(new Event('input', { bubbles: true }));
        select.dispatchEvent(new Event('change', { bubbles: true }));
        syncControl(select, trigger);
        close();
    };
    const render = () => {
        list.replaceChildren();
        const options = [...select.options].filter(o => !o.hidden && o.text.toLowerCase().includes(search.value.toLowerCase()));
        for (const option of options) {
            const button = document.createElement('button');
            button.type = 'button'; button.disabled = option.disabled || option.parentElement?.disabled;
            button.setAttribute('aria-pressed', String(chosen.has(option.value)));
            const text = document.createElement('span'); text.textContent = option.text;
            const check = document.createElement('span'); check.textContent = chosen.has(option.value) ? '✓' : '';
            check.setAttribute('aria-hidden', 'true'); button.append(text, check);
            button.onclick = () => {
                if (select.multiple) {
                    chosen.has(option.value) ? chosen.delete(option.value) : chosen.add(option.value);
                    render();
                } else { chosen.clear(); chosen.add(option.value); commit(); }
            };
            list.append(button);
        }
        if (!options.length) { const empty = document.createElement('p'); empty.textContent = 'No matching options.'; list.append(empty); }
    };
    const keyboard = event => {
        if (event.key === 'Escape') { event.stopImmediatePropagation(); event.preventDefault(); close(); }
        if (event.key === 'Tab') {
            const items = [...panel.querySelectorAll('button,input')].filter(el => !el.disabled);
            const first = items[0], last = items.at(-1);
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    };
    done.onclick = () => select.multiple ? commit() : close();
    search.oninput = render;
    overlay.onclick = event => { if (event.target === overlay) close(); };
    panel.append(header, search, list); overlay.append(panel);
    (select.closest('.fi-modal-window, .vx-mobile-menu-sheet') || document.body).append(overlay);
    document.body.style.overflow = 'hidden';
    trigger.setAttribute('aria-expanded', 'true');
    document.addEventListener('keydown', keyboard, true);
    dismiss = close; render(); search.focus();
}
function syncControl(select, trigger) {
    const text = [...select.selectedOptions].map(o => o.text).join(', ') || 'Choose…';
    if (trigger.textContent !== text) trigger.textContent = text;
    trigger.disabled = select.disabled;
    trigger.setAttribute('aria-label', labelFor(select) + ': ' + text);
}
function decorateControls() {
    for (const [select, trigger] of controls) {
        if (!select.isConnected || !trigger.isConnected) { trigger.remove(); select.classList.remove('vx-native-picker'); controls.delete(select); }
    }
    document.querySelectorAll('.fi-main select, .vx-mobile-menu-sheet select, .fi-modal-window select').forEach(select => {
        // Filament's searchable choices already supply their own picker.
        if (select.closest('[wire\\:ignore], .ts-wrapper, .choices') || select.tomselect || select.hidden || select.style.display === 'none') return;
        let trigger = controls.get(select);
        if (!trigger) {
            trigger = document.createElement('button'); trigger.type = 'button';
            trigger.className = 'vx-picker-trigger'; trigger.setAttribute('aria-haspopup', 'dialog');
            trigger.setAttribute('aria-expanded', 'false');
            trigger.onclick = () => openPicker(select, trigger);
            select.addEventListener('change', () => syncControl(select, trigger));
            select.addEventListener('invalid', event => {
                if (phone.matches) { event.preventDefault(); openPicker(select, trigger); }
            });
            select.after(trigger); select.classList.add('vx-native-picker');
            controls.set(select, trigger);
        }
        syncControl(select, trigger);
    });
}
function paginate() {
    document.querySelectorAll('[data-vx-mobile-page], .fi-main table:not(.fi-ta-table) > tbody').forEach(container => {
        const rows = [...container.children].filter(el => !el.classList.contains('vx-mobile-pages'));
        // Leave empty-state/total rows and Filament's server pagination alone.
        if (container.tagName === 'TBODY' && rows.some(r => r.querySelector('[colspan],[rowspan],input,select,textarea,button,[contenteditable]'))) return;
        if (rows.length <= 5) return;
        let state = collections.get(container);
        if (!state || state.rows.length !== rows.length || state.rows.some((r,i) => r !== rows[i]) || !state.nav.isConnected) {
            state?.nav.remove();
            const nav = document.createElement('nav'); nav.className = 'vx-mobile-pages';
            nav.setAttribute('aria-label', 'List pages');
            const prev = document.createElement('button'), next = document.createElement('button'), status = document.createElement('span');
            prev.type = next.type = 'button'; prev.textContent = 'Previous'; next.textContent = 'Next';
            status.setAttribute('aria-live', 'polite'); nav.append(prev,status,next);
            const anchor = container.tagName === 'TBODY' ? container.closest('table').parentElement : container;
            anchor.after(nav);
            state = {rows,nav,prev,next,status,page:0};
            prev.onclick = () => { state.page--; draw(state); };
            next.onclick = () => { state.page++; draw(state); };
            collections.set(container,state);
        }
        draw(state);
    });
    for (const [container,state] of collections) if (!container.isConnected) { state.nav.remove(); collections.delete(container); }
}
function draw(state) {
    const pages = Math.ceil(state.rows.length / 5);
    state.page = Math.max(0, Math.min(state.page,pages-1));
    state.rows.forEach((row,i) => row.classList.toggle('vx-mobile-page-hidden', phone.matches && Math.floor(i/5) !== state.page));
    state.prev.disabled = state.page === 0; state.next.disabled = state.page === pages-1;
    const text = 'Page ' + (state.page+1) + ' of ' + pages + ' · ' + state.rows.length + ' items';
    if (state.status.textContent !== text) state.status.textContent = text;
}
let scheduled = false;
function schedule() {
    if (scheduled) return;
    scheduled = true;
    requestAnimationFrame(() => { scheduled = false; decorateControls(); paginate(); });
}
function boot() {
    decorateControls(); paginate();
    new MutationObserver(schedule).observe(document.querySelector('.fi-body') || document.body, {childList:true,subtree:true});
}
phone.addEventListener('change', () => { dismiss?.(); for(const state of collections.values()) draw(state); });
document.addEventListener('livewire:navigating', () => dismiss?.());
document.addEventListener('livewire:navigated', schedule);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded',boot,{once:true}); else boot();

