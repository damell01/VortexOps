<style>
    [data-vx-page="inventory-scanner"] button,
    [data-vx-page="inventory-scanner"] a,
    [data-vx-page="inventory-scanner"] input,
    [data-vx-page="inventory-scanner"] select {
        touch-action: manipulation;
    }

    @media (max-width: 640px) {
        [data-vx-page="inventory-scanner"] {
            padding-bottom: max(6rem, calc(5rem + env(safe-area-inset-bottom)));
        }

        [data-vx-page="inventory-scanner"] button,
        [data-vx-page="inventory-scanner"] a[role="button"] {
            min-height: 48px;
        }

        [data-vx-page="inventory-scanner"] input,
        [data-vx-page="inventory-scanner"] select,
        [data-vx-page="inventory-scanner"] textarea {
            min-height: 48px;
            font-size: 16px !important;
        }

        [data-vx-page="inventory-scanner"] .grid.grid-cols-3 > button {
            min-height: 56px;
            padding-inline: .45rem;
            font-size: .78rem;
        }

        [data-vx-page="inventory-scanner"] .flex.flex-wrap.items-center.gap-2 > button,
        [data-vx-page="inventory-scanner"] .flex.flex-wrap.items-center.gap-2 > a {
            flex: 1 1 46%;
            justify-content: center;
        }

        [data-vx-page="inventory-scanner"] section {
            scroll-margin-top: 5rem;
        }
    }

    .vx-scan-sheet-backdrop {
        position: fixed;
        inset: 0;
        z-index: 9998;
        display: flex;
        align-items: flex-end;
        justify-content: center;
        background: rgba(15, 23, 42, .55);
        padding: 1rem;
        padding-bottom: max(1rem, env(safe-area-inset-bottom));
        backdrop-filter: blur(4px);
    }

    .vx-scan-sheet {
        width: min(100%, 34rem);
        max-height: min(88vh, 760px);
        overflow-y: auto;
        border-radius: 1rem;
        background: white;
        box-shadow: 0 24px 70px rgba(15, 23, 42, .28);
        color: rgb(17 24 39);
    }

    .dark .vx-scan-sheet {
        background: rgb(17 24 39);
        color: white;
        border: 1px solid rgb(55 65 81);
    }

    .vx-scan-sheet-header {
        position: sticky;
        top: 0;
        z-index: 2;
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: .75rem;
        padding: 1rem;
        border-bottom: 1px solid rgb(229 231 235);
        background: inherit;
    }

    .dark .vx-scan-sheet-header { border-color: rgb(55 65 81); }
    .vx-scan-sheet-body { padding: 1rem; }
    .vx-scan-eyebrow { font-size: .7rem; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: rgb(37 99 235); }
    .vx-scan-title { margin-top: .2rem; font-size: 1.15rem; font-weight: 800; line-height: 1.25; }
    .vx-scan-code { margin-top: .45rem; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: .85rem; color: rgb(107 114 128); word-break: break-all; }
    .vx-scan-close { width: 44px; height: 44px; flex: 0 0 44px; border-radius: .75rem; border: 1px solid rgb(209 213 219); font-size: 1.35rem; line-height: 1; }
    .dark .vx-scan-close { border-color: rgb(75 85 99); }

    .vx-scan-actions { display: grid; gap: .65rem; }
    .vx-scan-action {
        min-height: 54px;
        width: 100%;
        border-radius: .8rem;
        padding: .8rem 1rem;
        font-weight: 800;
        text-align: left;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
    }
    .vx-scan-action small { display: block; margin-top: .15rem; font-weight: 500; opacity: .82; }
    .vx-scan-action-primary { background: rgb(37 99 235); color: white; }
    .vx-scan-action-create { background: rgb(5 150 105); color: white; }
    .vx-scan-action-secondary { border: 1px solid rgb(209 213 219); background: rgb(249 250 251); color: rgb(55 65 81); }
    .dark .vx-scan-action-secondary { border-color: rgb(75 85 99); background: rgb(31 41 55); color: rgb(229 231 235); }

    .vx-scan-field { display: block; margin-top: .8rem; }
    .vx-scan-field span { display: block; margin-bottom: .35rem; font-size: .78rem; font-weight: 700; color: rgb(75 85 99); }
    .dark .vx-scan-field span { color: rgb(209 213 219); }
    .vx-scan-field input, .vx-scan-field select {
        width: 100%; min-height: 50px; border-radius: .75rem; border: 1px solid rgb(209 213 219); padding: .65rem .8rem; font-size: 16px;
    }
    .dark .vx-scan-field input, .dark .vx-scan-field select { border-color: rgb(75 85 99); background: rgb(31 41 55); color: white; }

    .vx-scan-results { margin-top: .7rem; display: grid; gap: .5rem; }
    .vx-scan-result {
        width: 100%; min-height: 58px; border-radius: .75rem; border: 1px solid rgb(229 231 235); padding: .7rem .8rem; text-align: left;
        display: flex; justify-content: space-between; gap: .75rem; align-items: center;
    }
    .dark .vx-scan-result { border-color: rgb(55 65 81); }
    .vx-scan-result strong { display: block; font-size: .9rem; }
    .vx-scan-result small { display: block; margin-top: .15rem; color: rgb(107 114 128); }
    .vx-scan-result em { font-style: normal; color: rgb(37 99 235); font-weight: 800; font-size: .8rem; }

    .vx-scan-status { margin-top: .75rem; border-radius: .7rem; padding: .7rem .8rem; font-size: .82rem; }
    .vx-scan-status-error { background: rgb(254 242 242); color: rgb(185 28 28); }
    .vx-scan-status-success { background: rgb(236 253 245); color: rgb(4 120 87); }

    .vx-scan-toast {
        position: fixed; z-index: 10000; left: 50%; bottom: max(1rem, env(safe-area-inset-bottom)); transform: translateX(-50%);
        width: min(calc(100% - 2rem), 30rem); border-radius: .8rem; padding: .85rem 1rem; background: rgb(5 150 105); color: white; font-weight: 800;
        box-shadow: 0 16px 40px rgba(15,23,42,.22); text-align: center;
    }
