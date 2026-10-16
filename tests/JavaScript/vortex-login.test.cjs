const {test} = require('node:test');
const assert = require('node:assert/strict');
const vm = require('node:vm');
const fs = require('node:fs');
function setup(reduced = false) {
    let hook, success, failure, redirect;
    const classes = new Set(); const timers = new Map(); let next = 0;
    const document = {querySelector: () => ({querySelector: () => ({})}), addEventListener() {}, body: {classList: {add: (...v) => v.forEach(x => classes.add(x)), remove: (...v) => v.forEach(x => classes.delete(x))}}};
    const location = {href:'https://vortexops.tech/admin/login', origin:'https://vortexops.tech', assign: url => redirect = url};
    const window = {location, addEventListener() {}, Livewire: {hook: (name, cb) => hook = cb}};
    vm.runInNewContext(fs.readFileSync('public/js/vortex-login.js','utf8'), {window,document,location,URL,matchMedia:()=>({matches:reduced}),setTimeout:cb=>{timers.set(++next,cb);return next},clearTimeout:id=>timers.delete(id)});
    return {classes, timers, redirect:()=>redirect, request: (payload = {components:[{calls:[{method:'authenticate'}]}]}) => hook({payload,succeed:cb=>success=cb,fail:cb=>failure=cb}), succeed: json=>success({json}),fail:()=>failure(),flush:()=>{const jobs=[...timers.values()];timers.clear();jobs.forEach(cb=>cb())}};
}
test('success uses the server destination after shake and wipe',()=>{const s=setup();s.request();assert(s.classes.has('vx-login-catching'));const effects={redirect:'/account/password',redirectUsingNavigate:true};s.succeed({components:[{effects}]});assert.equal(effects.redirect,undefined);assert(s.classes.has('vx-login-catching'));s.flush();assert(s.classes.has('vx-login-caught'));s.flush();assert(s.classes.has('vx-login-entering'));s.flush();assert.equal(s.redirect(),'https://vortexops.tech/account/password');});
test('validation failure restores the buddy without navigating',()=>{const s=setup();s.request();s.succeed({components:[{effects:{}}]});assert.equal(s.classes.size,0);assert.equal(s.timers.size,0);assert.equal(s.redirect(),undefined);});
test('network failure clears the animation',()=>{const s=setup();s.request();s.fail();assert.equal(s.classes.size,0);assert.equal(s.timers.size,0);});
test('reduced motion preserves Livewire redirect',()=>{const s=setup(true);s.request(JSON.stringify({components:[{calls:[{method:'authenticate'}]}]}));const effects={redirect:'/admin'};s.succeed({components:[{effects}]});assert.equal(effects.redirect,'/admin');assert.equal(s.classes.size,0);});
test('unrelated requests do not trigger the catch',()=>{const s=setup();s.request({components:[{calls:[{method:'$refresh'}]}]});assert.equal(s.classes.size,0);});
