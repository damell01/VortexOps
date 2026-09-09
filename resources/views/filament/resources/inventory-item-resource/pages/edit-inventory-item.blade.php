<x-filament-panels::page>
    <style>
        .vx-inventory-edit{max-width:1100px;margin:0 auto}.vx-inventory-edit-intro{display:none}
        .vx-barcode-card{margin-top:1rem;border:1px solid rgb(226 232 240);border-radius:1rem;background:#fff;overflow:hidden;box-shadow:0 1px 2px rgba(15,23,42,.04)}
        .dark .vx-barcode-card{border-color:rgb(51 65 85);background:rgb(15 23 42)}
        .vx-barcode-head{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid rgb(226 232 240)}
        .dark .vx-barcode-head{border-color:rgb(51 65 85)}
        .vx-barcode-title{font-size:1rem;font-weight:800;color:rgb(15 23 42)}.dark .vx-barcode-title{color:#fff}
        .vx-barcode-copy{margin-top:.25rem;font-size:.8rem;line-height:1.45;color:rgb(71 85 105)}.dark .vx-barcode-copy{color:rgb(203 213 225)}
        .vx-barcode-body{padding:1rem 1.1rem}
        .vx-barcode-add{display:grid;grid-template-columns:150px minmax(0,1fr) auto;gap:.65rem;align-items:end;margin-bottom:1rem}
        .vx-barcode-field label{display:block;margin-bottom:.35rem;font-size:.75rem;font-weight:700;color:rgb(51 65 85)}.dark .vx-barcode-field label{color:rgb(226 232 240)}
        .vx-barcode-field input,.vx-barcode-field select{width:100%;min-height:42px;border:1px solid rgb(203 213 225);border-radius:.65rem;background:#fff;padding:.55rem .7rem;color:rgb(15 23 42)}
        .dark .vx-barcode-field input,.dark .vx-barcode-field select{border-color:rgb(71 85 105);background:rgb(30 41 59);color:#fff}
        .vx-barcode-add-btn{min-height:42px;border-radius:.65rem;background:rgb(124 58 237);padding:.55rem .95rem;font-size:.8rem;font-weight:800;color:#fff}
        .vx-barcode-table{width:100%;border-collapse:separate;border-spacing:0;border:1px solid rgb(226 232 240);border-radius:.75rem;overflow:hidden}
        .dark .vx-barcode-table{border-color:rgb(51 65 85)}
        .vx-barcode-table th{background:rgb(248 250 252);padding:.65rem .75rem;text-align:left;font-size:.7rem;font-weight:800;text-transform:uppercase;letter-spacing:.035em;color:rgb(71 85 105)}
        .dark .vx-barcode-table th{background:rgb(30 41 59);color:rgb(203 213 225)}
        .vx-barcode-table td{padding:.72rem .75rem;border-top:1px solid rgb(226 232 240);font-size:.82rem;color:rgb(15 23 42);vertical-align:middle}
        .dark .vx-barcode-table td{border-color:rgb(51 65 85);color:rgb(241 245 249)}
        .vx-code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-weight:700;word-break:break-all}
        .vx-code-pill{display:inline-flex;border-radius:999px;padding:.18rem .5rem;font-size:.68rem;font-weight:800;background:rgb(237 233 254);color:rgb(109 40 217)}
        .dark .vx-code-pill{background:rgba(124,58,237,.22);color:rgb(216 180 254)}
        .vx-code-primary{background:rgb(220 252 231);color:rgb(21 128 61)}.dark .vx-code-primary{background:rgba(22,163,74,.2);color:rgb(134 239 172)}
        .vx-code-delete{border:1px solid rgb(254 202 202);border-radius:.55rem;padding:.35rem .55rem;font-size:.72rem;font-weight:700;color:rgb(185 28 28)}
        .dark .vx-code-delete{border-color:rgb(127 29 29);color:rgb(252 165 165)}
        .vx-barcode-empty{padding:1rem;border:1px dashed rgb(203 213 225);border-radius:.75rem;text-align:center;font-size:.8rem;color:rgb(71 85 105)}.dark .vx-barcode-empty{border-color:rgb(71 85 105);color:rgb(203 213 225)}
        .vx-barcode-status{margin-top:.65rem;font-size:.78rem;color:rgb(71 85 105)}.dark .vx-barcode-status{color:rgb(203 213 225)}
        .vx-barcode-status.error{color:rgb(185 28 28)}.dark .vx-barcode-status.error{color:rgb(252 165 165)}
        @media(max-width:640px){
            body:has(.vx-inventory-edit) .fi-page-header{display:none!important}
            .vx-inventory-edit{max-width:none;margin:0;padding-bottom:5.5rem}
            .vx-inventory-edit-intro{display:block;margin-bottom:.75rem}.vx-inventory-edit-intro h1{font-size:1.45rem;font-weight:800;line-height:1.2;color:rgb(17 24 39)}.dark .vx-inventory-edit-intro h1{color:#fff}.vx-inventory-edit-intro p{margin-top:.35rem;font-size:.78rem;line-height:1.45;color:rgb(71 85 105)}.dark .vx-inventory-edit-intro p{color:rgb(203 213 225)}
            .vx-inventory-edit .fi-section{border-radius:.85rem!important;margin-bottom:.7rem!important;overflow:hidden}.vx-inventory-edit .fi-section-header{padding:.85rem!important}.vx-inventory-edit .fi-section-content{padding:.85rem!important}
            .vx-inventory-edit .fi-sc-grid,.vx-inventory-edit [style*="grid-template-columns"]{grid-template-columns:minmax(0,1fr)!important}.vx-inventory-edit .fi-fo-field-wrp,.vx-inventory-edit .fi-fo-component-ctn>*{min-width:0!important;grid-column:1/-1!important}
            .vx-inventory-edit input,.vx-inventory-edit select,.vx-inventory-edit textarea,.vx-inventory-edit button[role="combobox"]{font-size:16px!important;min-height:46px!important}.vx-inventory-edit textarea{min-height:110px!important}
            .vx-inventory-edit .fi-fo-file-upload{min-height:120px}.vx-inventory-edit .fi-fo-repeater-item{border-radius:.8rem!important}.vx-inventory-edit .fi-fo-repeater-item-content{padding:.75rem!important}
            .vx-inventory-edit .vx-choice-cards{display:grid!important;grid-template-columns:1fr!important;gap:.5rem!important}.vx-inventory-edit .vx-choice-cards label{min-height:48px!important}
            body:has(.vx-inventory-edit) .fi-form-actions{position:sticky!important;bottom:0!important;z-index:30!important;margin-inline:-1rem!important;padding:.65rem 1rem max(.65rem,env(safe-area-inset-bottom))!important;border-top:1px solid rgb(229 231 235)!important;background:rgba(255,255,255,.96)!important;backdrop-filter:blur(16px)}
            .dark body:has(.vx-inventory-edit) .fi-form-actions{border-color:rgb(55 65 81)!important;background:rgba(17,24,39,.96)!important}body:has(.vx-inventory-edit) .fi-form-actions .fi-btn{min-height:48px!important;flex:1!important}
            .vx-barcode-head{display:block}.vx-barcode-add{grid-template-columns:1fr}.vx-barcode-add-btn{width:100%}.vx-barcode-table{font-size:.76rem}.vx-barcode-table th,.vx-barcode-table td{padding:.6rem .55rem}
        }
    </style>
    <div class="vx-inventory-edit" data-product-id="{{ $this->record->getKey() }}">
        <div class="vx-inventory-edit-intro">
            <h1>Edit Inventory Item</h1>
            <p>Update the product, barcode, pricing, case contents, and reorder settings. Stock quantity changes stay in Move / Correct Stock.</p>
        </div>
        <form wire:submit="save">
            {{ $this->form }}
            <div class="fi-form-actions mt-6 flex flex-wrap gap-3">
                <x-filament::button type="submit" icon="heroicon-o-check">
                    Save changes
                </x-filament::button>
                <x-filament::button
                    tag="a"
                    color="gray"
                    :href="\App\Filament\Resources\InventoryItemResource::getUrl('view', ['record' => $this->record])"
                >
                    Cancel
                </x-filament::button>
            </div>
        </form>

        <section class="vx-barcode-card" data-vx-barcode-manager>
            <div class="vx-barcode-head">
                <div>
                    <div class="vx-barcode-title">Barcodes / UPCs</div>
                    <p class="vx-barcode-copy">Add additional scannable codes that should resolve to this same inventory item. The primary barcode above stays intact; extras are stored as aliases for future scans.</p>
                </div>
            </div>
            <div class="vx-barcode-body">
                <div class="vx-barcode-add">
                    <div class="vx-barcode-field">
                        <label for="vx-barcode-type">Type</label>
                        <select id="vx-barcode-type" data-code-type>
                            <option value="barcode">Barcode</option>
                            <option value="upc">UPC</option>
                        </select>
                    </div>
                    <div class="vx-barcode-field">
                        <label for="vx-barcode-value">Additional barcode / UPC</label>
                        <input id="vx-barcode-value" data-code-value inputmode="numeric" autocomplete="off" placeholder="Scan or enter code">
                    </div>
                    <button type="button" class="vx-barcode-add-btn" data-add-code>+ Add Barcode</button>
                </div>
                <div data-code-list class="vx-barcode-empty">Loading barcodes…</div>
                <div data-code-status class="vx-barcode-status"></div>
            </div>
        </section>
    </div>

    <script>
    (() => {
        const root = document.querySelector('[data-vx-barcode-manager]');
        const page = document.querySelector('.vx-inventory-edit');
        if (!root || !page || root.dataset.ready === '1') return;
        root.dataset.ready = '1';

        const productId = Number(page.dataset.productId);
        const list = root.querySelector('[data-code-list]');
        const status = root.querySelector('[data-code-status]');
        const value = root.querySelector('[data-code-value]');
        const type = root.querySelector('[data-code-type]');
        const add = root.querySelector('[data-add-code]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
        const request = async (url, options = {}) => {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf,...(options.headers || {})},
                ...options,
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = data?.errors ? Object.values(data.errors).flat()[0] : (data?.message || 'Something went wrong.');
                throw new Error(message);
            }
            return data;
        };

        const render = codes => {
            if (!codes?.length) {
                list.className = 'vx-barcode-empty';
                list.innerHTML = 'No barcodes saved yet.';
                return;
            }
            list.className = '';
            list.innerHTML = `<table class="vx-barcode-table"><thead><tr><th>Barcode / UPC</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>${codes.map(code => `
                <tr>
                    <td class="vx-code">${esc(code.value)}</td>
                    <td><span class="vx-code-pill">${esc(String(code.type || '').toUpperCase())}</span></td>
                    <td>${code.primary ? '<span class="vx-code-pill vx-code-primary">Primary</span>' : '<span class="vx-code-pill">Additional</span>'}</td>
                    <td style="text-align:right">${code.id ? `<button type="button" class="vx-code-delete" data-remove-code="${code.id}">Remove</button>` : ''}</td>
                </tr>`).join('')}</tbody></table>`;

            list.querySelectorAll('[data-remove-code]').forEach(button => button.addEventListener('click', async () => {
                if (!confirm('Remove this additional barcode from the item?')) return;
                button.disabled = true;
                status.className = 'vx-barcode-status';
                status.textContent = 'Removing barcode…';
                try {
                    await request(`/inventory-scanner-api/barcodes/${button.dataset.removeCode}`, {method:'DELETE'});
                    status.textContent = 'Barcode removed.';
                    await load();
                } catch (error) {
                    button.disabled = false;
                    status.className = 'vx-barcode-status error';
                    status.textContent = error.message;
                }
            }));
        };

        const load = async () => {
            try {
                const data = await request(`/inventory-scanner-api/items/${productId}/barcodes`, {method:'GET', headers:{}});
                render(data.codes || []);
            } catch (error) {
                list.className = 'vx-barcode-empty';
                list.textContent = 'Unable to load barcodes.';
                status.className = 'vx-barcode-status error';
                status.textContent = error.message;
            }
        };

        add.addEventListener('click', async () => {
            const code = value.value.trim();
            if (!code) {
                status.className = 'vx-barcode-status error';
                status.textContent = 'Enter or scan a barcode first.';
                value.focus();
                return;
            }
            add.disabled = true;
            status.className = 'vx-barcode-status';
            status.textContent = 'Saving barcode…';
            try {
                const data = await request('/inventory-scanner-api/barcodes/attach', {
                    method:'POST',
                    body:JSON.stringify({product_id:productId, barcode:code, type:type.value}),
                });
                value.value = '';
                status.textContent = data.message || 'Barcode added.';
                await load();
                value.focus();
            } catch (error) {
                status.className = 'vx-barcode-status error';
                status.textContent = error.message;
            } finally {
                add.disabled = false;
            }
        });

        value.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                add.click();
            }
        });

        load();
    })();
    </script>
</x-filament-panels::page>
