'use strict';

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function loadPlaywright(){
  try { const root=execSync('npm root -g',{encoding:'utf8',stdio:['pipe','pipe','pipe']}).trim(); return require(root+'/playwright'); } catch {}
  for(const p of ['/opt/node22/lib/node_modules/playwright','/usr/lib/node_modules/playwright','/usr/local/lib/node_modules/playwright']){ try{return require(p);}catch{} }
  throw new Error('Playwright not found');
}
const { chromium } = loadPlaywright();
const { launchWithProfileRecovery } = require('./lib/whatnot-browser.cjs');
const { applyStealth } = require('./lib/whatnot-stealth.cjs');
const DEBUG = process.env.WHATNOT_DEBUG === '1';
const ENRICH_IDS = (process.env.WHATNOT_ENRICH_IDS || '').split(',').map(s=>s.trim()).filter(Boolean);
const MAX_ENRICH = Math.max(0, Number(process.env.WHATNOT_ENRICH_LIMIT || 3));

function findChromium(){
  const explicit=process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH; if(explicit&&fs.existsSync(explicit))return explicit;
  const marker=path.join(__dirname,'../storage/chromium-path.txt'); try{const p=fs.readFileSync(marker,'utf8').trim();if(p&&fs.existsSync(p))return p;}catch{}
  try{const p=chromium.executablePath();if(p&&fs.existsSync(p))return p;}catch{}
  for(const p of ['/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome'])if(fs.existsSync(p))return p;
}
function isChallenge(text,title=''){ return /performing security verification|just a moment|verifying you are human|checking your browser|cf-chl|cf_chl|challenge-platform|ray id/i.test(`${title}\n${text}`); }

// Exit codes, matching scripts/whatnot-scraper.cjs so callers can read either
// script the same way. Everything here used to exit 1, so "the session lapsed"
// and "the selectors moved" were indistinguishable — and the advice printed by
// the PHP commands, which keys off these codes, could never be right.
const EXIT = {GENERAL:1, SELECTORS:2, CHALLENGE:3, RATE_LIMITED:4};
function fail(code,message){ const e=new Error(message); e.exitCode=code; return e; }
async function bodyText(page){ return page.locator('body').innerText().catch(()=> ''); }

const CHALLENGE_WAIT_MS = Number(process.env.WHATNOT_CHALLENGE_WAIT_MS || 45000);

/**
 * Body text, or null while the page is mid-navigation.
 *
 * The distinction is the whole point: the interstitial reloads itself, so
 * evaluate() throws "execution context was destroyed" exactly when it is
 * working. Returning '' there would read as an empty page and stop the wait one
 * poll before it succeeded.
 */
async function readBodyText(page,max=2000){
  try{
    return await page.evaluate(m=>(document.body?(document.body.innerText||''):'').substring(0,m),max);
  }catch{
    return null;
  }
}

/**
 * Wait out a Cloudflare interstitial rather than calling it a failure.
 *
 * Cloudflare's managed challenge is not a wall — it runs a few seconds of JS,
 * sets cf_clearance and reloads itself into the real page. This script
 * navigated with waitUntil:'domcontentloaded' and read the DOM immediately, so
 * it caught the interstitial mid-flight every single time and reported a bot
 * challenge. whatnot-scraper.cjs hit exactly this and fixed it by waiting; that
 * is why one of them reaches the Seller Hub from this machine and this one
 * never did.
 *
 * Returns true when the page is real, false when the challenge outlasts the
 * wait — at which point it genuinely is a wall and stopping is right.
 */
