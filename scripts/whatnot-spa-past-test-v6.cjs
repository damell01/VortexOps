'use strict';

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

function loadPlaywright() {
  try { const root = execSync('npm root -g',{encoding:'utf8'}).trim(); return require(root + '/playwright'); } catch {}
  for (const p of ['/opt/node22/lib/node_modules/playwright','/usr/lib/node_modules/playwright','/usr/local/lib/node_modules/playwright']) {
    try { return require(p); } catch {}
  }
  throw new Error('Playwright not found');
}
const { chromium } = loadPlaywright();
const LIVE_ID = (process.argv[2] || '').trim();
const DEBUG = process.env.WHATNOT_DEBUG === '1';

function findChromium(){
  const explicit=process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH; if(explicit&&fs.existsSync(explicit))return explicit;
  const marker=path.join(__dirname,'../storage/chromium-path.txt'); try{const p=fs.readFileSync(marker,'utf8').trim();if(p&&fs.existsSync(p))return p;}catch{}
  try{const p=chromium.executablePath();if(p&&fs.existsSync(p))return p;}catch{}
  for(const p of ['/usr/bin/chromium','/usr/bin/chromium-browser','/usr/bin/google-chrome'])if(fs.existsSync(p))return p;
}
function challenged(text,title=''){return /performing security verification|just a moment|verifying you are human|checking your browser|cf-chl|cf_chl|challenge-platform|ray id/i.test(`${title}\n${text}`);}
async function bodyText(page){return page.locator('body').innerText().catch(()=> '');}
async function shot(page,name){if(DEBUG)await page.screenshot({path:`/tmp/whatnot-spa-v6-${name}.png`,fullPage:false}).catch(()=>{});}
async function state(page){const text=await bodyText(page),title=await page.title().catch(()=> '');return{url:page.url(),title,challenged:challenged(text,title),body:text.substring(0,5000)};}

