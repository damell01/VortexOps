import { chromium } from '@playwright/test';
const B='http://127.0.0.1:8123';
const b = await chromium.launch({ executablePath:'/opt/pw-browsers/chromium-1194/chrome-linux/chrome' });
const p = await b.newPage({ viewport:{width:1440,height:1000} });
await p.goto(B+'/admin/login',{waitUntil:'networkidle'});
await p.fill('input[type=email]','admin@vortexbreaks.com');
await p.fill('input[type=password]','password');
await Promise.all([p.waitForNavigation({waitUntil:'networkidle'}).catch(()=>{}),p.click('button[type=submit]')]);
await p.goto(B+'/admin/inventory-items',{waitUntil:'networkidle'}); await p.waitForTimeout(1400);
// Which type=button elements are NOT Filament buttons (i.e. collateral damage)?
console.log(JSON.stringify(await p.evaluate(()=>{
  const out={};
  document.querySelectorAll('button[type="button"]').forEach(el=>{
    const c=String(el.className);
    const kind = c.includes('fi-btn') ? 'fi-btn'
      : c.includes('fi-icon-btn') ? 'fi-icon-btn'
      : c.includes('fi-toggle') ? 'fi-toggle'
      : c.includes('fi-dropdown-trigger') ? 'dropdown-trigger'
      : c.includes('fi-ta-') ? 'table-control'
      : c.trim()==='' ? '(no class)' : 'other:'+c.split(' ')[0];
    out[kind]=(out[kind]||0)+1;
  });
  return out;
}),null,1));
await b.close();
