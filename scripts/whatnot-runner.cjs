'use strict';

const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

const projectRoot = path.resolve(__dirname, '..');
const mode = String(process.env.WHATNOT_MODE || 'analytics').trim();
const scraplingModes = new Set(['shows', 'analytics', 'orders-batch', 'shipments-batch', 'ledger']);
const explicitBackendRaw = String(process.env.WHATNOT_BROWSER_BACKEND || '').trim().toLowerCase();
const explicitBackend = explicitBackendRaw === 'scrapling' ? 'scrapling-stealthy' : explicitBackendRaw;
const legacyLocal = explicitBackend === 'local';
let backend = scraplingModes.has(mode) && (!explicitBackend || legacyLocal)
  ? 'scrapling-stealthy'
  : (explicitBackend || 'local');
const fallbackEnabled = String(process.env.WHATNOT_SCRAPER_FALLBACK || '0').trim() !== '0';
const httpPreflightEnabled = String(process.env.WHATNOT_HTTP_PREFLIGHT || '0').trim() === '1';
const httpPreflightStrict = String(process.env.WHATNOT_HTTP_PREFLIGHT_STRICT || '0').trim() === '1';

function normalizeChannel(value) { return String(value || '').trim().replace(/^@+/, '').toLowerCase(); }
function channelFromArgs() {
  const args = process.argv.slice(2);
  for (let i = 0; i < args.length; i += 1) {
    if (args[i].startsWith('--channel=')) return args[i].slice('--channel='.length);
    if (args[i] === '--channel' && args[i + 1]) return args[i + 1];
  }
  return '';
}
function scopedEnvironment() {
  const env = { ...process.env };
  const channel = normalizeChannel(env.WHATNOT_CHANNEL_NAME || channelFromArgs());
  if (channel) {
    if (!/^[a-z0-9._-]+$/.test(channel)) { console.error(`[whatnot] Invalid channel name: ${channel}`); process.exit(2); }
    env.WHATNOT_CHANNEL_NAME = channel;
  }
  const isolate = Boolean(channel) && String(env.WHATNOT_CHANNEL_ISOLATE || '0').trim() === '1';
  const configuredRoot = env.WHATNOT_STATE_DIR ? path.resolve(projectRoot, env.WHATNOT_STATE_DIR) : path.resolve(projectRoot, 'storage', 'whatnot-channels');
  if (isolate) {
    const channelRoot = path.join(configuredRoot, channel);
    env.WHATNOT_USER_DATA_DIR = path.join(channelRoot, 'browser-profile');
    env.WHATNOT_COOKIES_FILE = path.join(channelRoot, 'cookies.json');
    env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR = path.join(channelRoot, 'diagnostics');
    env.WHATNOT_HTTP_DIAGNOSTICS_DIR = path.join(channelRoot, 'diagnostics');
    fs.mkdirSync(env.WHATNOT_USER_DATA_DIR, { recursive: true });
    fs.mkdirSync(env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR, { recursive: true });
    console.error(`[whatnot] CHANNEL_SCOPE requested=@${channel} session=isolated state=${path.relative(projectRoot, channelRoot)}`);
  } else {
    const sharedProfile = path.resolve(projectRoot, 'storage', 'whatnot-scrapling-profile');
    env.WHATNOT_USER_DATA_DIR = env.WHATNOT_USER_DATA_DIR || sharedProfile;
    fs.mkdirSync(env.WHATNOT_USER_DATA_DIR, { recursive: true });
    if (channel) {
      const channelRoot = path.join(configuredRoot, channel);
      env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR = env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR || path.join(channelRoot, 'diagnostics');
      env.WHATNOT_HTTP_DIAGNOSTICS_DIR = env.WHATNOT_HTTP_DIAGNOSTICS_DIR || path.join(channelRoot, 'diagnostics');
      fs.mkdirSync(env.WHATNOT_SCRAPLING_DIAGNOSTICS_DIR, { recursive: true });
      console.error(`[whatnot] CHANNEL_SCOPE requested=@${channel} session=scrapling-owned profile=${path.relative(projectRoot, env.WHATNOT_USER_DATA_DIR)}`);
    }
  }
  return env;
}
const childEnv = scopedEnvironment();

function resolvePythonBin() {
  const configured = String(childEnv.WHATNOT_PYTHON_BIN || '').trim();
  if (configured) return configured;
  const projectVenv = path.join(projectRoot, '.venv', 'bin', 'python');
  if (fs.existsSync(projectVenv)) return projectVenv;
  return 'python3';
}

