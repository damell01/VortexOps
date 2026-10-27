import { test, expect, Page } from '@playwright/test';
import fs from 'node:fs';
const fixture = () => JSON.parse(fs.readFileSync('storage/framework/testing/inventory-audit-fixture.json','utf8'));
const field = (page:Page, name:string) => page.locator('[wire\\:model="'+name+'"],[wire\\:model\\.live="'+name+'"],[wire\\:model\\.live\\.debounce\\.400ms="'+name+'"]').filter({visible:true});

async function scan(page:Page, code='012345678905') {
    const input=field(page,'scanInput');await input.fill(code);await input.press('Enter');
    await expect(input).toHaveValue('');
}
async function select(page:Page, name:string, value:string|number) {
    const input=page.locator('select[wire\\:model\\.live="'+name+'"],select[wire\\:model="'+name+'"]');
    if (await input.isVisible()) await input.selectOption(String(value));
    else {
        const button=input.locator('xpath=following-sibling::button[contains(@class,"vx-picker-trigger")][1]');
        const label=await input.locator('option[value="'+value+'"]').textContent();
        await button.click();
        await page.getByRole('dialog').getByRole('button',{name:label!.trim(),exact:false}).click();
    }
}

test.beforeEach(async ({page})=>{
    const errors:string[]=[];
    page.on('pageerror',error=>errors.push(error.message));
    page.on('response',response=>{if(response.status()>=400 && /\/livewire\/|\/inventory-scanner-api\//.test(response.url())) errors.push(response.status()+' '+response.url())});
    (page as any).auditErrors=errors;
    await page.goto('/admin/login');
    await page.locator('input[type=email]').fill(fixture().email);
    await page.locator('input[type=password]').fill('inventory-audit-local');
    await page.locator('button[type=submit]').click();
    await expect(page.locator('input[type=password]')).toHaveCount(0,{timeout:30000});
});
test.afterEach(async ({page})=>{expect((page as any).auditErrors).toEqual([])});

test('scan-to-move and stock corrections persist after reload',async({page})=>{
    const f=fixture();
    await page.goto('/admin/inventory-scanner');await scan(page);
    await expect(page.getByText('Browser Audit Box',{exact:true})).toBeVisible();
    await page.getByRole('link',{name:'Move / Correct Stock'}).click();
    await page.getByRole('button',{name:'Move stock',exact:true}).click();
    await select(page,'fromLocationId',f.source);await select(page,'toLocationId',f.destination);
    await field(page,'moveQuantity').fill('2');
    await page.getByRole('button',{name:'Move Inventory',exact:true}).filter({visible:true}).click();
    await expect(page.getByText('Stock moved',{exact:true})).toBeVisible();
    await page.reload();await expect(page.locator('.fi-main')).toContainText('Audit Back');
    await select(page,'fromLocationId',f.destination);await field(page,'newQuantity').fill('7');
    await page.getByRole('button',{name:'Save Correction',exact:true}).filter({visible:true}).click();
    await expect(page.getByText('Stock adjusted',{exact:true})).toBeVisible();
    await page.reload();await select(page,'fromLocationId',f.destination);
    await expect(field(page,'newQuantity')).toHaveValue('7');
});

test('continuous scans submit once per operation and unknown barcode recovery closes',async({page})=>{
    const f=fixture();await page.goto('/admin/inventory-scanner');
    await page.locator('button[wire\\:click="switchMode(\'quickadd\')"]').click();
    await select(page,'qaLocationId',f.source);await field(page,'qaQty').fill('1');
    let requests=0;page.on('request',request=>{if(request.url().includes('/livewire/update'))requests++});
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
    const f=fixture();await page.goto('/admin/stock-transfer');
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
    for(const path of ['inventory-scanner','stock-transfer','quick-add-stock','inventory-search','inventory-items','inventory-items/'+f.item,'inventory-items/'+f.item+'/edit','inventory-locations','inventory-stock','inventory-movements','inventory-count','inventory-reconciliation','inventory-overview','inventory-health','inventory-activity','inventory-report','pallets']) {
        const response=await page.goto('/admin/'+path);expect(response?.status(),path).toBe(200);
        await expect(page.locator('.fi-main')).toBeVisible();
        await expect.poll(()=>page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+2),{message:path}).toBe(true);
    }
    await page.screenshot({path:'tests/Browser/output/inventory-audit/'+testInfo.project.name+'-inventory.png',fullPage:true});
});
