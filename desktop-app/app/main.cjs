'use strict';
const {app,BrowserWindow,ipcMain}=require('electron');const path=require('path'),fs=require('fs'),os=require('os'),{spawn}=require('child_process');let win,child=null;
const dataDir=()=>path.join(app.getPath('userData'),'collector');const bundled=()=>app.isPackaged?path.join(process.resourcesPath,'collector'):path.join(__dirname,'..','collector');
function copyDir(s,d){fs.mkdirSync(d,{recursive:true});for(const e of fs.readdirSync(s,{withFileTypes:true})){const a=path.join(s,e.name),b=path.join(d,e.name);e.isDirectory()?copyDir(a,b):fs.copyFileSync(a,b)}}
function ensure(){const d=dataDir();if(!fs.existsSync(path.join(d,'scrapling_collector.cjs')))copyDir(bundled(),d);return d}
function cfg(){const d=ensure(),p=path.join(d,'config.json');if(!fs.existsSync(p)){const x=JSON.parse(fs.readFileSync(path.join(d,'config.example.json'),'utf8'));x.api_url='https://vortexops.tech/api';x.headless=false;fs.writeFileSync(p,JSON.stringify(x,null,2))}return{p,d,v:JSON.parse(fs.readFileSync(p,'utf8'))}}
function emit(s){win&&win.webContents.send('log',String(s))}
function run(cmd,args,cwd){return new Promise(res=>{if(child)return res({code:-1,error:'Already running'});child=spawn(cmd,args,{cwd,windowsHide:false});child.stdout.on('data',emit);child.stderr.on('data',emit);child.on('error',e=>{emit(e.message);child=null;res({code:-1,error:e.message})});child.on('close',code=>{child=null;res({code})})})}
app.whenReady().then(()=>{win=new BrowserWindow({width:900,height:720,minWidth:760,minHeight:620,webPreferences:{preload:path.join(__dirname,'preload.cjs')}});win.loadFile(path.join(__dirname,'index.html'))});
ipcMain.handle('state',()=>{const c=cfg();return{configured:!!c.v.api_token&&!String(c.v.api_token).includes('PASTE_'),apiUrl:c.v.api_url||'https://vortexops.tech/api',computer:os.hostname()}});
ipcMain.handle('save-token',(_,t)=>{const c=cfg();c.v.api_url='https://vortexops.tech/api';c.v.api_token=String(t||'').trim();c.v.headless=false;fs.writeFileSync(c.p,JSON.stringify(c.v,null,2));return{ok:!!c.v.api_token}});
ipcMain.handle('login',()=>{const c=cfg();return run(process.execPath,[path.join(c.d,'login.cjs')],c.d)});
ipcMain.handle('sync',()=>{const c=cfg();return run(process.execPath,[path.join(c.d,'scrapling_collector.cjs')],c.d)});
ipcMain.handle('stop',()=>{if(child){child.kill();child=null;return true}return false});