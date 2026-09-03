// The barcode scanner and its decoding library are 469KB — more than a fifth
// of everything the panel ships — and were imported here at the top level, so
// every page paid for them: the dashboard, the payouts table, the settings
// screen. Three pages actually scan.
//
// They load on first use instead, behind ensureBarcodeScanner(). The import is
// cached by the browser and by this promise, so opening the camera twice costs
// one download and the pages that never scan cost none.
let scannerPromise = null;

window.ensureBarcodeScanner = function () {
    if (! scannerPromise) {
        scannerPromise = Promise.all([
            import('./barcode-scanner.js'),
            import('@zxing/browser'),
        ]).then(([scanner, zxing]) => {
            window.barcodeScanner = {
                init: scanner.initScanner,
                stop: scanner.stopScanner,
                toggleFlashlight: scanner.toggleFlashlight,
                BrowserMultiFormatReader: zxing.BrowserMultiFormatReader,
            };

            return window.barcodeScanner;
        }).catch((e) => {
            // Cleared so a failed download — a dropped connection mid-scan —
            // can be retried rather than poisoning every later attempt.
            scannerPromise = null;
            console.warn('[app.js] barcode scanner failed to load:', e.message);

            return null;
        });
    }

    return scannerPromise;
};

// Livewire v4 uses a hash-based update endpoint. After a deploy an already-open
// SPA tab can briefly keep the old endpoint and Livewire's default behavior is
// to render the server's 404 page inside a large diagnostic iframe. Recover by
// refreshing once, and suppress the iframe if the server still returns 404 so
// users are never trapped behind a fake-looking modal.
document.addEventListener('livewire:init', () => {
    if (! window.Livewire?.interceptRequest) return;

    window.Livewire.interceptRequest(({ request, onError }) => {
        onError(({ response, preventDefault }) => {
            if (response?.status !== 404) return;

            const uri = String(request?.uri || response?.url || '');
            if (! uri.includes('/livewire-')) return;

            preventDefault();

            const storageKey = 'vortexops-livewire-404-reloaded-at';
            const lastReload = Number(sessionStorage.getItem(storageKey) || 0);
            const now = Date.now();

            if (now - lastReload > 15000) {
                sessionStorage.setItem(storageKey, String(now));
                window.location.reload();
                return;
            }

            console.warn('[VortexOps] Livewire update endpoint returned 404 after refresh:', uri);
        });
    });
});

// Load optional modules asynchronously so they never block first paint.
Promise.all([
    import('./feedback-annotation.js').catch(e => console.warn('[app.js] feedback-annotation failed:', e.message)),
    import('./animations.js').catch(e => console.warn('[app.js] animations failed:', e.message)),
    import('./ui-enhancements.js').catch(e => console.warn('[app.js] ui-enhancements failed:', e.message)),
    import('./ux-enhancements.js').catch(e => console.warn('[app.js] ux-enhancements failed:', e.message)),
    import('./mobile-enhancements.js').catch(e => console.warn('[app.js] mobile-enhancements failed:', e.message)),
    import('./ui-improvements.js').catch(e => console.warn('[app.js] ui-improvements failed:', e.message)),
    import('./responsive-data-tables.js').catch(e => console.warn('[app.js] responsive-data-tables failed:', e.message)),
    import('./modal-visibility.js').catch(e => console.warn('[app.js] modal-visibility failed:', e.message)),
    import('./modal-lifecycle.js').catch(e => console.warn('[app.js] modal-lifecycle failed:', e.message)),
]).catch(e => console.warn('[app.js] Error loading optional modules:', e.message));