</style>

<script>
(() => {
    const API = '/inventory-scanner-api';
    let lastUnknown = null;
    let sheet = null;
    let observerTimer = null;

    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[ch]));

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf(),
                ...(options.headers || {}),
            },
            ...options,
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            const message = data?.errors ? Object.values(data.errors).flat()[0] : (data?.message || 'Something went wrong.');
            throw new Error(message);
        }
        return data;
    };

    const toast = message => {
        document.querySelector('.vx-scan-toast')?.remove();
        const el = document.createElement('div');
        el.className = 'vx-scan-toast';
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(() => el.remove(), 2600);
    };

    const closeSheet = () => {
        sheet?.remove();
        sheet = null;
    };

    const rescan = async barcode => {
        closeSheet();
        lastUnknown = null;
        const root = document.querySelector('[data-vx-page="inventory-scanner"]')?.closest('[wire\\:id]');
        const id = root?.getAttribute('wire:id');
        try {
            if (id && window.Livewire?.find) {
                const component = window.Livewire.find(id);
                await component.set('scanInput', barcode);
                await component.call('submitScan');
            }
        } catch (_) {}
    };

    const baseSheet = (barcode, body) => {
        closeSheet();
        sheet = document.createElement('div');
        sheet.className = 'vx-scan-sheet-backdrop';
        sheet.innerHTML = `
            <div class="vx-scan-sheet" role="dialog" aria-modal="true" aria-label="Unknown barcode">
                <div class="vx-scan-sheet-header">
                    <div>
                        <div class="vx-scan-eyebrow">Unknown barcode</div>
                        <div class="vx-scan-title">What should this barcode belong to?</div>
                        <div class="vx-scan-code">${escapeHtml(barcode)}</div>
                    </div>
                    <button class="vx-scan-close" type="button" aria-label="Close">×</button>
                </div>
                <div class="vx-scan-sheet-body">${body}</div>
            </div>`;
        document.body.appendChild(sheet);
        sheet.querySelector('.vx-scan-close')?.addEventListener('click', closeSheet);
        sheet.addEventListener('click', e => { if (e.target === sheet) closeSheet(); });
        return sheet;
    };

    const showChooser = barcode => {
        const el = baseSheet(barcode, `
            <div class="vx-scan-actions">
                <button type="button" class="vx-scan-action vx-scan-action-primary" data-action="existing">
                    <span>Add to Existing Item<small>Save this as another UPC/barcode for an item already in inventory.</small></span><span>→</span>
                </button>
                <button type="button" class="vx-scan-action vx-scan-action-create" data-action="create">
                    <span>Create New Inventory Item<small>Create the item now and save this barcode as its first code.</small></span><span>＋</span>
                </button>
                <button type="button" class="vx-scan-action vx-scan-action-secondary" data-action="cancel">
                    <span>Cancel / Scan Again<small>Close this and return to the scanner.</small></span><span>×</span>
                </button>
            </div>`);
        el.querySelector('[data-action="existing"]').addEventListener('click', () => showExisting(barcode));
        el.querySelector('[data-action="create"]').addEventListener('click', () => showCreate(barcode));
        el.querySelector('[data-action="cancel"]').addEventListener('click', closeSheet);
    };

    const showExisting = barcode => {
        const el = baseSheet(barcode, `
            <button type="button" class="vx-scan-action vx-scan-action-secondary" data-back><span>← Back</span></button>
            <label class="vx-scan-field"><span>Search inventory</span><input data-search type="search" autocomplete="off" placeholder="Item name, SKU or existing UPC"></label>
            <div class="vx-scan-results" data-results></div>
            <div data-status></div>`);
        el.querySelector('[data-back]').addEventListener('click', () => showChooser(barcode));
        const input = el.querySelector('[data-search]');
        const results = el.querySelector('[data-results]');
        const status = el.querySelector('[data-status]');
        let timer;
        input.focus();
        input.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const q = input.value.trim();
                results.innerHTML = '';
                status.innerHTML = '';
                if (q.length < 2) return;
                try {
                    const data = await request(`${API}/items?q=${encodeURIComponent(q)}`, { method: 'GET', headers: {} });
                    if (!data.items?.length) {
                        status.innerHTML = '<div class="vx-scan-status">No matching inventory items.</div>';
                        return;
                    }
                    results.innerHTML = data.items.map(item => `
                        <button type="button" class="vx-scan-result" data-id="${item.id}">
                            <span><strong>${escapeHtml(item.name)}</strong><small>SKU ${escapeHtml(item.sku || '—')} ${item.barcode ? ' · ' + escapeHtml(item.barcode) : ''}</small></span>
                            <em>Add UPC</em>
                        </button>`).join('');
                    results.querySelectorAll('[data-id]').forEach(button => button.addEventListener('click', async () => {
                        button.disabled = true;
                        status.innerHTML = '<div class="vx-scan-status">Saving barcode…</div>';
                        try {
                            const data = await request(`${API}/barcodes/attach`, { method: 'POST', body: JSON.stringify({ product_id: Number(button.dataset.id), barcode, type: 'barcode' }) });
                            toast(data.message || 'Barcode added.');
                            await rescan(barcode);
                        } catch (error) {
                            button.disabled = false;
                            status.innerHTML = `<div class="vx-scan-status vx-scan-status-error">${escapeHtml(error.message)}</div>`;
                        }
                    }));
                } catch (error) {
                    status.innerHTML = `<div class="vx-scan-status vx-scan-status-error">${escapeHtml(error.message)}</div>`;
                }
            }, 250);
        });
    };

    const showCreate = barcode => {
        const el = baseSheet(barcode, `
            <button type="button" class="vx-scan-action vx-scan-action-secondary" data-back><span>← Back</span></button>
            <label class="vx-scan-field"><span>Item name</span><input data-name type="text" autocomplete="off" placeholder="What is this item?"></label>
            <label class="vx-scan-field"><span>Code type</span><select data-type><option value="barcode">Barcode</option><option value="upc">UPC</option></select></label>
            <button type="button" class="vx-scan-action vx-scan-action-create" data-create style="margin-top:.85rem"><span>Create Item & Save Barcode</span><span>＋</span></button>
            <div data-status></div>`);
        el.querySelector('[data-back]').addEventListener('click', () => showChooser(barcode));
        const name = el.querySelector('[data-name]');
        const type = el.querySelector('[data-type]');
        const create = el.querySelector('[data-create]');
        const status = el.querySelector('[data-status]');
        name.focus();
        create.addEventListener('click', async () => {
            if (!name.value.trim()) {
                status.innerHTML = '<div class="vx-scan-status vx-scan-status-error">Enter an item name first.</div>';
                return;
            }
            create.disabled = true;
            status.innerHTML = '<div class="vx-scan-status">Creating item…</div>';
            try {
                const data = await request(`${API}/items/create`, { method: 'POST', body: JSON.stringify({ name: name.value.trim(), barcode, type: type.value }) });
                toast(data.message || 'Item created.');
                await rescan(barcode);
            } catch (error) {
                create.disabled = false;
                status.innerHTML = `<div class="vx-scan-status vx-scan-status-error">${escapeHtml(error.message)}</div>`;
            }
        });
    };

    const detectUnknown = () => {
        if (!document.querySelector('[data-vx-page="inventory-scanner"]')) return;
        const pageText = document.querySelector('[data-vx-page="inventory-scanner"]')?.innerText || '';
        const match = pageText.match(/No inventory item found for ["“]([^"”]+)["”]/i);
        if (!match) return;
        const barcode = match[1].trim();
        if (!barcode || barcode === lastUnknown || sheet) return;
        lastUnknown = barcode;
        showChooser(barcode);
    };

    const enhanceInventoryHeader = () => {
        const match = window.location.pathname.match(/\/inventory-items\/(\d+)(?:\/|$)/);
        if (!match || document.querySelector('[data-vx-barcode-manager]')) return;
        const actions = document.querySelector('.fi-page-header-actions');
        if (!actions) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.vxBarcodeManager = '1';
        button.className = 'fi-btn inline-flex items-center justify-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white';
        button.textContent = 'Barcodes / UPCs';
        button.addEventListener('click', () => showBarcodeManager(Number(match[1])));
        actions.prepend(button);
    };

    const showBarcodeManager = async itemId => {
        const el = baseSheet('Inventory item', '<div data-manager><div class="vx-scan-status">Loading barcodes…</div></div>');
        const manager = el.querySelector('[data-manager]');
        try {
            const data = await request(`${API}/items/${itemId}/barcodes`, { method: 'GET', headers: {} });
            const codes = data.codes || [];
            manager.innerHTML = `
                <div class="vx-scan-title">${escapeHtml(data.item.name)}</div>
                <div class="vx-scan-code">SKU ${escapeHtml(data.item.sku || '—')}</div>
                <div class="vx-scan-results" data-codes>
                    ${codes.length ? codes.map(code => `<div class="vx-scan-result"><span><strong>${escapeHtml(code.value)}</strong><small>${escapeHtml(code.type.toUpperCase())}${code.primary ? ' · primary' : ''}</small></span>${code.id ? `<button type="button" data-remove="${code.id}" style="color:rgb(185 28 28);font-weight:800;min-height:44px">Remove</button>` : ''}</div>`).join('') : '<div class="vx-scan-status">No barcodes saved yet.</div>'}
                </div>
                <label class="vx-scan-field"><span>Add another barcode / UPC</span><input data-new-code type="text" inputmode="numeric" autocomplete="off" placeholder="Scan or type code"></label>
                <label class="vx-scan-field"><span>Code type</span><select data-new-type><option value="barcode">Barcode</option><option value="upc">UPC</option></select></label>
                <button type="button" class="vx-scan-action vx-scan-action-primary" data-add-code style="margin-top:.8rem"><span>Add Code</span><span>＋</span></button>
                <div data-status></div>`;
            manager.querySelectorAll('[data-remove]').forEach(btn => btn.addEventListener('click', async () => {
                if (!confirm('Remove this additional barcode?')) return;
                try {
                    await request(`${API}/barcodes/${btn.dataset.remove}`, { method: 'DELETE', body: '{}' });
                    toast('Barcode removed.');
                    showBarcodeManager(itemId);
                } catch (error) { alert(error.message); }
            }));
            manager.querySelector('[data-add-code]').addEventListener('click', async () => {
                const code = manager.querySelector('[data-new-code]').value.trim();
                const type = manager.querySelector('[data-new-type]').value;
                const status = manager.querySelector('[data-status]');
                if (!code) return;
                try {
                    const response = await request(`${API}/barcodes/attach`, { method: 'POST', body: JSON.stringify({ product_id: itemId, barcode: code, type }) });
                    toast(response.message || 'Barcode added.');
                    showBarcodeManager(itemId);
                } catch (error) {
                    status.innerHTML = `<div class="vx-scan-status vx-scan-status-error">${escapeHtml(error.message)}</div>`;
                }
            });
        } catch (error) {
            manager.innerHTML = `<div class="vx-scan-status vx-scan-status-error">${escapeHtml(error.message)}</div>`;
        }
    };

    const runEnhancements = () => {
        clearTimeout(observerTimer);
        observerTimer = setTimeout(() => {
            detectUnknown();
            enhanceInventoryHeader();
        }, 120);
    };

    const start = () => {
        runEnhancements();
        const observer = new MutationObserver(runEnhancements);
        observer.observe(document.body, { childList: true, subtree: true, characterData: true });
        document.addEventListener('livewire:navigated', () => { lastUnknown = null; closeSheet(); runEnhancements(); });
    };

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
})();
</script>
