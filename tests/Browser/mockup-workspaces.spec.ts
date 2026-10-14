import { test, expect } from '@playwright/test';
import fs from 'node:fs';
async function login(page, email: string) {
 const state='storage/mockup-auth-'+email.replace(/[^a-z0-9]/gi,'_')+'.json';
 if(fs.existsSync(state)){
  await page.context().addCookies(JSON.parse(fs.readFileSync(state,'utf8')).cookies);
  await page.goto('/admin');
  return;
 }
 await page.goto('/admin/login');
 await page.locator('input[type=email]').fill(email);
 await page.locator('input[type=password]').fill('MockupTest123!');
 await page.getByRole('button', {name:'Sign in', exact:true}).click();
 await expect(page.locator('input[type=password]')).toHaveCount(0,{timeout:20000});
 await page.context().storageState({path:state});
}
for (const width of [390, 1280, 1920]) {
 test(`streamer home and day tabs at ${width}`, async ({page}) => {
  await page.setViewportSize({width,height:1000});
  await login(page,'mockup-streamer@example.test');
  await expect(page.locator('.vx-stream-home')).toBeVisible();
  await page.getByRole('tab',{name:/Yesterday/}).click();
  await expect(page.locator('.vx-home-show:visible')).toHaveCount(1);
  await expect(page.locator('.vx-home-show:visible')).toContainText('1 items logged');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth <= innerWidth+2)).toBe(true);
  await page.screenshot({path:`tests/Browser/output/streamer-home-${width}.png`,fullPage:true});
 });
 test(`header and centered show modal at ${width}`, async ({page}) => {
  await page.setViewportSize({width,height:1000});
  await login(page,fs.readFileSync('storage/mockup-owner.txt','utf8').trim());
  await page.goto('/admin/shows-overview');
  await expect(page.locator('.fi-main')).toBeVisible();
  if(width>=1024){
   const search=page.locator('.fi-global-search-field');
   if(await search.count()){
    const overlap=await search.evaluate(el=>{
     const a=el.getBoundingClientRect();
     return [...document.querySelectorAll('.vx-mega-channel')].some(c=>{const b=c.getBoundingClientRect();return a.left<b.right && a.right>b.left && a.top<b.bottom && a.bottom>b.top;});
    });
    expect(overlap).toBe(false);
   }
  }
  const show=page.locator('.vx-chip:visible, .vx-acard:visible, .vx-arow:visible').first();
  await show.click();
  const modal=page.locator('.vx-drawer');
  await expect(modal).toBeVisible();
  const box=await modal.boundingBox();
  expect(Math.abs(box!.x+box!.width/2-width/2)).toBeLessThan(3);
  expect(box!.width).toBeLessThan(width);
  if(width>=1024)expect(box!.width).toBeGreaterThan(850);
  await page.screenshot({path:`tests/Browser/output/show-modal-${width}.png`});
  await page.keyboard.press('Escape');
  await expect(modal).toBeHidden();
 });
}
