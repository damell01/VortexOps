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
async function bodyText(page){ return page.locator('body').innerText().catch(()=> ''); }
async function state(page){ const text=await bodyText(page),title=await page.title().catch(()=> ''); return {url:page.url(),title,challenged:isChallenge(text,title)}; }
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
  const st=await state(page);
  if(st.challenged)return st;
  // Whatnot bounces an unrecognised or removed show back to a list page.
  const wantedPath=new URL(url).pathname;
  if(!page.url().includes(wantedPath)){if(DEBUG)console.error(`[whatnot-prod] deep link ${wantedPath} redirected to ${page.url()}`);return null;}
  return st;
}


// Chrome refuses to open a profile that still carries another instance's lock,
// and a run killed mid-flight (Ctrl+C, OOM, kill) leaves exactly that behind.
// Every later run then dies at launch on "Failed to create a ProcessSingleton
// for your profile directory" — including unattended scheduled ones, for ever,
// until a human clears the files by hand.
//
// SingletonLock is a symlink whose target encodes hostname-pid. If that PID is
// gone the lock is a leftover and clearing it is safe; if it is alive, leave it
// and let Chrome refuse, because two instances on one profile corrupt it.
function clearStaleProfileLock(dir){
  for(const name of ['SingletonLock','SingletonSocket','SingletonCookie']){
    const file=path.join(dir,name);
    let target; try{target=fs.readlinkSync(file);}catch{continue;}
    const pid=Number(/-(\d+)$/.exec(target)?.[1]);
    if(pid&&fs.existsSync(`/proc/${pid}`)){
      if(DEBUG)console.error(`[whatnot-prod] profile lock held by live PID ${pid}; leaving ${name} alone`);
      return false;
    }
    try{fs.unlinkSync(file);if(DEBUG)console.error(`[whatnot-prod] cleared stale ${name} (recorded PID ${pid||'unknown'} is gone)`);}catch{}
  }
  return true;
}

(async()=>{
  const userDataDir=process.env.WHATNOT_USER_DATA_DIR||path.join(__dirname,'../storage/whatnot-browser-profile'); fs.mkdirSync(userDataDir,{recursive:true});
  clearStaleProfileLock(userDataDir);
  const context=await chromium.launchPersistentContext(userDataDir,{headless:process.env.WHATNOT_HEADLESS!=='false',executablePath:findChromium(),args:['--no-sandbox','--no-zygote','--disable-dev-shm-usage','--disable-crash-reporter','--crash-dumps-dir=/tmp','--disable-gpu'],userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',viewport:{width:1280,height:900},locale:'en-US',timezoneId:'America/Chicago',extraHTTPHeaders:{'sec-ch-ua':'"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"','sec-ch-ua-mobile':'?0','sec-ch-ua-platform':'"Windows"','Accept-Language':'en-US,en;q=0.9'}});
  await context.addInitScript(()=>{try{Object.defineProperty(navigator,'webdriver',{get:()=>undefined});}catch{}try{Object.defineProperty(navigator,'languages',{get:()=>['en-US','en']});}catch{}try{Object.defineProperty(navigator,'platform',{get:()=> 'Win32'});}catch{}try{if(!window.chrome)window.chrome={runtime:{}};}catch{}});
  const lsFile=path.join(__dirname,'../storage/whatnot-localstorage.json');if(fs.existsSync(lsFile)){try{const saved=JSON.parse(fs.readFileSync(lsFile,'utf8'));await context.addInitScript(entries=>{if(/whatnot\.com$/i.test(location.hostname)){try{for(const[k,v]of Object.entries(entries||{}))localStorage.setItem(k,v);}catch{}}},saved);}catch{}}
  const page=await context.newPage(); const out={current:[],upcoming:[],past:[],enriched:[],stages:{}};
  try{
    const resp=await page.goto('https://www.whatnot.com/dashboard/home',{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null);await page.waitForLoadState('networkidle',{timeout:7000}).catch(()=>{});await page.waitForTimeout(2200);out.stages.home={status:resp?resp.status():null,...await state(page)};if(out.stages.home.challenged)throw new Error('Seller Hub home challenged');
    if(!await clickShows(page))throw new Error('Could not reach Shows'); out.stages.shows=await state(page);

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
      if(!selected.ok)throw new Error(`Could not select ${spec.name} tab; states=${JSON.stringify(selected.states)}`);
      if(selected.missing){out[spec.key]=[];if(DEBUG)console.error(`[whatnot-prod] ${spec.key}: tab absent, treating as 0 shows; states=${JSON.stringify(selected.states)}`);continue;}
      out[spec.key]=await loadTabAll(page,spec.key,spec.passes,spec.wanted);
      if(DEBUG)console.error(`[whatnot-prod] ${spec.key}: ${out[spec.key].length} unique show rows; states=${JSON.stringify(await tabStates(page))}`);
    }
    await shot(page,'past');
    if(out.past.length===0)throw new Error('Past tab produced no show rows');

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
})().catch(err=>{console.error(err?.stack||String(err));process.exit(1);});
