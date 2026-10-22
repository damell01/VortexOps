import { chromium } from '@playwright/test';
import { mkdir, writeFile } from 'node:fs/promises';
const browser = await chromium.launch({ headless:true, args:['--use-gl=angle','--use-angle=swiftshader','--enable-webgl','--disable-web-security'] });
const page=await browser.newPage({viewport:{width:1280,height:360},deviceScaleFactor:1});
page.on('pageerror',e=>console.error('BROWSER:',e.message));
await page.goto('http://127.0.0.1:5173/arena-export.html',{waitUntil:'networkidle'});
await page.waitForFunction(()=>window.exportReady===true,{timeout:45000});
await mkdir('storage/app/cinematic-frames',{recursive:true});
for(let i=0;i<36;i++){
 const data=await page.evaluate(i=>window.renderExportFrame(i),i);
 await writeFile('storage/app/cinematic-frames/'+String(i).padStart(3,'0')+'.png',Buffer.from(data.split(',')[1],'base64'));
}
await writeFile('storage/app/colosseum-still.png',Buffer.from((await page.evaluate(()=>window.renderExportFrame(35))).split(',')[1],'base64'));
await browser.close();