async function clickShows(page){
  if(/\/dashboard\/lives(?:[/?#]|$)/.test(page.url()))return true;
  const loc=page.locator('a[href="/dashboard/lives"],a[href*="/dashboard/lives"]').first();
  if(!(await loc.count().catch(()=>0)))return false;
  await loc.click({timeout:8000}).catch(()=>null); await page.waitForURL(/\/dashboard\/lives(?:[/?#]|$)/,{timeout:10000}).catch(()=>{}); await page.waitForTimeout(2500);
  return /\/dashboard\/lives(?:[/?#]|$)/.test(page.url());
}
async function clickPast(page){
  const past=page.locator('ul[role="tablist"] button[role="tab"]',{hasText:/^Past$/}).first();
  if(!(await past.count().catch(()=>0)))return false;
  if((await past.getAttribute('aria-selected').catch(()=>null))!=='true')await past.click({timeout:8000}).catch(()=>null);
  await page.waitForFunction(()=>{const p=[...document.querySelectorAll('ul[role="tablist"] button[role="tab"]')].find(x=>(x.textContent||'').trim()==='Past');return p&&p.getAttribute('aria-selected')==='true';},{timeout:10000}).catch(()=>{});
  await page.waitForTimeout(2500); return true;
}
async function extractRows(page){
  return page.locator('[data-testid="show-list-item"]').evaluateAll(rows=>rows.map(row=>{
    const title=row.querySelector('[data-testid="show-list-item-title"]')?.textContent?.trim()||null;
    const open=row.querySelector('a[href^="/dashboard/live/"]');
    const shipments=[...row.querySelectorAll('a')].find(a=>/View Shipments/i.test(a.textContent||''));
    const analytics=[...row.querySelectorAll('a')].find(a=>/See Analytics/i.test(a.textContent||''));
    const openUrl=open?.getAttribute('href')||null;
    const id=openUrl?.match(/\/dashboard\/live\/([0-9a-f-]{36})/i)?.[1]||null;
    const parts=[...row.querySelectorAll('strong')].map(s=>(s.textContent||'').trim()).filter(Boolean);
    return{live_id:id,title,date_time_parts:parts,open_url:openUrl,shipments_url:shipments?.getAttribute('href')||null,analytics_url:analytics?.getAttribute('href')||null};
  })).catch(()=>[]);
}
async function loadPastRows(page,desiredLiveId=LIVE_ID){
  const all=new Map(); let stable=0,previous=0,passes=0,requested=null;
  for(let pass=0;pass<30;pass++){
    passes=pass+1; const rows=await extractRows(page); for(const row of rows)if(row.live_id)all.set(row.live_id,row);
    if(desiredLiveId&&all.has(desiredLiveId)){requested=all.get(desiredLiveId);break;}
    stable=all.size===previous?stable+1:0; previous=all.size; if(stable>=3)break;
    await page.evaluate(()=>{window.scrollTo(0,document.body.scrollHeight);const scrollers=[...document.querySelectorAll('*')].filter(el=>el.scrollHeight>el.clientHeight+200);for(const el of scrollers.slice(-8))el.scrollTop=el.scrollHeight;}).catch(()=>{});
    await page.waitForTimeout(1800);
  }
  const rows=[...all.values()]; const fallback=rows.slice().reverse().find(r=>r.analytics_url&&r.shipments_url)||null;
  return{rows,passes,requested,target:requested||fallback,target_source:requested?'requested':(fallback?'fallback-current-channel':null)};
}
async function ensureTargetRendered(page,target){
  if(!target)return false; const selector=`a[href="${target.open_url}"]`; if(await page.locator(selector).count().catch(()=>0))return true;
  for(let i=0;i<12;i++){await page.evaluate(()=>window.scrollBy(0,-Math.max(window.innerHeight*2,1200))).catch(()=>{});await page.waitForTimeout(600);if(await page.locator(selector).count().catch(()=>0))return true;}
  return false;
}
async function extractAnalytics(page){
  const text=await bodyText(page),lines=text.split('\n').map(x=>x.trim()).filter(Boolean);
  const labels=['Estimated Sales','Total Estimated Earnings','Completed Earnings','Orders','Average Order Value','AOV','Giveaway Spend','Giveaways','Buyers','First Time Buyers','Returning Buyers','Shares','Show Duration','Duration','Max Concurrent Viewers','Total Views','Average Order Rating'];
  const metrics={}; for(const label of labels){const i=lines.findIndex(x=>x.toLowerCase()===label.toLowerCase());if(i>=0)metrics[label]=lines[i+1]||null;}
  return{...(await state(page)),metrics};
}
async function extractShipmentPage(page){
  await page.waitForSelector('tbody[data-testid="shipments-table-body"]',{timeout:12000}).catch(()=>{});
  const expandAll=page.locator('button:has(svg[aria-label="Expand All"])').first();
  if(await expandAll.count().catch(()=>0)){await expandAll.click({timeout:5000}).catch(()=>null);await page.waitForTimeout(1200);}

  const stats=await page.evaluate(()=>{
    const wanted=['Sales','Completed Earnings','Shipping Spend','Items sold','Total Delivered','Pending Delivery'];
    const out={};
    const strongs=[...document.querySelectorAll('strong')];
    for(const label of wanted){
      const labelEl=strongs.find(s=>(s.textContent||'').trim()===label);
      if(!labelEl)continue;
      const card=labelEl.closest('div.flex.flex-col.p-4.h-24')||labelEl.parentElement?.parentElement;
      const vals=card?[...card.querySelectorAll('strong')].map(s=>(s.textContent||'').trim()).filter(Boolean):[];
      out[label]=vals.find(v=>v!==label)||null;
    }
    return out;
  }).catch(()=>({}));

  const rows=await page.locator('tbody[data-testid="shipments-table-body"] > tr[data-testid^="shipments-"][data-testid$="-row"]').evaluateAll(trs=>trs.slice(0,25).map(tr=>{
    const td=[...tr.children].filter(el=>el.tagName==='TD');
    const text=i=>(td[i]?.innerText||'').trim().replace(/\s+/g,' ');
    const recipient=td[1]?.querySelector('a[href*="/dashboard/inbox?participantId="]')?.textContent?.trim()||null;
    const carrier=[...td[9]?.querySelectorAll('strong')||[]].map(x=>(x.textContent||'').trim()).filter(Boolean)[0]||null;
    const trackingLink=td[9]?.querySelector('a[href*="TrackConfirmAction"]');
    const trackingHref=trackingLink?.getAttribute('href')||null;
    const tracking=trackingHref?.match(/[?&]tLabels=([^&]+)/)?.[1]||trackingLink?.textContent?.trim()||null;
    return{
      shipment_testid:tr.getAttribute('data-testid'),
      recipient,
      order_date:text(2)||null,
      items:text(3)||null,
      value:text(4)||null,
      weight:text(5)||null,
      dimensions:text(6)||null,
      requirements:text(7)||null,
      status:text(8)||null,
      carrier,
      tracking,
      tracking_url:trackingHref,
    };
  })).catch(()=>[]);

  return{...(await state(page)),stats,row_count:await page.locator('tbody[data-testid="shipments-table-body"] > tr[data-testid^="shipments-"][data-testid$="-row"]').count().catch(()=>0),rows};
}

(async()=>{
  const userDataDir=process.env.WHATNOT_USER_DATA_DIR||path.join(__dirname,'../storage/whatnot-browser-profile'); fs.mkdirSync(userDataDir,{recursive:true});
  const context=await chromium.launchPersistentContext(userDataDir,{headless:process.env.WHATNOT_HEADLESS!=='false',executablePath:findChromium(),args:['--no-sandbox','--no-zygote','--disable-dev-shm-usage','--disable-crash-reporter','--crash-dumps-dir=/tmp','--disable-gpu'],userAgent:'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',viewport:{width:1280,height:900},locale:'en-US',timezoneId:'America/Chicago',extraHTTPHeaders:{'sec-ch-ua':'"Chromium";v="128", "Google Chrome";v="128", "Not-A.Brand";v="99"','sec-ch-ua-mobile':'?0','sec-ch-ua-platform':'"Windows"','Accept-Language':'en-US,en;q=0.9'}});
  await context.addInitScript(()=>{try{Object.defineProperty(navigator,'webdriver',{get:()=>undefined});}catch{}try{Object.defineProperty(navigator,'languages',{get:()=>['en-US','en']});}catch{}try{Object.defineProperty(navigator,'platform',{get:()=> 'Win32'});}catch{}try{if(!window.chrome)window.chrome={runtime:{}};}catch{}});
  const lsFile=path.join(__dirname,'../storage/whatnot-localstorage.json'); if(fs.existsSync(lsFile)){try{const saved=JSON.parse(fs.readFileSync(lsFile,'utf8'));await context.addInitScript(entries=>{if(/whatnot\.com$/i.test(location.hostname)){try{for(const[k,v]of Object.entries(entries||{}))localStorage.setItem(k,v);}catch{}}},saved);}catch{}}
  const page=await context.newPage(); const ops=[]; page.on('request',req=>{const m=req.url().match(/operationName=([^&]+)/);if(m)ops.push(decodeURIComponent(m[1]));});
  const out={requested_live_id:LIVE_ID||null,stages:{},past_rows:[],requested_target:null,target:null,target_source:null,operations:[]};
  try{
    const resp=await page.goto('https://www.whatnot.com/dashboard/home',{waitUntil:'domcontentloaded',timeout:30000}).catch(()=>null); await page.waitForLoadState('networkidle',{timeout:7000}).catch(()=>{}); await page.waitForTimeout(2500);
    out.stages.home={status:resp?resp.status():null,...await state(page)}; if(out.stages.home.challenged)throw new Error('home challenged');
    out.stages.shows_click={clicked:await clickShows(page)}; out.stages.shows=await state(page); if(!out.stages.shows_click.clicked)throw new Error('could not reach shows');
    out.stages.past_click={clicked:await clickPast(page)}; out.stages.past=await state(page);
    const loaded=await loadPastRows(page,LIVE_ID); out.past_rows=loaded.rows; out.requested_target=loaded.requested; out.target=loaded.target; out.target_source=loaded.target_source; out.stages.past.loaded_count=loaded.rows.length; out.stages.past.scroll_passes=loaded.passes; await shot(page,'03-past');

    if(out.target?.analytics_url&&await ensureTargetRendered(page,out.target)){
      const row=page.locator('[data-testid="show-list-item"]').filter({has:page.locator(`a[href="${out.target.open_url}"]`)}).first(); const link=row.locator('a',{hasText:/^See Analytics$/}).first();
      if(await link.count().catch(()=>0)){await link.click({timeout:8000}).catch(()=>null);await page.waitForTimeout(6000);out.stages.analytics=await extractAnalytics(page);out.stages.analytics.expected_href=out.target.analytics_url.replace(/&amp;/g,'&');await shot(page,'04-analytics');}
    }

    if(out.target?.shipments_url){
      await page.goBack({waitUntil:'domcontentloaded',timeout:15000}).catch(()=>null); await page.waitForTimeout(3000); if(!/\/dashboard\/lives(?:[/?#]|$)/.test(page.url()))await clickShows(page); await clickPast(page);
      const reload=await loadPastRows(page,out.target.live_id); const target=reload.requested||reload.rows.find(r=>r.live_id===out.target.live_id)||out.target; out.stages.shipments_reload={desired_live_id:out.target.live_id,loaded_count:reload.rows.length,scroll_passes:reload.passes,found:!!reload.requested};
      if(await ensureTargetRendered(page,target)){
        const row=page.locator('[data-testid="show-list-item"]').filter({has:page.locator(`a[href="${target.open_url}"]`)}).first(); const link=row.locator('a',{hasText:/^View Shipments$/}).first();
        if(await link.count().catch(()=>0)){await link.click({timeout:8000}).catch(()=>null);await page.waitForTimeout(7000);out.stages.shipments=await extractShipmentPage(page);out.stages.shipments.expected_href=target.shipments_url;await shot(page,'05-shipments');}
      }
    }
    out.operations=[...new Set(ops)]; process.stdout.write(JSON.stringify(out,null,2)+'\n');
  } finally {await context.close().catch(()=>{});}
})().catch(err=>{console.error(err?.stack||String(err));process.exit(1);});