async function settleChallenge(page,timeoutMs=CHALLENGE_WAIT_MS){
  let body=await readBodyText(page);
  if(body!==null&&!isChallenge(body))return true;

  const started=Date.now();
  let announced=false;

  while(Date.now()-started<timeoutMs){
    await page.waitForTimeout(2000);
    body=await readBodyText(page);

    if(body===null)continue;               // reloading — that is the challenge working
    if(!isChallenge(body)){
      if(announced)console.error(`[whatnot-prod] challenge cleared after ${Math.round((Date.now()-started)/1000)}s`);
      return true;
    }

    if(!announced){
      console.error(`[whatnot-prod] cloudflare interstitial — waiting up to ${Math.round(timeoutMs/1000)}s for it to clear`);
      announced=true;
    }
  }

  if(!announced)return true;               // never actually saw a challenge
  console.error(`[whatnot-prod] challenge did not clear within ${Math.round(timeoutMs/1000)}s`);
  return false;
}
async function state(page){ const text=await bodyText(page),title=await page.title().catch(()=> ''); return {url:page.url(),title,challenged:isChallenge(text,title),text}; }

/**
 * Show what Whatnot actually served instead of only naming it a challenge.
 *
 * "Challenged" covers a Cloudflare interstitial, a signed-out page and a consent
 * wall alike, and telling them apart from the outside is guesswork — which is
 * how several plausible causes got ruled in and back out again. The page's own
 * title and first lines say which it is; the screenshot settles it.
 *
 * Printed unconditionally, not under --debug: by the time anyone thinks to
 * re-run with a flag, the session has usually moved on.
 */
async function reportBlockingPage(page,label){
  const st=await state(page);
  const shotPath=`/tmp/whatnot-prod-${label}.png`;

  await page.screenshot({path:shotPath,fullPage:false}).catch(()=>{});

  const lines=(st.text||'').split('\n').map(x=>x.trim()).filter(Boolean).slice(0,8);

  console.error(`[whatnot-prod] blocked at ${st.url}`);
  console.error(`[whatnot-prod]   title: ${st.title||'(none)'}`);
  for(const line of lines)console.error(`[whatnot-prod]   > ${line}`);
  console.error(`[whatnot-prod]   screenshot: ${shotPath}`);

  return st;
}
async function shot(page,name){ if(DEBUG) await page.screenshot({path:`/tmp/whatnot-prod-${name}.png`,fullPage:false}).catch(()=>{}); }

