from pathlib import Path

p = Path('scripts/whatnot-scraper.cjs')
s = p.read_text()

old = r'''async function launchWithProfileRecovery(userDataDir, opts = {}) {
  try {
    return await launchPersistentContextViaCdp(userDataDir, opts);
  } catch (e) {
    if (!/died during startup/.test(e.message || '')) throw e;

    const fs = require('fs');
    const quarantine = userDataDir + '.broken';

    try {
      fs.rmSync(quarantine, { recursive: true, force: true });   // keep only the latest
      fs.renameSync(userDataDir, quarantine);
      info('chromium would not start on this profile — moved it to', quarantine);
      info('starting a clean profile; the session re-loads from the cookie file');
    } catch (moveError) {
      info('could not move the profile aside:', moveError.message);
      throw e;
    }

    return await launchPersistentContextViaCdp(userDataDir, opts);
  }
}'''

new = r'''async function launchWithProfileRecovery(userDataDir, opts = {}) {
  const isStartupFailure = (e) => /died during startup|exited before DevTools listener was ready|Timed out waiting for Chromium DevTools listener/i.test(e?.message || '');

  try {
    return await launchPersistentContextViaCdp(userDataDir, opts);
  } catch (firstError) {
    if (!isStartupFailure(firstError)) throw firstError;

    info('chromium startup failed once — cleaning stale profile processes and retrying the same profile');
    killStaleProcessesForProfile(userDataDir);
    clearStaleSingletonLock(userDataDir);
    await new Promise(r => setTimeout(r, 1200));

    try {
      return await launchPersistentContextViaCdp(userDataDir, opts);
    } catch (secondError) {
      if (!isStartupFailure(secondError)) throw secondError;

      const fs = require('fs');
      const quarantine = userDataDir + '.broken';

      try {
        fs.rmSync(quarantine, { recursive: true, force: true });
        if (fs.existsSync(userDataDir)) {
          fs.renameSync(userDataDir, quarantine);
          info('chromium failed twice on this profile — moved it to', quarantine);
        }
        info('starting a clean profile; the session re-loads from the cookie file');
      } catch (moveError) {
        info('could not move the profile aside:', moveError.message);
        throw secondError;
      }

      return await launchPersistentContextViaCdp(userDataDir, opts);
    }
  }
}'''

if s.count(old) != 1:
    raise SystemExit(f'launchWithProfileRecovery block expected once, found {s.count(old)}')
s = s.replace(old, new, 1)

old2 = "reject(new Error(`Chromium exited before DevTools listener was ready (code=${code} signal=${signal})`));"
new2 = "reject(new Error(`Chromium exited before DevTools listener was ready (code=${code} signal=${signal}). stderr: ${buf.trim().slice(-1200) || '(empty)'}`));"
if s.count(old2) != 1:
    raise SystemExit(f'onExit error line expected once, found {s.count(old2)}')
s = s.replace(old2, new2, 1)

p.write_text(s)
