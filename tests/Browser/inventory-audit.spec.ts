import { test, expect, Page } from '@playwright/test';
import fs from 'node:fs';
const fixture = () => JSON.parse(fs.readFileSync('storage/framework/testing/inventory-audit-fixture.json','utf8'));
const field = (page:Page, name:string) => page.locator('[wire\\:model="'+name+'"],[wire\\:model\\.live="'+name+'"],[wire\\:model\\.live\\.debounce\\.400ms="'+name+'"]').filter({visible:true});

async function visit(page:Page, url:string) {
    await page.waitForLoadState('networkidle');
    return page.goto(url);
}
async function reload(page:Page) {
    await page.waitForLoadState('networkidle');
    return page.reload();
}
async function scan(page:Page, code='012345678905') {
    const input=field(page,'scanInput');await input.fill(code);await input.press('Enter');
    await expect(input).toHaveValue('');
}
async function select(page:Page, name:string, value:string|number) {
    const input=page.locator('select[wire\\:model\\.live="'+name+'"],select[wire\\:model="'+name+'"]');
    await input.waitFor({state:'attached'});
    if (await input.inputValue() === String(value)) return;
    const roundTrip=await input.getAttribute('wire:model.live')
        ? page.waitForResponse(r=>/\/livewire[^/]*\/update/.test(r.url()) && r.request().method()==='POST').then(r=>r.finished())
        : Promise.resolve();
    if (await input.isVisible()) await input.selectOption(String(value));
    else {
        const button=input.locator('xpath=following-sibling::button[contains(@class,"vx-picker-trigger")][1]');
        const label=await input.locator('option[value="'+value+'"]').textContent();
        await button.click();
        await page.getByRole('dialog').getByRole('button',{name:label!.trim(),exact:false}).click();
    }
    await roundTrip;
}

