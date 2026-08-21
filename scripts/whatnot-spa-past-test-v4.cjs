'use strict';

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function loadPlaywright() {
  try {
    const root = execSync('npm root -g', { encoding: 'utf8', stdio: ['pipe','pipe','pipe'] }).trim();
    return require(root + '/playwright');
  } catch {}
  for (const p of ['/opt/node22/lib/node_modules/playwright','/usr/lib/node_modules/playwright','/usr/local/lib/node_modules/playwright']) {
    try { return require(p); } catch {}
  }
  throw new Error('Playwright not found');
}

const { chromium } = loadPlaywright();
const LIVE_ID = (process.argv[2] || '183498e1-fc7d-436b-a4a0-c042efba09b8').trim();
const DEBUG = process.env.WHATNOT_DEBUG === '1';

function findChromium() {
  const explicit = process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH;
  if (explicit && fs.existsSync(explicit)) return explicit;
  const marker = path.join(__dirname, '../storage/chromium-path.txt');
  try { const p = fs.readFileSync(marker,'utf8').trim(); if (p && fs.existsSync(p)) return p; } catch {}
  try { const p = chromium.executablePath(); if (p && fs.existsSync(p)) return p; } catch {}
  for (const p of ['/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome']) if (fs.existsSync(p)) return p;
}

function challenged(text, title='') {
  return /performing security verification|just a moment|verifying you are human|checking your browser|cf-chl|cf_chl|challenge-platform|ray id/i.test(`${title}\n${text}`);
}

async function bodyText(page) { return page.locator('body').innerText().catch(() => ''); }
async function shot(page, name) { if (DEBUG) await page.screenshot({ path:`/tmp/whatnot-spa-v4-${name}.png`, fullPage:false }).catch(() => {}); }
async function state(page) {
  const text = await bodyText(page); const title = await page.title().catch(()=>'');
  return { url:page.url(), title, challenged:challenged(text,title), body:text.substring(0,4000) };
}

