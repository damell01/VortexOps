const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const view = fs.readFileSync(path.join(__dirname, '../../resources/views/filament/components/camera-barcode-scanner.blade.php'), 'utf8');
const script = view.match(/@verbatim\s*<script>([\s\S]*?)<\/script>/)?.[1];
assert.ok(script, 'scanner script is available');

function scannerFixture({ flashSupported = true } = {}) {
    const events = [];
    const flashConstraints = [];
    const track = {
        getCapabilities() { return { torch: flashSupported }; },
        async applyConstraints(value) { flashConstraints.push(value); },
        stop() {},
    };
    const tracks = [track];
    const timers = [];
    const callbacks = {};
    const video = { videoWidth: 640, videoHeight: 480, srcObject: { getTracks: () => tracks, getVideoTracks: () => tracks } };
    const canvas = { width: 640, height: 480 };
    const overlay = { clientWidth: 0, clientHeight: 0, setAttribute(name, value) { this[name] = value; } };
    const readerDiv = {
        innerHTML: '',
        parentElement: { clientWidth: 320, clientHeight: 480 },
        querySelector(selector) { return selector === 'video' ? video : canvas; },
        querySelectorAll() { return [video]; },
    };
    const Quagga = {
        init(config, callback) { callbacks.config = config; callbacks.ready = callback; },
        start() { callbacks.started = true; },
        stop() { callbacks.stopped = true; },
        onDetected(callback) { callbacks.detected = callback; },
        onProcessed(callback) { callbacks.processed = callback; },
        offDetected(callback) { assert.equal(callback, callbacks.detected); },
        offProcessed(callback) { assert.equal(callback, callbacks.processed); },
    };
    const window = { Quagga, QuaggaInitialized: false, dispatchEvent(event) { events.push(event); } };
    const context = vm.createContext({
        window, Quagga,
        navigator: { hardwareConcurrency: 4, vibrate() {} },
        document: { activeElement: null, body: { classList: { add() {}, remove() {} } }, querySelector() { return null; } },
        CustomEvent: class { constructor(type, options) { this.type = type; this.detail = options.detail; } },
        setTimeout(callback, duration) { const timer = { callback, duration }; timers.push(timer); return timer; },
        clearTimeout() {},
        console,
    });
    vm.runInContext(script, context);
    const scanner = vm.runInContext('cameraScanner()', context);
    scanner.$refs = { readerDiv, barcodeOverlay: overlay, manualInput: { value: '' } };
    scanner.$nextTick = async callback => callback?.();
    return { scanner, callbacks, events, timers, overlay, flashConstraints };
}

test('camera bounds follow the barcode through object-fit cropping', async () => {
    const { scanner, callbacks, overlay } = scannerFixture();
    await scanner.open();
    assert.equal(callbacks.config.inputStream.area.top, '30%');
    assert.equal(callbacks.config.inputStream.area.left, '15%');
    assert.equal(callbacks.config.inputStream.area.right, '15%');
    assert.equal(callbacks.config.locator.patchSize, 'small');
    assert.equal(callbacks.config.inputStream.constraints.width.ideal, 960);
    assert.deepEqual(Array.from(callbacks.config.decoder.readers), ['upc_reader', 'ean_reader', 'ean_8_reader', 'upc_e_reader', 'code_128_reader']);
    callbacks.ready(null);
    assert.equal(callbacks.started, true);

    const box = [[200, 180], [440, 180], [440, 300], [200, 300]];
    callbacks.processed({ boxes: [box] });
    assert.equal(scanner.barcodeOutline, '', 'unread label edges do not trigger a false outline');
    callbacks.processed({ box, codeResult: { code: '012345678905' } });
    assert.equal(scanner.barcodeOutline, '40.0,180.0 280.0,180.0 280.0,300.0 40.0,300.0');
    assert.equal(overlay.viewBox, '0 0 320 480');
});