test.beforeEach(async ({page})=>{
    const errors:string[]=[];
    page.on('pageerror',error=>errors.push(error.message+' '+error.stack));
    page.on('response',response=>{if(response.status()>=400 && /\/livewire[^/]*\/|\/inventory-scanner-api\//.test(response.url())) errors.push(response.status()+' '+response.url())});
    (page as any).auditErrors=errors;
    await visit(page,'/admin/login');
    await page.locator('input[type=email]').fill(fixture().email);
    await page.locator('input[type=password]').fill('inventory-audit-local');
    await page.locator('button[type=submit]').click();
    await expect(page.locator('input[type=password]')).toHaveCount(0,{timeout:30000});
    await page.waitForLoadState('networkidle');
});
test.afterEach(async ({page})=>{await page.waitForLoadState('networkidle');expect((page as any).auditErrors).toEqual([])});

test('scan-to-move and stock corrections persist after reload',async({page})=>{
    const f=fixture();
    await visit(page,'/admin/inventory-scanner');await scan(page);
    await expect(page.getByText('Browser Audit Box',{exact:true})).toBeVisible();
    await page.waitForLoadState('networkidle');
    await page.getByRole('link',{name:'Move / Correct Stock'}).click();
    await page.getByRole('button',{name:'Move stock',exact:true}).click();
    await select(page,'fromLocationId',f.source);await select(page,'toLocationId',f.destination);
    await field(page,'moveQuantity').fill('2');
    await page.getByRole('button',{name:'Move Inventory',exact:true}).filter({visible:true}).click();
    await expect(page.getByText('Stock moved',{exact:true})).toBeVisible();
    await reload(page);await expect(page.locator('.fi-main')).toContainText('Audit Back');
    await select(page,'fromLocationId',f.destination);await field(page,'newQuantity').fill('7');
    await page.getByRole('button',{name:'Save Correction',exact:true}).filter({visible:true}).click();
    await expect(page.getByText('Stock adjusted',{exact:true})).toBeVisible();
    await reload(page);await select(page,'fromLocationId',f.destination);
    await expect(field(page,'newQuantity')).toHaveValue('7');
});

test('continuous scans submit once per operation and unknown barcode recovery closes',async({page})=>{
    const f=fixture();await visit(page,'/admin/inventory-scanner');
    await page.locator('button[wire\\:click="switchMode(\'quickadd\')"]').click();
    await select(page,'qaLocationId',f.source);await field(page,'qaQty').fill('1');
    let requests=0;page.on('request',request=>{if(/\/livewire[^/]*\/update/.test(request.url()))requests++});
    await page.evaluate(()=>{for(let i=0;i<3;i++)window.dispatchEvent(new CustomEvent('barcode-scanned',{detail:{value:'012345678905'},cancelable:true}))});
    await expect(page.getByText('Stock added: Browser Audit Box')).toBeVisible();
    await expect.poll(()=>requests).toBe(3);
    await page.locator('button[wire\\:click="switchMode(\'lookup\')"]').click();
    await scan(page,'AUDIT-UNKNOWN-CODE');
    const sheet=page.getByRole('dialog',{name:'Unknown barcode'});
    await expect(sheet).toBeVisible();await sheet.getByRole('button',{name:'Close',exact:true}).click();
    await expect(sheet).toHaveCount(0);
    await scan(page);await expect(page.getByRole('link',{name:'Move / Correct Stock'})).toBeVisible();
});

test('bulk transfer can cancel, reopen and execute without stuck overlays',async({page})=>{
    const f=fixture();await visit(page,'/admin/stock-transfer');
    await select(page,'fromLocationId',f.source);await select(page,'toLocationId',f.destination);
    const quantity=page.locator('input[wire\\:change]').first();await quantity.fill('1');await quantity.blur();
    await page.getByRole('button',{name:'Execute Transfer',exact:true}).click();
    const dialog=page.getByRole('dialog',{name:'Confirm Transfer'});await expect(dialog).toBeVisible();
    await dialog.getByRole('button',{name:'Cancel',exact:true}).click();await expect(dialog).toHaveCount(0);
    await page.getByRole('button',{name:'Execute Transfer',exact:true}).click();
    await dialog.locator('button[wire\\:click="executeTransfer"]').click();
    await expect(page.getByText('Transfer complete',{exact:true})).toBeVisible();await expect(dialog).toHaveCount(0);
});

test('inventory pages render without horizontal overflow or browser errors',async({page},testInfo)=>{
    const f=fixture();
    for(const path of ['inventory-scanner','stock-transfer','quick-add-stock','inventory-search','inventory-items','inventory-items/'+f.item,'inventory-items/'+f.item+'/edit','inventory-locations',f.stockPath.replace(/^admin\//,''),'inventory-movements','inventory-count','inventory-reconciliation','inventory-overview','inventory-health','inventory-activity','inventory-report','pallets']) {
        const response=await visit(page,'/admin/'+path);expect(response?.status(),path).toBe(200);
        await expect(page.locator('.fi-main')).toBeVisible();
        await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),{message:path}).toBe(true);
    }
    await page.screenshot({path:'tests/Browser/output/inventory-audit/'+testInfo.project.name+'-inventory.png',fullPage:true});
});

test('quick add stock persists through the standalone form',async({page})=>{
    const f=fixture();
    await visit(page,'/admin/quick-add-stock');
    await field(page,'barcode').fill('012345678905');
    await page.locator('button[wire\\:click="scanBarcode"]').click();
    await expect(page.getByText('Browser Audit Box',{exact:true}).first()).toBeVisible();
    await select(page,'locationId',f.source);
    await page.locator('input[wire\\:model\\.number="quantity"]').fill('2');
    await page.locator('button[wire\\:click="addStock"]').click();
    await expect(page.getByText('Stock Added',{exact:true})).toBeVisible();
    await reload(page);
    await expect(page.getByText('Browser Audit Box',{exact:true}).first()).toBeHidden();
});

test('receiving scan updates cases and survives reopening the delivery',async({page},testInfo)=>{
    const f=fixture();
    await visit(page,'/admin/inventory-scanner');
    await page.locator('button[wire\\:click="switchMode(\'receive\')"]').click();
    await select(page,'rcvPalletId',f.pallets[testInfo.project.name]);
    const received=()=>page.evaluate(()=>{
        const root=document.querySelector('[data-vx-page="inventory-scanner"]')!.closest('[wire\\:id]')!;
        return (window as any).Livewire.find(root.getAttribute('wire:id')).get('rcvProgress').lines[0].received_cases;
    });
    expect(Number(await received())).toBe(0);await scan(page);
    // The existing scanner receives a matched manifest line, not one case per product barcode.
    await expect.poll(received).toBe(20);
    await reload(page);
    await page.locator('button[wire\\:click="switchMode(\'receive\')"]').click();
    await page.locator('.vx-received-filter button').click();
    await select(page,'rcvPalletId',f.pallets[testInfo.project.name]);
    await expect.poll(received).toBe(20);
    await expect(page.locator('.vx-received-filter button')).toBeVisible();
});

test('camera manual fallback submits and closes without a stuck layer',async({page})=>{
    await visit(page,'/admin/inventory-scanner');
    await page.locator('[data-camera-scan]').filter({visible:true}).first().click();
    const manual=page.locator('input[x-ref="manualInput"]');
    await expect(manual).toBeVisible();await manual.fill('012345678905');
    await page.getByRole('button',{name:'Use Code',exact:true}).click();
    await expect(manual).toBeHidden();
    await expect(page.getByRole('link',{name:'Move / Correct Stock'})).toBeVisible();
    await expect(page.locator('body')).not.toHaveClass(/vx-camera-scanner-open/);
});

async function signInAs(page:Page,email:string) {
    await visit(page,'about:blank');
    await page.context().clearCookies();await visit(page,'/admin/login');
    await page.locator('input[type=email]').fill(email);
    await page.locator('input[type=password]').fill('inventory-audit-local');
    await page.locator('button[type=submit]').click();
    await expect(page.locator('input[type=password]')).toHaveCount(0);
    await page.waitForLoadState('networkidle');
}

test('admins cannot see another assigned location and streamers have no card controls',async({page})=>{
    const f=fixture();await signInAs(page,f.adminEmail);
    await visit(page,'/admin/inventory-items');
    await expect(page.locator('.vx-product-card').first()).toBeVisible();
    await expect(page.locator('.fi-main')).not.toContainText('Audit Personal Shelf');
    await expect(page.locator('.fi-main')).not.toContainText('Audit Back');
    const hidden = await visit(page,'/admin/inventory-locations/'+f.readerLocation);
    expect([403,404]).toContain(hidden!.status());
    await signInAs(page,f.streamerEmail);
    await visit(page,'/admin/inventory-items');
    await expect(page.locator('.vx-product-card').first()).toBeVisible();
    await expect(page.locator('.vx-product-card button')).toHaveCount(0);
    await expect(page.locator('.vx-product-card .vx-card-actions')).toHaveCount(0);
    await expect(page.locator('.vx-product-card a[href$="/edit"]')).toHaveCount(0);
    const denied=await visit(page,'/admin/inventory-items/'+f.item+'/edit');
    expect(denied!.status()).toBe(403);
});
