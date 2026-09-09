<style>
.vx-received-filter{margin-top:.55rem;display:flex;align-items:center;gap:.5rem;font-size:.78rem;color:rgb(71 85 105)}
.dark .vx-received-filter{color:rgb(203 213 225)}
.vx-received-filter button{min-height:36px;border:1px solid rgb(203 213 225);border-radius:.65rem;padding:.4rem .7rem;font-weight:700;background:#fff;color:rgb(51 65 85)}
.dark .vx-received-filter button{border-color:rgb(71 85 105);background:rgb(30 41 59);color:rgb(226 232 240)}
</style>
<script>
(() => {
    if (window.__vxScannerReliabilityLoaded) return;
    window.__vxScannerReliabilityLoaded = true;

    const API = '/inventory-scanner-api';
    let candidateTimer = null;
    let lastCandidate = null;
    let showReceived = false;

    const scannerRoot = () => document.querySelector('[data-vx-page="inventory-scanner"]');
    const scanSheetOpen = () => !!document.querySelector('.vx-scan-sheet-backdrop');
    const lookupIsActive = () => {
        const root = scannerRoot();
        if (!root) return false;
        const button = [...root.querySelectorAll('button')].find(el => el.textContent?.trim().includes('Look Up'));
        return !!button && (button.className.includes('text-primary-600') || button.className.includes('shadow-[inset_0_-2px_0_currentColor]'));
    };

    const openUnknownChooser = barcode => {
        if (!barcode || scanSheetOpen()) return;
        const text = scannerRoot()?.innerText || '';
        if (text.includes(`No inventory item found for "${barcode}"`) || text.includes(`No inventory item found for “${barcode}”`)) {
            // Existing scanner helper watches this exact text. Touching the DOM
            // here makes sure its MutationObserver gets another chance after
            // fast Livewire rerenders on mobile Safari.
            scannerRoot()?.setAttribute('data-vx-unknown-refresh', String(Date.now()));
            return;
        }

        // If Livewire already cleared/re-rendered before the old observer saw
        // the error, place a short-lived copy of the same message in the page.
        // The existing chooser detects it and removes nothing from inventory.
        const marker = document.createElement('span');
        marker.hidden = true;
        marker.dataset.vxUnknownFallback = '1';
        marker.textContent = `No inventory item found for "${barcode}".`;
        scannerRoot()?.appendChild(marker);
        setTimeout(() => marker.remove(), 1500);
    };

    const verifyCandidate = barcode => {
        barcode = String(barcode || '').trim();
        if (barcode.length < 3 || !scannerRoot() || !lookupIsActive()) return;
        lastCandidate = barcode;
        clearTimeout(candidateTimer);
        candidateTimer = setTimeout(async () => {
            if (lastCandidate !== barcode || !lookupIsActive() || scanSheetOpen()) return;
            try {
                const response = await fetch(`${API}/items?q=${encodeURIComponent(barcode)}`, {
                    credentials: 'same-origin',
                    headers: {'Accept':'application/json'},
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) return;

                // The server search checks name/SKU/primary barcode/UPC and
                // product identities. Zero results means this really is an
                // unknown code, so force the chooser even if Livewire's error
                // text flashed too quickly for the original observer to see it.
                if (!Array.isArray(data.items) || data.items.length === 0) {
                    openUnknownChooser(barcode);
                }
            } catch (_) {
                // Keep the original Livewire lookup/error path as fallback.
            }
        }, 450);
    };

    const enhanceReceivedPalletFilter = () => {
        const root = scannerRoot();
        if (!root) return;
        const select = [...root.querySelectorAll('select')].find(el => el.getAttribute('wire:model.live') === 'rcvPalletId');
        if (!select) return;

        const received = [...select.options].filter(option => /\s—\sreceived\s*$/i.test(option.textContent || ''));
        received.forEach(option => {
            option.hidden = !showReceived && option.value !== select.value;
            option.disabled = !showReceived && option.value !== select.value;
        });

        let controls = select.parentElement?.querySelector('.vx-received-filter');
        if (!controls) {
            controls = document.createElement('div');
            controls.className = 'vx-received-filter';
            select.insertAdjacentElement('afterend', controls);
        }
        controls.innerHTML = `<button type="button">${showReceived ? 'Hide received pallets' : `Show received pallets${received.length ? ` (${received.length})` : ''}`}</button><span>${showReceived ? 'Showing completed and open deliveries.' : 'Completed pallets are hidden by default.'}</span>`;
        controls.querySelector('button')?.addEventListener('click', () => {
            showReceived = !showReceived;
            enhanceReceivedPalletFilter();
        });
    };

    const inspect = () => {
        enhanceReceivedPalletFilter();
    };

    // Camera scans dispatch this event from the scanner page.
    window.addEventListener('barcode-scanned', event => verifyCandidate(event?.detail?.value));

    // Hardware/Bluetooth scanners type into the Livewire scanInput field. Save
    // the code before Livewire clears it during submit so the unknown prompt
    // cannot lose the value on a fast rerender.
    document.addEventListener('input', event => {
        const target = event.target;
        if (!(target instanceof HTMLInputElement)) return;
        if (target.getAttribute('wire:model.live.debounce.300ms') !== 'scanInput') return;
        verifyCandidate(target.value);
    }, true);

    const observer = new MutationObserver(() => {
        clearTimeout(window.__vxScannerReliabilityTimer);
        window.__vxScannerReliabilityTimer = setTimeout(inspect, 80);
    });
    observer.observe(document.body, {childList:true, subtree:true});

    document.addEventListener('livewire:navigated', () => {
        lastCandidate = null;
        showReceived = false;
        setTimeout(inspect, 80);
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', inspect, {once:true});
    else inspect();
})();
</script>