function localCdpEndpoint() { return String(childEnv.WHATNOT_ATTACH_CDP_URL || 'http://127.0.0.1:9222').trim().replace(/\/$/, ''); }
function localCdpAvailable(timeoutSeconds = 2) {
  const endpoint = localCdpEndpoint(); let parsed; try { parsed = new URL(endpoint); } catch { return false; }
  if (!['127.0.0.1','localhost','::1'].includes((parsed.hostname || '').toLowerCase())) return false;
  const result = spawnSync('curl', ['-fsS','--max-time',String(timeoutSeconds),endpoint + '/json/version'], { cwd: projectRoot, encoding: 'utf8' });
  if (result.status !== 0) return false; try { const data=JSON.parse(result.stdout||'{}'); return Boolean(data.webSocketDebuggerUrl&&data.Browser); } catch { return false; }
}
function runHttpHealth() { const python=resolvePythonBin(); return spawnSync(python,[path.join(__dirname,'whatnot-http-health.py')],{env:childEnv,cwd:projectRoot,stdio:'inherit'}); }
function runScraplingStealthy() {
  const python=resolvePythonBin();
  const useCdp=String(childEnv.WHATNOT_SCRAPLING_USE_CDP||'0').trim()==='1';
  const env={...childEnv,WHATNOT_BROWSER_BACKEND:'scrapling',WHATNOT_SCRAPLING_USE_CDP:useCdp?'1':'0',WHATNOT_PYTHON_BIN:python};

  if (mode === 'orders-batch' && !String(env.WHATNOT_ORDER_DETAIL_ENRICH || '').trim()) {
    env.WHATNOT_ORDER_DETAIL_ENRICH = '0';
  }

  if (useCdp) env.WHATNOT_SCRAPLING_CDP_URL=childEnv.WHATNOT_SCRAPLING_CDP_URL||localCdpEndpoint();
  const transport=useCdp?`cdp-diagnostic:${env.WHATNOT_SCRAPLING_CDP_URL}`:'owned-browser';
  const webrtc=String(env.WHATNOT_SCRAPLING_BLOCK_WEBRTC||'false').trim().toLowerCase();
  const canvas=String(env.WHATNOT_SCRAPLING_HIDE_CANVAS||'false').trim().toLowerCase();
  const webgl=String(env.WHATNOT_SCRAPLING_ALLOW_WEBGL||'true').trim().toLowerCase();
  const lockFile=String(env.WHATNOT_BROWSER_LOCK_FILE||path.join(projectRoot,'storage','whatnot-browser.lock')).trim();
  const lockWait=Math.max(1,parseInt(String(env.WHATNOT_BROWSER_LOCK_WAIT||'1200'),10)||1200);
  const scraperScript = mode === 'shipments-batch'
    ? path.join(__dirname, 'whatnot-shipments-hardened.py')
    : path.join(__dirname, 'whatnot-scrapling-stealthy.py');
  fs.mkdirSync(path.dirname(lockFile),{recursive:true});
  process.stderr.write(`[whatnot] production browser backend: scrapling-stealthy (mode=${mode}, transport=${transport}, profile=${env.WHATNOT_USER_DATA_DIR||'(temporary)'}, python=${python}, solve_cloudflare=false, block_webrtc=${webrtc}, hide_canvas=${canvas}, allow_webgl=${webgl})\n`);
  if (mode === 'orders-batch') process.stderr.write(`[whatnot] ORDER_DETAIL_ENRICH=${env.WHATNOT_ORDER_DETAIL_ENRICH}\n`);
  if (mode === 'shipments-batch') process.stderr.write('[whatnot] SHIPMENT_EXTRACTOR=hardened\n');
  process.stderr.write(`[whatnot] BROWSER_LOCK_WAIT file=${lockFile} timeout=${lockWait}s\n`);
  const result=spawnSync('flock',['-w',String(lockWait),lockFile,python,scraperScript],{env,cwd:projectRoot,stdio:'inherit'});
  if(result.status===0) process.stderr.write('[whatnot] BROWSER_LOCK_RELEASED\n');
  else if(result.status===1) process.stderr.write(`[whatnot] BROWSER_LOCK_TIMEOUT waited=${lockWait}s\n`);
  return result;
}
function runAttachedBrowser() {
  const env={...childEnv,WHATNOT_BROWSER_BACKEND:'local',WHATNOT_ATTACH_EXISTING_BROWSER:'1',WHATNOT_ATTACH_CDP_URL:childEnv.WHATNOT_ATTACH_CDP_URL||'http://127.0.0.1:9222'};
  process.stderr.write(`[whatnot] diagnostic browser backend: attached-persistent-chromium (mode=${mode}, cdp=${env.WHATNOT_ATTACH_CDP_URL})\n`);
  return spawnSync(process.execPath,[path.join(__dirname,'whatnot-attached-runner.cjs'),...process.argv.slice(2)],{env,cwd:projectRoot,stdio:'inherit'});
}
function runPlaywright() {
  if (localCdpAvailable()) return runAttachedBrowser();
  const env={...childEnv,WHATNOT_BROWSER_BACKEND:'local'};
  return spawnSync(process.execPath,[path.join(__dirname,'whatnot-scraper.cjs'),...process.argv.slice(2)],{env,cwd:projectRoot,stdio:'inherit'});
}
function exitFor(result,label) { if(result.error){process.stderr.write(`[whatnot] ${label} failed to start: ${result.error.message}\n`);process.exit(1);} process.exit(result.status==null?1:result.status); }

if (backend === 'http-health') exitFor(runHttpHealth(),'HTTP health adapter');
if (httpPreflightEnabled && backend !== 'attached') { const p=runHttpHealth(); const s=p.status==null?1:p.status; if((p.error||s!==0)&&httpPreflightStrict) process.exit(s||1); }
if (scraplingModes.has(mode)) {
  const result=runScraplingStealthy(); const status=result.status==null?1:result.status;
  if(status===0) process.exit(0);
  if(fallbackEnabled && !new Set([3,4,5]).has(status)) exitFor(runPlaywright(),'Playwright fallback');
  exitFor(result,'Scrapling StealthySession runner');
}
if (backend === 'scrapling-stealthy') process.stderr.write(`[whatnot] mode=${mode} is an auth/diagnostic utility mode; retaining the existing browser utility runner.\n`);
if (backend === 'attached' || (backend === 'local' && localCdpAvailable())) exitFor(runAttachedBrowser(),'Attached browser runner');
exitFor(runPlaywright(),'Playwright runner');