'use strict';

/**
 * One fingerprint, consistently told.
 *
 * Lifted verbatim from scripts/whatnot-scraper.cjs, which reaches the Seller Hub
 * from this server while whatnot-production-sync.cjs was challenged on the same
 * profile, cookies and address.
 *
 * production-sync masked four things — webdriver, languages, navigator.platform,
 * window.chrome — and left the rest telling the truth. So its User-Agent and
 * Client Hints claimed Windows while navigator.userAgentData still said Linux and
 * WebGL still reported SwiftShader, which is what headless Chrome renders with.
 * whatnot-scraper had to fix exactly this and wrote down why:
 *
 *   "Three operating systems in one fingerprint is a stronger signal than any
 *    single unusual value, because no real machine produces it."
 *
 * That is why the interstitial ran and never cleared. Cloudflare's challenge JS
 * reads these values, finds a machine that cannot exist, and declines to issue
 * clearance — so the page reloads itself until the wait runs out.
 *
 * Shared rather than copied, because a fingerprint that is half-applied is worse
 * than one not applied at all: the inconsistency is the signal.
 */
function applyStealth(context) {
  return context.addInitScript(() => {
    // Core: webdriver flag
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });

    // Chrome-specific object that headless Chrome doesn't set by default
    if (!window.chrome) window.chrome = { runtime: {} };

    // Realistic plugin list
    Object.defineProperty(navigator, 'plugins', {
      get: () => {
        const arr = [{ name: 'Chrome PDF Plugin' }, { name: 'Chrome PDF Viewer' }, { name: 'Native Client' }];
        arr.item = i => arr[i];
        arr.namedItem = n => arr.find(p => p.name === n) || null;
        arr.refresh = () => {};
        return arr;
      },
    });

    Object.defineProperty(navigator, 'languages', { get: () => ['en-US', 'en'] });

    // Screen dimensions matching the viewport (headless can leave these at 0)
    try {
      Object.defineProperty(screen, 'availWidth',  { get: () => 1920 });
      Object.defineProperty(screen, 'availHeight', { get: () => 1040 });
    } catch (_) {}

    // Permissions API — headless denies notifications, real browsers default to "default"
    try {
      const origQuery = window.navigator.permissions.query.bind(window.navigator.permissions);
      window.navigator.permissions.query = (params) => {
        if (params && params.name === 'notifications') {
          return Promise.resolve({ state: 'default', onchange: null });
        }
        return origQuery(params);
      };
    } catch (_) {}

    // WebGL — headless reports "SwiftShader", which is detectable.
    //
    // The replacement has to agree with the User-Agent. These strings used to
    // say "Intel Iris OpenGL Engine", which is what macOS Chrome reports, on a
    // browser whose UA and Client Hints both claim Windows. A real Windows
    // Chrome reports an ANGLE/Direct3D string, so the old pair described a
    // machine that cannot exist.
    try {
      const getParam = WebGLRenderingContext.prototype.getParameter;
      WebGLRenderingContext.prototype.getParameter = function (parameter) {
        if (parameter === 37446) return 'Google Inc. (Intel)';   // UNMASKED_VENDOR_WEBGL
        if (parameter === 37445) {                               // UNMASKED_RENDERER_WEBGL
          return 'ANGLE (Intel, Intel(R) UHD Graphics 620 (0x00003E9B) Direct3D11 vs_5_0 ps_5_0, D3D11)';
        }
        return getParam.call(this, parameter);
      };
    } catch (_) {}

    // navigator.platform and userAgentData were left alone, so they reported
    // the truth — Linux — while the UA, the Client Hints headers and the WebGL
    // strings all claimed something else. Three operating systems in one
    // fingerprint is a stronger signal than any single unusual value, because
    // no real machine produces it.
    try {
      Object.defineProperty(navigator, 'platform', { get: () => 'Win32' });
    } catch (_) {}

    try {
      if (navigator.userAgentData) {
        Object.defineProperty(navigator.userAgentData, 'platform', { get: () => 'Windows' });
      }
    } catch (_) {}

    // A screen exactly the size of the viewport is not a shape real windows
    // take — there is always browser chrome and usually a taskbar.
    try {
      Object.defineProperty(screen, 'width',  { get: () => 1920 });
      Object.defineProperty(screen, 'height', { get: () => 1080 });
      Object.defineProperty(screen, 'colorDepth', { get: () => 24 });
      Object.defineProperty(screen, 'pixelDepth', { get: () => 24 });
    } catch (_) {}
  });
}

module.exports = { applyStealth };
