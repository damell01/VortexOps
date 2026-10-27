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

    const pendingScans = [];
    let generation = 0;
    let showReceived = false;
    let scanInFlight = false;

    const scannerRoot = () => document.querySelector('[data-vx-page="inventory-scanner"]');
    const scanSheetOpen = () => !!document.querySelector('.vx-scan-sheet-backdrop');
    const scanInput = () => scannerRoot()?.querySelector('input[wire\\:model="scanInput"]');
    const livewireComponent = () => {
        const root = scannerRoot()?.closest('[wire\\:id]');
        const id = root?.getAttribute('wire:id');
        return id && window.Livewire?.find ? window.Livewire.find(id) : null;
    };

    const submitBarcodeOnce = async barcode => {
        barcode = String(barcode || '').trim();
        if (!barcode) return;
        pendingScans.push(barcode);
        if (scanInFlight) return;
        const current = generation;
        scanInFlight = true;
        try {
            while (pendingScans.length && current === generation) {
                const component = livewireComponent();
                if (!component) { pendingScans.length = 0; break; }
                await component.call('barcodeScanned', pendingScans.shift());
                if (current === generation) scannerRoot()?.querySelector('[data-vx-scan-error]')?.remove();
            }
        } catch (error) {
            if (current !== generation) return;
            pendingScans.length = 0;
            // A failed response may follow a committed write. Never retry automatically.
            if (current === generation && scannerRoot()) {
                let status = scannerRoot().querySelector('[data-vx-scan-error]');
                if (!status) {
                    status = document.createElement('p');
                    status.dataset.vxScanError = '1';
                    status.setAttribute('role', 'alert');
                    scannerRoot().prepend(status);
                }
                status.textContent = 'Scan could not be confirmed. Check inventory before trying again.';
            }
        } finally {
            if (current === generation) scanInFlight = false;
        }
    };

    const clearHardwareInput = input => {
        input.value = '';
        // Explicit barcodeScanned() owns the server submission.
    };

    // Hardware/Bluetooth scanners normally send Enter after the code. Capture
    // that before Livewire's keydown handler so the same scan cannot be submitted
    // once by Enter and again by the debounced updatedScanInput() hook.
    document.addEventListener('keydown', event => {
        const target = event.target;
        if (event.key !== 'Enter' || !(target instanceof HTMLInputElement)) return;
        if (target !== scanInput()) return;

        const barcode = target.value.trim();
        if (!barcode) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        clearHardwareInput(target);
        void submitBarcodeOnce(barcode);
    }, true);

    // Camera scans used to hit Alpine's $wire.set(...).then(submitScan()), which
    // can also invoke updatedScanInput() and create a second request. Own the
    // event on the scanner page and call the server's barcodeScanned() method once.
    window.addEventListener('barcode-scanned', event => {
        if (!scannerRoot()) return;
        const barcode = String(event?.detail?.value || '').trim();
        if (!barcode) return;

        event.preventDefault?.();
        event.stopImmediatePropagation();
        void submitBarcodeOnce(barcode);
    }, true);

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
        const markup = `<button type="button">${showReceived ? 'Hide received pallets' : `Show received pallets${received.length ? ` (${received.length})` : ''}`}</button><span>${showReceived ? 'Showing completed and open deliveries.' : 'Completed pallets are hidden by default.'}</span>`;
        // Mutating the observed subtree again would schedule another pass forever.
        if (controls.dataset.markup === markup) return;
        controls.dataset.markup = markup;
        controls.innerHTML = markup;
        controls.querySelector('button')?.addEventListener('click', () => {
            showReceived = !showReceived;
            enhanceReceivedPalletFilter();
        });
    };

    const inspect = () => enhanceReceivedPalletFilter();

    const observer = new MutationObserver(() => {
        clearTimeout(window.__vxScannerReliabilityTimer);
        window.__vxScannerReliabilityTimer = setTimeout(inspect, 80);
    });
    observer.observe(document.body, {childList:true, subtree:true});

    document.addEventListener('livewire:navigating', () => {
        generation++;
        pendingScans.length = 0;
        scanInFlight = false;
    });

    document.addEventListener('livewire:navigated', () => {
        generation++;
        pendingScans.length = 0;
        showReceived = false;
        scanInFlight = false;
        setTimeout(inspect, 80);
    });

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', inspect, {once:true});
    else inspect();
})();
</script>