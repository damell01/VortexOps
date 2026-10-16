const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function setup({reduced = false, spa = true, storageBlocked = false} = {}) {
    let hook, success, failure, redirect, login = true;
    const classes = new Set(), htmlClasses = new Set(), overlayClasses = new Set(), timers = new Map(), events = new Map(), storage = new Map(); let next = 0;
    const classList = set => ({add: (...v) => v.forEach(x => set.add(x)), remove: (...v) => v.forEach(x => set.delete(x))});
    const link = {hidden:true}, label = {}, caption = {};
    const overlay = {hidden:true,classList:classList(overlayClasses),querySelector:s=>s.includes('continue')?link:s.includes('caption')?caption:label};
    const document = {readyState:'loading',querySelector:s=>s.includes('workspace-entry')?overlay:login?{querySelector:()=>({})}:null,addEventListener:(n,cb)=>events.set(n,cb),body:{classList:classList(classes)},documentElement:{classList:classList(htmlClasses)}};
    const location = {href:'https://vortexops.tech/admin/login',origin:'https://vortexops.tech',assign:url=>redirect=url};
    const window = {location,matchMedia:()=>({matches:reduced}),addEventListener:(n,cb)=>events.set(n,cb),Livewire:{hook:(n,cb)=>hook=cb}};
    if (spa) window.Livewire.navigate=url=>{redirect=url;};
    const sessionStorage={getItem:k=>{if(storageBlocked)throw Error();return storage.get(k)||null;},setItem:(k,v)=>{if(storageBlocked)throw Error();storage.set(k,v);},removeItem:k=>storage.delete(k)};
    vm.runInNewContext(fs.readFileSync('public/js/vortex-login.js','utf8'), {window,document,sessionStorage,URL,Date,requestAnimationFrame:cb=>cb(),setTimeout:(cb,ms)=>{timers.set(++next,{cb,ms});return next},clearTimeout:id=>timers.delete(id)});
    return {classes,overlay,overlayClasses,link,storage,redirect:()=>redirect,request:(payload={components:[{calls:[{method:'authenticate'}]}]})=>hook({payload,succeed:cb=>success=cb,fail:cb=>failure=cb}),succeed:json=>success({json}),fail:()=>failure(),tick:()=>{const [id,job]=[...timers].sort((a,b)=>a[1].ms-b[1].ms)[0]||[];if(job){timers.delete(id);job.cb();}},land:()=>{login=false;events.get('livewire:navigated')();},events};
}
function authenticate(s){s.request();const effects={redirect:'/admin',redirectUsingNavigate:true};s.succeed({components:[{effects}]});return effects;}
test('keeps visible loading scene until the destination is ready',()=>{const s=setup();assert.equal(authenticate(s).redirect,undefined);s.tick();assert(s.classes.has('vx-login-caught'));s.tick();assert.equal(s.redirect(),'https://vortexops.tech/admin');assert.equal(s.overlay.hidden,false);assert.equal(s.overlayClasses.has('is-ready'),false);s.land();assert(s.overlayClasses.has('is-ready'));s.tick();assert(s.overlayClasses.has('is-leaving'));s.tick();assert.equal(s.overlay.hidden,true);assert.equal(s.storage.size,0);});
test('slow navigation offers the real server destination',()=>{const s=setup();authenticate(s);s.tick();s.tick();s.tick();assert.equal(s.link.hidden,false);assert.equal(s.link.href,'https://vortexops.tech/admin');assert.equal(s.overlay.hidden,false);});
test('full navigation fallback retains the reveal marker',()=>{const s=setup({spa:false});authenticate(s);s.tick();s.tick();assert.equal(s.redirect(),'https://vortexops.tech/admin');assert.equal(s.storage.size,1);});
test('blocked session storage still completes SPA reveal',()=>{const s=setup({storageBlocked:true});authenticate(s);s.tick();s.tick();s.land();assert(s.overlayClasses.has('is-ready'));s.tick();s.tick();assert(s.overlay.hidden);});
test('validation failure restores the buddy without navigation',()=>{const s=setup();s.request();s.succeed({components:[{effects:{}}]});assert.equal(s.classes.size,0);assert.equal(s.redirect(),undefined);assert(s.overlay.hidden);});
test('network failure clears catch animation',()=>{const s=setup();s.request();s.fail();assert.equal(s.classes.size,0);assert(s.overlay.hidden);});
test('reduced motion preserves native Livewire redirect',()=>{const s=setup({reduced:true});s.request();const effects={redirect:'/admin'};s.succeed({components:[{effects}]});assert.equal(effects.redirect,'/admin');assert.equal(s.classes.size,0);assert(s.overlay.hidden);});
test('unrelated requests do not trigger capture',()=>{const s=setup();s.request({components:[{calls:[{method:'$refresh'}]}]});assert.equal(s.classes.size,0);});
test('back-forward cache cannot restore a stuck overlay',()=>{const s=setup();authenticate(s);s.tick();s.tick();s.events.get('pageshow')({persisted:true});assert(s.overlay.hidden);assert.equal(s.storage.size,0);});