test('two clean reads submit once and release the camera promptly', async () => {
    const { scanner, callbacks, events, timers } = scannerFixture();
    await scanner.open();
    callbacks.ready(null);
    const result = { codeResult: { code: '012345678905', decodedCodes: [{ error: .04 }] }, box: [[200, 180], [440, 180], [440, 300], [200, 300]] };

    callbacks.detected({ ...result, codeResult: { ...result.codeResult, decodedCodes: [{ error: .3 }] } });
    assert.equal(scanner.detected, false, 'poor reads cannot submit');
    callbacks.detected(result);
    assert.equal(events.length, 0, 'one read cannot submit');
    callbacks.detected(result);
    callbacks.detected(result);
    assert.deepEqual(events.map(event => event.detail.value), ['012345678905']);
    assert.equal(timers.at(-1).duration, 120);

    timers.at(-1).callback();
    assert.equal(callbacks.stopped, true);
    assert.equal(scanner.barcodeOutline, '');
});

test('closing while the scanner initializes cannot restart the camera', async () => {
    const { scanner, callbacks } = scannerFixture();
    await scanner.open();
    await scanner.close();
    callbacks.ready(null);
    assert.equal(callbacks.started, undefined);
});

test('flash appears only for supported cameras and toggles the light', async () => {
    const { scanner, callbacks, flashConstraints } = scannerFixture();
    await scanner.open();
    callbacks.ready(null);
    assert.equal(scanner.flashAvailable, true);
    await scanner.toggleFlash();
    assert.equal(scanner.flashOn, true);
    await scanner.toggleFlash();
    assert.equal(scanner.flashOn, false);
    assert.deepEqual(flashConstraints.map(value => value.advanced[0].torch), [true, false]);
    await scanner.close();
    assert.equal(scanner.flashAvailable, false);

    const unsupported = scannerFixture({ flashSupported: false });
    await unsupported.scanner.open();
    unsupported.callbacks.ready(null);
    assert.equal(unsupported.scanner.flashAvailable, false);
});

test('concurrent scanner library requests wait for the same script load', async () => {
    const app = fs.readFileSync(path.join(__dirname, '../../resources/js/app.js'), 'utf8');
    const loader = app.match(/const vxScriptLoads = new Map\(\);[\s\S]*?(?=\/\/ Some Filament)/)?.[0];
    assert.ok(loader);
    const scripts = [];
    const window = {};
    const document = {
        getElementById(id) { return scripts.find(script => script.id === id) || null; },
        createElement() { return { remove() { scripts.splice(scripts.indexOf(this), 1); } }; },
        head: { appendChild(script) { scripts.push(script); } },
    };
    vm.runInNewContext(loader, { window, document, Map, Promise });
    const first = window.vxLoadScriptOnce('/vendor/quagga.min.js', 'vx-quagga');
    const second = window.vxLoadScriptOnce('/vendor/quagga.min.js', 'vx-quagga');
    assert.equal(first, second);
    assert.equal(scripts.length, 1);
    scripts[0].onload();
    await Promise.all([first, second]);
});

test('failed scanner library loads can be retried', async () => {
    const app = fs.readFileSync(path.join(__dirname, '../../resources/js/app.js'), 'utf8');
    const loader = app.match(/const vxScriptLoads = new Map\(\);[\s\S]*?(?=\/\/ Some Filament)/)?.[0];
    const scripts = [];
    const window = {};
    const document = {
        getElementById(id) { return scripts.find(script => script.id === id) || null; },
        createElement() { return { remove() { scripts.splice(scripts.indexOf(this), 1); } }; },
        head: { appendChild(script) { scripts.push(script); } },
    };
    vm.runInNewContext(loader, { window, document, Map, Promise });
    const first = window.vxLoadScriptOnce('/vendor/quagga.min.js', 'vx-quagga');
    scripts[0].onerror(new Error('network interruption'));
    await assert.rejects(first);
    assert.equal(scripts.length, 0);
    const retry = window.vxLoadScriptOnce('/vendor/quagga.min.js', 'vx-quagga');
    assert.equal(scripts.length, 1);
    scripts[0].onload();
    await retry;
});
