const Module = require('node:module');
const original = Module._load;
Module._load = function (name, ...args) { return name === 'canvas' ? {} : original.call(this, name, ...args); };
const { JSDOM } = require('jsdom');
const fs = require('node:fs');
const assert = require('node:assert/strict');
const w = new JSDOM('<body><form wire:submit="save"><button type="submit">Save</button></form></body>', { runScripts: 'outside-only' }).window;
let clock = 0, next = 0;
const timers = new Map();
w.setTimeout = (fn, ms) => { const id = ++next; timers.set(id, { fn, at: clock + ms }); return id; };
w.clearTimeout = id => timers.delete(id);
function tick(ms) { clock += ms; for (const [id, timer] of [...timers]) if (timer.at <= clock) { timers.delete(id); timer.fn(); } }
let hook;
w.Livewire = { hook: (name, fn) => { hook = fn; } };
w.eval(fs.readFileSync('resources/js/action-feedback.js', 'utf8').replace(/^import.*;\n/, ''));
function request(method, serialized = false) {
    const body = { components: [{ calls: [{ method }] }] };
    const callbacks = {};
    hook({ payload: serialized ? JSON.stringify(body) : body,
        respond: fn => callbacks.respond = fn,
        succeed: fn => callbacks.succeed = fn,
        fail: fn => callbacks.fail = fn });
    return callbacks;
}
request('$refresh'); tick(800); assert.equal(w.document.querySelector('[role=status]'), null);
let action = request('save', true);
tick(699); assert.equal(w.document.querySelector('[role=status]'), null);
tick(1); assert.match(w.document.querySelector('[role=status]').textContent, /Working/);
action.succeed({ json: { components: [] } }); assert.equal(w.document.querySelector('[role=status]'), null);
action = request('save'); action.respond({ status: 500 });
assert.match(w.document.querySelector('[role=alert]').textContent, /didn.t save/);
w.document.querySelector('[role=alert] button').click();
action = request('save'); action.fail({ status: 419 });
assert.match(w.document.querySelector('[role=alert]').textContent, /session expired/);
w.document.querySelector('[role=alert] button').click();
const stale = request('save');
w.document.dispatchEvent(new w.Event('livewire:navigating'));
const current = request('save');
stale.fail({ status: 500 }); tick(700);
assert.equal(w.document.querySelector('[role=alert]'), null);
assert.match(w.document.querySelector('[role=status]').textContent, /Working/);
current.succeed({ json: { components: [] } });
assert.equal(w.document.querySelector('[role=status]'), null);
w.document.querySelector('form').dispatchEvent(new w.Event('submit', { bubbles: true }));
w.document.dispatchEvent(new w.Event('livewire:navigating')); tick(5000);
assert.equal(w.document.querySelector('[role=alert]'), null);
w.close(); console.log('Action feedback: serialized requests, delayed progress, server/network failures and stale-navigation guards passed.');
