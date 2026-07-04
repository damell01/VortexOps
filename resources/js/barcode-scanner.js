import { BarcodeDetector } from 'barcode-detector/pure';

// Polyfill BarcodeDetector for browsers that don't support it natively
// (iOS Safari, Firefox). Native Chrome/Edge/Android Chrome take the native path.
if (!('BarcodeDetector' in window)) {
    window.BarcodeDetector = BarcodeDetector;
}
