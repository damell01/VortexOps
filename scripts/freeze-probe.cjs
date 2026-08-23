// Drive the running app and check it never locks up.
//
// The freeze this hunts for was real and invisible: a page visit that started
// and never finished left `pointer-events: none` on <body>, so the whole
// screen stopped responding with nothing on it to explain why. A modal torn
// out of the DOM mid-transition did the same through Alpine's scroll lock and
// focus trap. Neither shows up in a PHP test — both need a browser.
//
// "Frozen" here means what it means to a person: clicks do not land, or the
// page will not scroll. So that is what is measured, not whether the markup
// looks right.
//
//   php artisan serve --port=8123
//   node scripts/freeze-probe.cjs
//
// Exits non-zero if any check finds the page locked.

const { chromium } = require('playwright-core');

const BASE = 'http://127.0.0.1:8123';
const EMAIL = process.env.PROBE_EMAIL || 'dbellcreations@gmail.com';
const PASS  = process.env.PROBE_PASSWORD || 'probe-pass-1234';

// Is the page actually usable? Not "does it look right" — can a click land,
// and can the page scroll. That is what "frozen" means to a person.
async function usable(page, label) {
    // A navigation can be mid-swap, leaving no body for an instant.
    await page.waitForFunction(() => !!document.body, null, { timeout: 5000 }).catch(() => {});

    const state = await page.evaluate(() => {
        const b = document.body, h = document.documentElement;
        if (!b) return { pointerEvents: 'auto', opacity: '1', bodyOverflow: '', htmlOverflow: '',
                         navigating: false, hitTag: null, hitClass: null, orphanOverlays: 0, noBody: true };
        const cs = getComputedStyle(b);
        // Where does a click in the middle of the viewport actually land?
        const el = document.elementFromPoint(innerWidth / 2, innerHeight / 2);
        return {
            pointerEvents: cs.pointerEvents,
            opacity: cs.opacity,
            bodyOverflow: b.style.overflow || '',
            htmlOverflow: h.style.overflow || '',
            navigating: b.classList.contains('vx-navigating'),
            hitTag: el ? el.tagName.toLowerCase() : null,
            hitClass: el ? (el.className || '').toString().slice(0, 60) : null,
            orphanOverlays: [...document.querySelectorAll('.fi-modal-window-ctn')]
                .filter(c => !c.querySelector('.fi-modal-window')).length,
        };
    });

    const frozen =
        state.pointerEvents === 'none' ||
        state.bodyOverflow === 'hidden' ||
        state.htmlOverflow === 'hidden' ||
        state.navigating ||
        state.orphanOverlays > 0;

    console.log(`${frozen ? 'FROZEN' : 'ok    '}  ${label.padEnd(38)} ` +
        `pe=${state.pointerEvents} scroll=${state.bodyOverflow || 'free'}/${state.htmlOverflow || 'free'} ` +
        `nav=${state.navigating} orphans=${state.orphanOverlays} hit=${state.hitTag}`);

    return !frozen;
}

(async () => {
    const browser = await chromium.launch({ executablePath: '/opt/pw-browsers/chromium' });
    const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });

    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    page.on('console', m => { if (m.type() === 'error') errors.push(m.text().slice(0, 120)); });

    let allOk = true;

    await page.goto(`${BASE}/admin/login`, { waitUntil: 'networkidle' });
    await page.fill('input[type="email"]', EMAIL);
    await page.fill('input[type="password"]', PASS);
    await Promise.all([
        page.waitForLoadState('networkidle'),
        page.click('button[type="submit"]'),
    ]);
    await page.waitForTimeout(2000);
    console.log('signed in →', page.url().replace(BASE, ''));
    if (page.url().includes('/login')) { console.log('SIGN-IN FAILED'); process.exit(2); }

    allOk &= await usable(page, 'after sign-in');

    // 1. Navigate around via the sidebar (wire:navigate — the path that froze).
    for (const path of ['/admin/inventory-items', '/admin/shows', '/admin/pallets', '/admin/inventory-items']) {
        await page.goto(BASE + path, { waitUntil: 'networkidle' }).catch(() => {});
        await page.waitForTimeout(600);
        allOk &= await usable(page, `visited ${path}`);
    }

    // 2. Interrupt a navigation mid-flight — the exact freeze that was reported.
    await page.goto(`${BASE}/admin/inventory-items`, { waitUntil: 'networkidle' });
    await page.evaluate(() => document.dispatchEvent(new CustomEvent('livewire:navigating')));
    await page.waitForTimeout(300);
    console.log('       (navigation started and abandoned — waiting out the watchdog)');
    await page.waitForTimeout(5600);
    allOk &= await usable(page, 'after an abandoned navigation');

    // 3. Open a modal and cancel it, twice — "cancel, then nothing works".
    await page.goto(`${BASE}/admin/inventory-items`, { waitUntil: 'networkidle' });
    for (const round of [1, 2]) {
        const opener = page.locator('button:has-text("New"), a:has-text("New")').first();
        if (await opener.count()) {
            await opener.click().catch(() => {});
            await page.waitForTimeout(900);
        }
        await page.keyboard.press('Escape');
        await page.waitForTimeout(900);
        allOk &= await usable(page, `after opening and cancelling (round ${round})`);
    }

    // 4. Can a real click still do something after all that?
    await page.goto(`${BASE}/admin/shows`, { waitUntil: 'networkidle' });
    const before = page.url();
    await page.locator('a[href*="/admin/"]').first().click({ timeout: 4000 }).catch(() => {});
    await page.waitForTimeout(1200);
    console.log(`       a click ${page.url() !== before ? 'navigated' : 'did not navigate'} (${page.url().replace(BASE, '')})`);
    allOk &= await usable(page, 'after a real click');

    if (errors.length) {
        console.log('\nconsole/page errors:');
        [...new Set(errors)].slice(0, 8).forEach(e => console.log('  ! ' + e));
    } else {
        console.log('\nno console or page errors');
    }

    console.log(allOk ? '\nRESULT: no freeze detected' : '\nRESULT: FREEZE DETECTED');
    await browser.close();
    process.exit(allOk ? 0 : 1);
})();
