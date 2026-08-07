import './feedback-annotation.js';
import './animations.js';
import './ui-enhancements.js';
import './ux-enhancements.js';
import './mobile-enhancements.js';
import './ui-improvements.js';
import { initScanner, stopScanner, toggleFlashlight } from './barcode-scanner.js';
import { BrowserMultiFormatReader } from '@zxing/browser';

// Make barcode scanner globally available
window.barcodeScanner = {
    init: initScanner,
    stop: stopScanner,
    toggleFlashlight: toggleFlashlight,
    BrowserMultiFormatReader: BrowserMultiFormatReader
};
