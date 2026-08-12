console.log('[app.js] Starting');

// Import barcode scanner first - critical for camera functionality
import { initScanner, stopScanner, toggleFlashlight } from './barcode-scanner.js';
import { BrowserMultiFormatReader } from '@zxing/browser';

console.log('[app.js] Critical imports complete');

// Make barcode scanner globally available FIRST - before loading other modules
window.barcodeScanner = {
    init: initScanner,
    stop: stopScanner,
    toggleFlashlight: toggleFlashlight,
    BrowserMultiFormatReader: BrowserMultiFormatReader
};

console.log('[app.js] window.barcodeScanner set:', window.barcodeScanner);

// Load optional modules asynchronously so they don't block barcode scanner
Promise.all([
    import('./feedback-annotation.js').catch(e => console.warn('[app.js] feedback-annotation failed:', e.message)),
    import('./animations.js').catch(e => console.warn('[app.js] animations failed:', e.message)),
    import('./ui-enhancements.js').catch(e => console.warn('[app.js] ui-enhancements failed:', e.message)),
    import('./ux-enhancements.js').catch(e => console.warn('[app.js] ux-enhancements failed:', e.message)),
    import('./mobile-enhancements.js').catch(e => console.warn('[app.js] mobile-enhancements failed:', e.message)),
    import('./ui-improvements.js').catch(e => console.warn('[app.js] ui-improvements failed:', e.message)),
    import('./responsive-data-tables.js').catch(e => console.warn('[app.js] responsive-data-tables failed:', e.message)),
]).then(() => console.log('[app.js] All optional modules loaded'))
  .catch(e => console.warn('[app.js] Error loading optional modules:', e.message));
