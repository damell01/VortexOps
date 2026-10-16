import { test, expect } from '@playwright/test';
import fs from 'node:fs';
for (const width of [390, 1366, 1920]) {
 test(`workspace entrance at ${width}`, async ({page}) => {
  await page.setViewportSize({width,height:1000});
  await page.goto('/admin/login');
  const art=await page.locator('.vx-login-art').boundingBox();
  const form=await page.locator('.fi-simple-main').boundingBox();
  if(width>800)expect(art!.x+art!.width).toBeLessThan(form!.x);
  else expect(art!.y+art!.height).toBeLessThanOrEqual(form!.y+2);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2)).toBe(true);
  await page.screenshot({path:`tests/Browser/output/login/before-${width}.png`,fullPage:true});
  let releaseNavigation: () => void = () => {};
  const gate = new Promise<void>(resolve => { releaseNavigation = resolve; });
  await page.route('**/admin**', async route=>{
   if(route.request().method()==='GET' && new URL(route.request().url()).pathname !== '/admin/login') await gate;
   await route.continue();
  });
  await page.locator('input[type=email]').fill(fs.readFileSync('storage/mockup-owner.txt','utf8').trim());
  await page.locator('input[type=password]').fill('MockupTest123!');
  await page.getByRole('button',{name:'Sign in',exact:true}).click();
  const entry=page.locator('[data-vx-workspace-entry]');
  await expect(entry).toBeVisible({timeout:15000});
  await expect(entry).not.toHaveClass(/is-ready/);
  await expect(page.locator('[data-vx-entry-status]')).toHaveText('Open your Dashboard');
  await page.screenshot({path:`tests/Browser/output/login/loading-${width}.png`});
  releaseNavigation();
  await expect(page.locator('.vx-login-experience')).toHaveCount(0,{timeout:20000});
  await expect(entry).toBeHidden({timeout:5000});
  await expect.poll(()=>page.evaluate(()=>sessionStorage.getItem('vx-workspace-entry'))).toBeNull();
  await expect(page.locator('.fi-main')).toBeVisible();
  await page.screenshot({path:`tests/Browser/output/login/dashboard-${width}.png`,fullPage:true});
 });
}
test('failed login never opens the workspace scene',async({page})=>{
 await page.goto('/admin/login');
 await page.locator('input[type=email]').fill(fs.readFileSync('storage/mockup-owner.txt','utf8').trim());
 await page.locator('input[type=password]').fill('wrong-test-password');
 await page.getByRole('button',{name:'Sign in',exact:true}).click();
 await expect(page.locator('.fi-fo-field-wrp-error-message')).toBeVisible({timeout:15000});
 await expect(page.locator('[data-vx-workspace-entry]')).toBeHidden();
 await expect(page.locator('body')).not.toHaveClass(/vx-login-catching/);
});
