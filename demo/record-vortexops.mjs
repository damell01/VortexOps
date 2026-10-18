import { chromium } from '@playwright/test';
import fs from 'node:fs';
fs.mkdirSync('demo/output',{recursive:true});
const browser=await chromium.launch({headless:true,args:['--no-sandbox']});
const context=await browser.newContext({viewport:{width:1280,height:720},deviceScaleFactor:1,colorScheme:'light'});
const page=await context.newPage();
let index=0;
async function shot(label) {
  await page.waitForTimeout(2000);
  await page.screenshot({path:'demo/output/'+String(index++).padStart(2,'0')+'-'+label+'.png',fullPage:true,animations:'disabled'});
}
try {
  await page.goto('http://127.0.0.1:8088/admin/login',{waitUntil:'domcontentloaded',timeout:60000});
  await shot('real-vortexops-login');
  const email=page.locator('input[type=email]').first();
  const password=page.locator('input[type=password]').first();
  if(await email.count() && await password.count()) {
    await email.fill(process.env.DEMO_RECORD_EMAIL);
    await password.fill(process.env.DEMO_RECORD_PASSWORD);
    await page.locator('button[type=submit]').first().click();
    await page.waitForTimeout(3500);
    await shot('real-vortexops-after-login');
  }
  if(!page.url().includes('/login')) {
    await page.goto('http://127.0.0.1:8088/admin',{waitUntil:'domcontentloaded'});
    await shot('real-vortexops-dashboard');
    for(const target of ['Shows','Inventory','Reports']){
      const link=page.getByRole('link',{name:new RegExp(target,'i')}).first();
      if(await link.count()){
        try { await link.click({timeout:4000}); await shot('real-'+target.toLowerCase()); }
        catch(err){ console.log('Navigation unavailable: '+target+' '+err.message.slice(0,100)); }
      }
      await page.goto('http://127.0.0.1:8088/admin',{waitUntil:'domcontentloaded'});
    }
  }
} catch(err) {
  console.error('Browser capture issue:',err.message);
  await page.screenshot({path:'demo/output/error-real-app.png'}).catch(()=>{});
} finally {
  await context.close();
  await browser.close();
}
console.log('Captured '+index+' real Laravel browser frames.');