async function clickShows(page){
  if(/\/dashboard\/lives(?:[/?#]|$)/.test(page.url()))return true;
  const loc=page.locator('a[href="/dashboard/lives"],a[href*="/dashboard/lives"]').first();
  if(!(await loc.count().catch(()=>0)))return false;
  await loc.click({timeout:8000}).catch(()=>null);
  await page.waitForURL(/\/dashboard\/lives(?:[/?#]|$)/,{timeout:10000}).catch(()=>{});
  await page.waitForTimeout(2500);
  return /\/dashboard\/lives(?:[/?#]|$)/.test(page.url());
}

function tabLocator(page,name){
  if(name==='current') return page.locator('button[data-testid="tab-current"][role="tab"]').first();
  if(name==='upcoming') return page.locator('button[data-testid="tab-upcoming"][role="tab"]').first();
  return page.getByRole('tab',{name:'Past',exact:true}).first();
}
async function tabStates(page){
  return page.locator('ul[role="tablist"] button[role="tab"]').evaluateAll(btns=>btns.map(b=>({text:(b.textContent||'').trim(),selected:b.getAttribute('aria-selected'),testid:b.getAttribute('data-testid')}))).catch(()=>[]);
}
async function selectTab(page,name){
  const loc=tabLocator(page,name);
  if(!(await loc.count().catch(()=>0))){
    const states=await tabStates(page);
    if(name==='current') return {ok:true,missing:true,states};
    return {ok:false,missing:false,states};
  }
  if((await loc.getAttribute('aria-selected').catch(()=>null))==='true') return {ok:true,missing:false,states:await tabStates(page)};
  await loc.scrollIntoViewIfNeeded().catch(()=>{});
  await loc.click({timeout:8000}).catch(()=>null);
  await page.waitForTimeout(1800);
  if((await loc.getAttribute('aria-selected').catch(()=>null))==='true') return {ok:true,missing:false,states:await tabStates(page)};
  await loc.evaluate(el=>el.click()).catch(()=>null);
  await page.waitForTimeout(2500);
  const ok=(await loc.getAttribute('aria-selected').catch(()=>null))==='true';
  return {ok,missing:false,states:await tabStates(page)};
}

async function resetScroll(page){
  await page.evaluate(()=>{
    window.scrollTo(0,0);
    const els=[...document.querySelectorAll('*')].filter(el=>el.scrollHeight>el.clientHeight+200);
    for(const el of els)el.scrollTop=0;
  }).catch(()=>{});
  await page.waitForTimeout(900);
}
async function extractRows(page,kind){
  return page.locator('[data-testid="show-list-item"]').evaluateAll((rows,kind)=>rows.map(row=>{
    const title=row.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim()||null;
    const open=row.querySelector('a[href^="/dashboard/live/"]');
    const shipments=[...row.querySelectorAll('a')].find(a=>/View Shipments/i.test(a.textContent||''));
    const analytics=[...row.querySelectorAll('a')].find(a=>/See Analytics/i.test(a.textContent||''));
    const openUrl=open?.getAttribute('href')||null;
    const id=openUrl?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i)?.[1]||null;
    const strongs=[...row.querySelectorAll('strong')].map(s=>(s.textContent||'').trim()).filter(Boolean);
    const date=strongs.find(x=>/^\d{1,2}\/\d{1,2}\/\d{4}$/.test(x))||null;
    const time=strongs.find(x=>/^\d{1,2}:\d{2}\s*(AM|PM)$/i.test(x))||null;
    return {live_id:id,title,date,time,kind,open_url:openUrl,shipments_url:shipments?.getAttribute('href')||null,analytics_url:analytics?.getAttribute('href')||null};
  }),kind).catch(()=>[]);
}
// wanted: ids we must reach before we can stop scrolling. The Past list is
// infinite-scroll, so a show only exists in the DOM once we have scrolled far
// enough back to it — stopping early does not return a short list, it silently
// makes those shows unreachable. Scrolling stops as soon as every wanted id is
// in hand, or the list stops growing, whichever comes first.
async function loadTabAll(page,kind,maxPasses=30,wanted=null){
  await resetScroll(page);
  const need=wanted&&wanted.size?new Set(wanted):null;
  const all=new Map(); let stable=0,prev=-1;
  for(let pass=0;pass<maxPasses;pass++){
    const rows=await extractRows(page,kind);
    for(const row of rows)if(row.live_id){all.set(row.live_id,row);if(need)need.delete(row.live_id);}
    if(need&&need.size===0)break;
    if(all.size===prev)stable++; else stable=0;
    prev=all.size;
    if(stable>=3)break;
    await page.evaluate(()=>{
      window.scrollTo(0,document.body.scrollHeight);
      const els=[...document.querySelectorAll('*')].filter(el=>el.scrollHeight>el.clientHeight+200);
      for(const el of els.slice(-5))el.scrollTop=el.scrollHeight;
    }).catch(()=>{});
    await page.waitForTimeout(1800);
    if(DEBUG&&pass%10===9)console.error(`[whatnot-prod] ${kind}: pass ${pass+1}, ${all.size} rows loaded${need?`, ${need.size} still to reach`:''}`);
  }
  if(DEBUG&&need&&need.size)console.error(`[whatnot-prod] ${kind}: gave up with ${need.size} requested show(s) never reached: ${[...need].join(', ')}`);
  return [...all.values()];
}
function extractMetrics(text){
  const lines=text.split('\n').map(x=>x.trim()).filter(Boolean);
  const labels=['Estimated Sales','Total Estimated Earnings','Completed Earnings','Orders','Average Order Value','AOV','Giveaway Spend','Giveaways','Buyers','First Time Buyers','Returning Buyers','Shares','Show Duration','Duration','Max Concurrent Viewers','Total Views','Average Order Rating'];
  const out={}; for(const label of labels){const i=lines.findIndex(x=>x.toLowerCase()===label.toLowerCase());if(i>=0)out[label]=lines[i+1]||null;} return out;
}
async function extractShipmentPage(page){
  await page.waitForSelector('tbody[data-testid="shipments-table-body"]',{timeout:12000}).catch(()=>{});
  const stats=await page.evaluate(()=>{
    const wanted=['Sales','Completed Earnings','Shipping Spend','Items sold','Total Delivered','Pending Delivery']; const out={}; const strongs=[...document.querySelectorAll('strong')];
    for(const label of wanted){const el=strongs.find(s=>(s.textContent||'').trim()===label);if(!el)continue;const card=el.closest('div.flex.flex-col.p-4.h-24')||el.parentElement?.parentElement;const vals=card?[...card.querySelectorAll('strong')].map(s=>(s.textContent||'').trim()).filter(Boolean):[];out[label]=vals.find(v=>v!==label)||null;}return out;
  }).catch(()=>({}));
  const all=[]; let pageNo=0;
  while(pageNo++<20){
    const rows=await page.locator('tbody[data-testid="shipments-table-body"] > tr[data-testid^="shipments-"][data-testid$="-row"]').evaluateAll(trs=>trs.map(tr=>{
      const td=[...tr.children].filter(el=>el.tagName==='TD'); const text=i=>(td[i]?.innerText||'').trim().replace(/\s+/g,' ');
      const recipient=td[1]?.querySelector('a[href*="/dashboard/inbox?participantId="]')?.textContent?.trim()||null;
      const carrier=[...td[9]?.querySelectorAll('strong')||[]].map(x=>(x.textContent||'').trim()).filter(Boolean)[0]||null;
      const track=td[9]?.querySelector('a[href*="TrackConfirmAction"]'); const href=track?.getAttribute('href')||null; const tracking=href?.match(/[?&]tLabels=([^&]+)/)?.[1]||track?.textContent?.trim()||null;
      return {shipment_key:tr.getAttribute('data-testid'),recipient,order_date:text(2)||null,items:text(3)||null,value:text(4)||null,weight:text(5)||null,dimensions:text(6)||null,requirements:text(7)||null,status:text(8)||null,carrier,tracking,tracking_url:href};
    })).catch(()=>[]);
    for(const row of rows)if(row.tracking&&!all.some(x=>x.tracking===row.tracking))all.push(row);
    const next=page.locator('button:has(svg[aria-label="Next page"])').first();
    if(!(await next.count().catch(()=>0))||await next.isDisabled().catch(()=>true))break;
    const before=rows[0]?.tracking||''; await next.click({timeout:5000}).catch(()=>null); await page.waitForTimeout(1800);
    const after=await page.locator('tbody[data-testid="shipments-table-body"] > tr').first().innerText().catch(()=> ''); if(after.includes(before)&&pageNo>1)break;
  }
  return {stats,rows:all};
}
// Navigate to a per-show page by the href its list row carried. Returns the
// page state on arrival, or null if the navigation did not land somewhere that
// looks like the requested URL — a redirect to the dashboard, a deleted show.
async function openDeepLink(page,href){
  let url; try{url=new URL(href,'https://www.whatnot.com').toString();}catch{return null;}
  const resp=await page.goto(url,{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null);
  if(!resp)return null;
  await page.waitForLoadState('networkidle',{timeout:8000}).catch(()=>{});
  await page.waitForTimeout(1200);
  await settleChallenge(page);

  const st=await state(page);
  if(st.challenged)return st;
  // Whatnot bounces an unrecognised or removed show back to a list page.
  const wantedPath=new URL(url).pathname;
  if(!page.url().includes(wantedPath)){if(DEBUG)console.error(`[whatnot-prod] deep link ${wantedPath} redirected to ${page.url()}`);return null;}
  return st;
}



// Which saved session to bootstrap from, following whatnot-scraper.cjs:
// whatnot-cookies.json is what a human imported, whatnot-live-cookies.json is
// written from a live context on every successful cookie test and is usually
// fresher, because Whatnot rotates the session as it is used.
function resolveCookiesFile(){
  if(process.env.WHATNOT_COOKIES_FILE)return process.env.WHATNOT_COOKIES_FILE;
  const bootstrap=path.join(__dirname,'../storage/whatnot-cookies.json');
  const live=path.join(__dirname,'../storage/whatnot-live-cookies.json');
  const mtime=f=>{try{return fs.statSync(f).mtimeMs;}catch{return 0;}};
  return mtime(live)>mtime(bootstrap)?live:bootstrap;
}

/**
 * Put a saved session into the profile when the profile has none of its own.
 *
 * This script never loaded cookies at all — it trusted the persistent profile
 * entirely. That works right up until the profile is new or gets rebuilt, at
 * which point there is no session, Whatnot serves a signed-out page, and the
 * only symptom is a Cloudflare challenge that looks like an IP problem.
 *
 * An established profile is left alone on purpose: its cookies are the ones
 * Whatnot has been refreshing, and overwriting them with an older export is how
 * a working session gets downgraded to a stale one.
 */
/**
 * Drop a Cloudflare clearance this browser did not earn.
 *
 * cf_clearance proves a particular browser on a particular IP passed a
 * challenge, and Cloudflare binds it to both. One that arrived in an imported
 * cookie file was earned somewhere else, and presenting a token that does not
 * match the connection is worse than presenting none — it is exactly what a
 * replayed token looks like, and it is answered with a challenge.
 *
 * whatnot-scraper.cjs does this on every run, logs "dropped an imported
 * cf_clearance", and is then served a plain 200. This script only did it on the
 * bootstrap path, which is skipped whenever the profile already has cookies — so
 * on a shared profile it kept offering the very token that earns the block.
 *
 * Cloudflare issues clearance for about an hour, so anything claiming days was
 * imported. The expiry is enough to tell them apart without guessing.
 */
async function dropForeignClearance(context){
  const cookies=await context.cookies('https://www.whatnot.com').catch(()=>[]);

  const foreign=cookies.find(c=>c.name==='cf_clearance'
    && c.expires>0
    && (c.expires*1000-Date.now())>24*60*60*1000);

  if(!foreign&&process.env.WHATNOT_DROP_CLEARANCE!=='1')return;

  for(const name of ['cf_clearance','__cf_bm','__cfwaitingroom','cf_chl_2','cf_chl_prog','cf_chl_rc_i','cf_chl_rc_ni','cf_chl_rc_m']){
    await context.clearCookies({name}).catch(()=>{});
  }

  console.error('[whatnot-prod] dropped an imported cf_clearance — its expiry says it was earned on another machine');
}

async function bootstrapCookies(context){
  const file=resolveCookiesFile();
  if(!fs.existsSync(file))return;

  const existing=await context.cookies('https://www.whatnot.com').catch(()=>[]);
  if(existing.length>0){
    if(DEBUG)console.error(`[whatnot-prod] profile already has ${existing.length} whatnot.com cookies; leaving them alone`);
    return;
  }

  try{
    const sameSite={no_restriction:'None',strict:'Strict',lax:'Lax'};
    const cookies=JSON.parse(fs.readFileSync(file,'utf8'))
      .filter(c=>typeof c.name==='string'&&typeof c.value==='string')
      .map(c=>({name:c.name,value:c.value,domain:c.domain||'.whatnot.com',path:c.path||'/',expires:c.expirationDate??c.expires??-1,httpOnly:Boolean(c.httpOnly),secure:Boolean(c.secure),sameSite:sameSite[(c.sameSite||'').toLowerCase()]||'Lax'}));

    if(cookies.length===0)return;

    await context.addCookies(cookies);

    // cf_clearance proves a particular browser on a particular IP passed a
    // challenge, and Cloudflare binds it to both. One that arrived in an
    // imported file was earned somewhere else, and presenting a token that does
    // not match the connection is worse than presenting none — it is what a
    // replayed token looks like. This browser earns its own.
    for(const edge of ['cf_clearance','__cf_bm','__cfruid'])await context.clearCookies({name:edge}).catch(()=>{});

    if(DEBUG)console.error(`[whatnot-prod] bootstrapped ${cookies.length} session cookies from ${file} (profile had none)`);
  }catch(e){
    if(DEBUG)console.error(`[whatnot-prod] cookie file found but failed to load: ${e.message}`);
  }
}

(async()=>{
  const userDataDir=process.env.WHATNOT_USER_DATA_DIR||path.join(__dirname,'../storage/whatnot-browser-profile'); fs.mkdirSync(userDataDir,{recursive:true});

  // Cloudflare judges this server's datacenter address far harder than a
  // residential one, and no amount of browser tuning changes an IP. The PHP
  // caller has always put WHATNOT_PROXY in this process's environment and this
  // script has always ignored it, so every run here went out on the bare server
  // address while whatnot-scraper.cjs — the one that works — went through the
  // proxy. That is the difference between a dashboard and a challenge.
  const proxy=process.env.WHATNOT_PROXY||'';
  if(proxy&&DEBUG)console.error(`[whatnot-prod] routing browser traffic through proxy: ${proxy}`);

  // Launched the way whatnot-scraper.cjs launches, because that one reaches the
  // Seller Hub from this machine every day and this one was being challenged on
  // the same address, profile and cookies. launchPersistentContext() adds
  // --remote-debugging-pipe and roughly thirty automation flags; the shared
  // launcher spawns Chromium with a small deliberate set and attaches over CDP.
  const context=await launchWithProfileRecovery(userDataDir,{
    args:['--no-sandbox','--no-zygote','--disable-dev-shm-usage','--disable-crash-reporter','--crash-dumps-dir=/tmp','--disable-gpu'],
    userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:{width:1280,height:900},
    locale:'en-US',
    extraHTTPHeaders:{'sec-ch-ua':'"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"','sec-ch-ua-mobile':'?0','sec-ch-ua-platform':'"Windows"','Accept-Language':'en-US,en;q=0.9'},
  },{chromium,chromiumPath:findChromium(),info:(...a)=>{if(DEBUG)console.error('[whatnot-prod]',...a);}});
  // The full fingerprint, not four fields of it. Masking webdriver, languages,
  // navigator.platform and window.chrome while leaving userAgentData reporting
  // Linux and WebGL reporting SwiftShader described a machine that cannot exist,
  // and that inconsistency is what Cloudflare's challenge JS reads before
  // declining to issue clearance — so the interstitial reloaded until the wait
  // ran out. Shared with whatnot-scraper, which reaches the hub from here.
  await applyStealth(context);
  const lsFile=path.join(__dirname,'../storage/whatnot-localstorage.json');if(fs.existsSync(lsFile)){try{const saved=JSON.parse(fs.readFileSync(lsFile,'utf8'));await context.addInitScript(entries=>{if(/whatnot\.com$/i.test(location.hostname)){try{for(const[k,v]of Object.entries(entries||{}))localStorage.setItem(k,v);}catch{}}},saved);}catch{}}
  await bootstrapCookies(context);
  await dropForeignClearance(context);
  const page=await context.newPage(); const out={current:[],upcoming:[],past:[],enriched:[],stages:{}};
  try{
    // Land on the public site before the Seller Hub, the way whatnot-scraper
    // does. Going straight to /dashboard/home means the first request of the
    // session is an authenticated one, which is the request Cloudflare is most
    // interested in.
    await page.goto('https://www.whatnot.com/',{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null);
    await page.waitForTimeout(1500);
    await settleChallenge(page);

    const resp=await page.goto('https://www.whatnot.com/dashboard/home',{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null);await page.waitForLoadState('networkidle',{timeout:7000}).catch(()=>{});await page.waitForTimeout(2200);await settleChallenge(page);out.stages.home={status:resp?resp.status():null,...await state(page)};if(out.stages.home.challenged){await reportBlockingPage(page,'challenge-home');throw fail(EXIT.CHALLENGE,'Seller Hub home challenged — Cloudflare served a check instead of the dashboard'+(proxy?' (via proxy '+proxy+')':'')+'. whatnot-scraper.cjs reaching the same site from this machine would mean the address is fine and the interstitial did not clear within the wait, so this is a real block rather than a page still loading.');}
    if(!await clickShows(page))throw fail(EXIT.SELECTORS,'Could not reach Shows'); out.stages.shows=await state(page);

    // A backfill asks for named shows that may sit hundreds of rows back. The
    // flat 30-pass cap reached only the newest few hundred and dropped the rest
    // before enrichment even began, so an old show could never be filled in no
    // matter how many times the job ran.
    const wantedPast=new Set(ENRICH_IDS);
    const pastPasses=wantedPast.size?Math.max(30,Number(process.env.WHATNOT_PAST_PASSES||400)):30;

    for(const spec of [
      {name:'current',key:'current',passes:8},
      {name:'upcoming',key:'upcoming',passes:16},
      {name:'past',key:'past',passes:pastPasses,wanted:wantedPast},
    ]){
      const selected=await selectTab(page,spec.name);
      if(!selected.ok)throw fail(EXIT.SELECTORS,`Could not select ${spec.name} tab; states=${JSON.stringify(selected.states)}`);
      if(selected.missing){out[spec.key]=[];if(DEBUG)console.error(`[whatnot-prod] ${spec.key}: tab absent, treating as 0 shows; states=${JSON.stringify(selected.states)}`);continue;}
      out[spec.key]=await loadTabAll(page,spec.key,spec.passes,spec.wanted);
      if(DEBUG)console.error(`[whatnot-prod] ${spec.key}: ${out[spec.key].length} unique show rows; states=${JSON.stringify(await tabStates(page))}`);
    }
    await shot(page,'past');
    if(out.past.length===0)throw fail(EXIT.SELECTORS,'Past tab produced no show rows');

    let targets=[];
    if(ENRICH_IDS.length){
      for(const id of ENRICH_IDS){
        const row=out.past.find(r=>r.live_id===id);
        if(!row){if(DEBUG)console.error(`[whatnot-prod] enrich ${id}: not found in loaded Past rows`);continue;}
        if(!row.analytics_url&&!row.shipments_url){if(DEBUG)console.error(`[whatnot-prod] enrich ${id}: row has neither Analytics nor Shipments action`);continue;}
        targets.push(row);
        if(DEBUG)console.error(`[whatnot-prod] enrich ${id}: analytics=${row.analytics_url?'yes':'no'} shipments=${row.shipments_url?'yes':'no'}`);
      }
    } else {
      targets=out.past.filter(r=>r.analytics_url||r.shipments_url).slice(0,MAX_ENRICH);
    }
    targets=targets.slice(0,MAX_ENRICH);
    if(DEBUG)console.error(`[whatnot-prod] enrichment targets: ${targets.length}/${ENRICH_IDS.length||MAX_ENRICH}`);

    // Each row carries the href of its own Analytics and Shipments page, so go
    // straight there. Clicking the links instead meant returning to the Past
    // list and scrolling it from the top again — twice per show — which put the
    // cost of a show in proportion to how old it was, and pushed whole batches
    // of older shows past the caller's process timeout.
    for(const seed of targets){
      const item={live_id:seed.live_id,analytics:null,shipments:null,availability:{analytics:!!seed.analytics_url,shipments:!!seed.shipments_url}};
      let challenged=false;

      if(seed.shipments_url){
        const st=await openDeepLink(page,seed.shipments_url);
        if(st&&st.challenged)challenged=true;
        else if(st)item.shipments=await extractShipmentPage(page);
      }

      if(seed.analytics_url&&!challenged){
        const st=await openDeepLink(page,seed.analytics_url);
        if(st&&st.challenged)challenged=true;
        else if(st)item.analytics={url:st.url,metrics:extractMetrics(await bodyText(page))};
      }

      if(DEBUG)console.error(`[whatnot-prod] enrich ${seed.live_id}: analytics=${item.analytics?'ok':(item.availability.analytics?'failed':'n/a')} shipments=${item.shipments?'ok':(item.availability.shipments?'failed':'n/a')}${challenged?' CHALLENGED':''}`);
      out.enriched.push(item);

      // A challenge mid-run means the session is being questioned. Carrying on
      // collects nothing and deepens the block; stop and report what is done.
      if(challenged){out.stages.enrich_challenged={live_id:seed.live_id,...await state(page)};break;}
    }
    process.stdout.write(JSON.stringify(out)+'\n');
  } finally {await context.close().catch(()=>{});}
})().catch(err=>{console.error(err?.stack||String(err));process.exit(err?.exitCode||EXIT.GENERAL);});
