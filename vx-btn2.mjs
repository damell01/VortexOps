import { chromium } from '@playwright/test';
const B='http://127.0.0.1:8123';
const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await b.newPage({ viewport:{width:1440,height:1000} });
await p.goto(B+'/admin/login',{waitUntil:'networkidle'});
await p.fill('input[type=email]','admin@vortexbreaks.com');
await p.fill('input[type=password]','password');
await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/admin/inventory-items',{waitUntil:'networkidle'}); await p.waitForTimeout(1500);
console.log(JSON.stringify(await p.evaluate(()=>{
  const probe = (sel) => { const e=document.querySelector(sel); if(!e) return null;
    const s=getComputedStyle(e); const r=e.getBoundingClientRect();
    return {minH:s.minHeight, bg:s.backgroundColor, border:s.borderTopWidth, pad:s.padding, h:Math.round(r.height)}; };
  return {
    iconBtn: probe('.fi-ta-actions .fi-icon-btn'),
    dropdownItem: probe('.fi-dropdown-list-item'),
    realBtn: probe('.fi-btn'),
  };
}),null,1));
await b.close();