async function clickShows(page) {
  if (/\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) return true;
  const loc = page.locator('a[href="/dashboard/lives"], a[href*="/dashboard/lives"]').first();
  if (!(await loc.count().catch(()=>0))) return false;
  await loc.click({timeout:8000}).catch(()=>null);
  await page.waitForURL(/\/dashboard\/lives(?:[/?#]|$)/,{timeout:10000}).catch(()=>{});
  await page.waitForTimeout(2500);
  return /\/dashboard\/lives(?:[/?#]|$)/.test(page.url());
}

async function clickPast(page) {
  const past = page.locator('ul[role="tablist"] button[role="tab"]', { hasText:/^Past$/ }).first();
  if (!(await past.count().catch(()=>0))) return false;
  if ((await past.getAttribute('aria-selected').catch(()=>null)) !== 'true') await past.click({timeout:8000}).catch(()=>null);
  await page.waitForFunction(() => {
    const p=[...document.querySelectorAll('ul[role="tablist"] button[role="tab"]')].find(x=>(x.textContent||'').trim()==='Past');
    return p && p.getAttribute('aria-selected')==='true';
  },{timeout:10000}).catch(()=>{});
  await page.waitForTimeout(2500);
  return true;
}

async function extractRows(page) {
  return page.locator('[data-testid="show-list-item"]').evaluateAll(rows => rows.map(row => {
    const title = row.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim() || null;
    const open = row.querySelector('a[href^="/dashboard/live/"]');
    const shipments = [...row.querySelectorAll('a')].find(a => /View Shipments/i.test(a.textContent||''));
    const analytics = [...row.querySelectorAll('a')].find(a => /See Analytics/i.test(a.textContent||''));
    const openUrl = open?.getAttribute('href') || null;
    const id = openUrl?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i)?.[1] || null;
    const parts = [...row.querySelectorAll('strong')].map(s=>(s.textContent||'').trim()).filter(Boolean);
    return { live_id:id, title, date_time_parts:parts, open_url:openUrl, shipments_url:shipments?.getAttribute('href')||null, analytics_url:analytics?.getAttribute('href')||null };
  })).catch(()=>[]);
}

async function loadUntilTarget(page) {
  let all = new Map();
  let stable = 0;
  let previous = 0;
  for (let pass=0; pass<24; pass++) {
    const rows = await extractRows(page);
    for (const row of rows) if (row.live_id) all.set(row.live_id,row);
    if (all.has(LIVE_ID)) return { rows:[...all.values()], target:all.get(LIVE_ID), passes:pass+1 };

    if (all.size === previous) stable++; else stable = 0;
    previous = all.size;
    if (stable >= 3) break;

    await page.evaluate(() => {
      window.scrollTo(0, document.body.scrollHeight);
      const scrollers=[...document.querySelectorAll('*')].filter(el => el.scrollHeight > el.clientHeight + 200);
      for (const el of scrollers.slice(-5)) el.scrollTop = el.scrollHeight;
    }).catch(()=>{});
    await page.waitForTimeout(1800);
  }
  return { rows:[...all.values()], target:null, passes:null };
}

async function extractAnalytics(page) {
  const text = await bodyText(page);
  const lines = text.split('\n').map(x=>x.trim()).filter(Boolean);
  const labels=['Estimated Sales','Total Estimated Earnings','Completed Earnings','Orders','Average Order Value','AOV','Giveaway Spend','Giveaways','Buyers','First Time Buyers','Returning Buyers','Shares','Show Duration','Duration','Max Concurrent Viewers','Total Views','Average Order Rating'];
  const metrics={};
  for (const label of labels) { const i=lines.findIndex(x=>x.toLowerCase()===label.toLowerCase()); if (i>=0) metrics[label]=lines[i+1]||null; }
  return { ...(await state(page)), metrics };
}

(async()=>{
  const userDataDir = process.env.WHATNOT_USER_DATA_DIR || path.join(__dirname,'../storage/whatnot-browser-profile');
  fs.mkdirSync(userDataDir,{recursive:true});
  const context = await chromium.launchPersistentContext(userDataDir,{
    headless:process.env.WHATNOT_HEADLESS!=='false', executablePath:findChromium(),
    args:['--no-sandbox','--no-zygote','--disable-dev-shm-usage','--disable-crash-reporter','--crash-dumps-dir=/tmp','--disable-gpu'],
    userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
    viewport:{width:1280,height:900}, locale:'en-US', timezoneId:'America/Chicago',
    extraHTTPHeaders:{'sec-ch-ua':'"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"','sec-ch-ua-mobile':'?0','sec-ch-ua-platform':'"Windows"','Accept-Language':'en-US,en;q=0.9'},
  });
  await context.addInitScript(()=>{
    try{Object.defineProperty(navigator,'webdriver',{get:()=>undefined});}catch{}
    try{Object.defineProperty(navigator,'languages',{get:()=>['en-US','en']});}catch{}
    try{Object.defineProperty(navigator,'platform',{get:()=> 'Win32'});}catch{}
    try{if(!window.chrome)window.chrome={runtime:{}};}catch{}
  });

  const lsFile=path.join(__dirname,'../storage/whatnot-localstorage.json');
  if(fs.existsSync(lsFile)){
    try{const saved=JSON.parse(fs.readFileSync(lsFile,'utf8')); await context.addInitScript(entries=>{if(/whatnot\.com$/i.test(location.hostname)){try{for(const[k,v]of Object.entries(entries||{}))localStorage.setItem(k,v);}catch{}}},saved);}catch{}
  }

  const page=await context.newPage();
  const ops=[];
  page.on('request',req=>{const m=req.url().match(/operationName=([^&]+)/); if(m)ops.push(decodeURIComponent(m[1]));});
  const out={live_id:LIVE_ID,stages:{},past_rows:[],target:null,operations:[]};
  try{
    const resp=await page.goto('https://www.whatnot.com/dashboard/home',{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null);
    await page.waitForLoadState('networkidle',{timeout:7000}).catch(()=>{}); await page.waitForTimeout(2500);
    out.stages.home={status:resp?resp.status():null,...await state(page)}; await shot(page,'01-home');
    if(out.stages.home.challenged) throw new Error('home challenged');

    out.stages.shows_click={clicked:await clickShows(page)}; out.stages.shows=await state(page); await shot(page,'02-shows');
    if(!out.stages.shows_click.clicked) throw new Error('could not reach shows');

    out.stages.past_click={clicked:await clickPast(page)}; out.stages.past=await state(page);
    const loaded=await loadUntilTarget(page); out.past_rows=loaded.rows; out.target=loaded.target; out.stages.past.loaded_count=loaded.rows.length; out.stages.past.scroll_passes=loaded.passes; await shot(page,'03-past');

    if(out.target?.analytics_url){
      const href=out.target.analytics_url.replace(/&amp;/g,'&');
      const row=page.locator('[data-testid="show-list-item"]').filter({has:page.locator(`a[href="${out.target.open_url}"]`)}).first();
      const link=row.locator('a',{hasText:/^See Analytics$/}).first();
      if(await link.count().catch(()=>0)){
        await link.click({timeout:8000}).catch(()=>null); await page.waitForTimeout(5000);
        out.stages.analytics=await extractAnalytics(page); out.stages.analytics.expected_href=href; await shot(page,'04-analytics');
      }
    }

    if(out.target?.shipments_url){
      await page.goBack({waitUntil:'domcontentloaded',timeout:15000}).catch(()=>null); await page.waitForTimeout(2500);
      if(!/\/dashboard\/lives(?:[/?#]|$)/.test(page.url())) await clickShows(page);
      await clickPast(page); await loadUntilTarget(page);
      const row=page.locator('[data-testid="show-list-item"]').filter({has:page.locator(`a[href="${out.target.open_url}"]`)}).first();
      const link=row.locator('a',{hasText:/^View Shipments$/}).first();
      if(await link.count().catch(()=>0)){
        await link.click({timeout:8000}).catch(()=>null); await page.waitForTimeout(5000);
        out.stages.shipments={...await state(page),tbody_rows:await page.locator('tbody tr').count().catch(()=>0),expected_href:out.target.shipments_url}; await shot(page,'05-shipments');
      }
    }

    out.operations=[...new Set(ops)]; process.stdout.write(JSON.stringify(out,null,2)+'\n');
  } finally { await context.close().catch(()=>{}); }
})().catch(err=>{console.error(err?.stack||String(err));process.exit(1);});
