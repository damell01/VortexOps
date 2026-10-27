import { test, expect } from '@playwright/test';
import fs from 'node:fs';
const script = fs.readFileSync('resources/views/filament/inventory-scanner-reliability.blade.php','utf8').match(/<script>([\s\S]*?)<\/script>/)![1];

test('receiving observer settles instead of mutating controls forever', async ({page}) => {
    await page.setContent('<div data-vx-page="inventory-scanner"><label><select wire:model.live="rcvPalletId"><option value="1">Open — pending</option><option value="2">Closed — received</option></select></label></div>');
    await page.addScriptTag({content:script});
    await expect(page.locator('.vx-received-filter button')).toHaveText('Show received pallets (1)');
    const changes = await page.evaluate(async () => {
        let count=0;
        const observer=new MutationObserver(records=>count+=records.length);
        observer.observe(document.querySelector('.vx-received-filter')!,{childList:true,subtree:true});
        await new Promise(resolve=>setTimeout(resolve,500));
        observer.disconnect();return count;
    });
    expect(changes).toBe(0);
    await page.locator('.vx-received-filter button').click();
    await expect(page.locator('.vx-received-filter button')).toHaveText('Hide received pallets');
    await expect(page.locator('option[value="2"]')).not.toBeDisabled();
});

test('rapid scans are serialized without dropping operations', async ({page}) => {
    await page.setContent('<div wire:id="scanner"><div data-vx-page="inventory-scanner"><input wire:model="scanInput"></div></div>');
    await page.evaluate(() => {
        (window as any).scans=[];(window as any).active=0;(window as any).peak=0;
        (window as any).Livewire={find:()=>({call:async (_method:string, code:string)=>{
            (window as any).active++;(window as any).peak=Math.max((window as any).peak,(window as any).active);
            await new Promise(resolve=>setTimeout(resolve,60));
            (window as any).scans.push(code);(window as any).active--;
        }})};
    });
    await page.addScriptTag({content:script});
    await page.evaluate(() => {
        for (const value of ['FIRST','SECOND','SECOND']) window.dispatchEvent(new CustomEvent('barcode-scanned',{detail:{value},cancelable:true}));
        const input=document.querySelector('input')!;input.value='FOURTH';
        input.dispatchEvent(new KeyboardEvent('keydown',{key:'Enter',bubbles:true,cancelable:true}));
    });
    await expect.poll(()=>page.evaluate(()=>(window as any).scans)).toEqual(['FIRST','SECOND','SECOND','FOURTH']);
    expect(await page.evaluate(()=>(window as any).peak)).toBe(1);
});

test('failed scans release the queue without retrying a possible write', async ({page}) => {
    await page.setContent('<div wire:id="scanner"><div data-vx-page="inventory-scanner"></div></div>');
    await page.evaluate(() => {(window as any).Livewire={find:()=>({call:async()=>{throw new Error('Offline')}})}});
    await page.addScriptTag({content:script});
    await page.evaluate(()=>window.dispatchEvent(new CustomEvent('barcode-scanned',{detail:{value:'ONE'},cancelable:true})));
    await expect(page.getByRole('alert')).toContainText('Check inventory');
    await page.evaluate(()=>{(window as any).Livewire={find:()=>({call:async()=>{(window as any).recovered=true}})};window.dispatchEvent(new CustomEvent('barcode-scanned',{detail:{value:'TWO'},cancelable:true}));});
    await expect.poll(()=>page.evaluate(()=>(window as any).recovered)).toBe(true);
});

test('every history restore dismisses the navigation overlay', async ({page}) => {
    await page.route('http://audit.test/**',route=>route.fulfill({contentType:'text/html',body:'<html><body><button id="action">Inventory action</button></body></html>'}));
    await page.goto('http://audit.test/admin/inventory-scanner');
    const app=fs.readFileSync('resources/js/app.js','utf8');
    const loader=app.slice(app.indexOf('function installVortexNavigationLoader()'),app.indexOf('// Shared lazy script loader'));
    await page.addScriptTag({content:loader});
    await expect(page.locator('#vx-nav-loader')).toHaveCount(1);
    await page.evaluate(()=>{
        window.dispatchEvent(new PageTransitionEvent('pageshow'));
        document.getElementById('vx-nav-loader')!.classList.add('vx-show');
        window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true}));
    });
    await expect(page.locator('#vx-nav-loader')).not.toHaveClass(/vx-show/);
    await page.locator('#action').click();
});
