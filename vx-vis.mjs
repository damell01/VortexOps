import { chromium } from '@playwright/test';
const B='http://127.0.0.1:8125'; const SS='/tmp/claude-0/-home-user-VortexOps/be66a6b4-0a4e-5d57-95bd-5cef4f0d78fb/scratchpad';
const errs=[];
const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await b.newPage({ viewport:{width:1440,height:1000}, deviceScaleFactor:2 });
p.on('pageerror',e=>errs.push(e.message));
await p.goto(B+'/admin/login',{waitUntil:'networkidle'});
await p.fill('input[type=email]','admin@vortexbreaks.com');
await p.fill('input[type=password]','password');
await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/admin/inventory-items',{waitUntil:'networkidle'}); await p.waitForTimeout(1500);
await p.evaluate(`document.querySelectorAll('[class*=feedback],[id*=feedback],.phpdebugbar,[class*=phpdebugbar]').forEach(e=>e.style.display='none')`);
// open a row overflow menu so dropdown items are visible
const dots = p.locator('.fi-ta-actions .fi-dropdown-trigger button').first();
if (await dots.count() && await dots.isVisible()) { await dots.click({timeout:5000}).catch(()=>{}); await p.waitForTimeout(700); }
await p.screenshot({path:`${SS}/btn-after.png`, clip:{x:440,y:280,width:1000,height:560}});
console.log('errors:', errs.length?errs:'none');
await b.close();
