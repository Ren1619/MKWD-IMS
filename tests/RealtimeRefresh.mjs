import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../resources/js/lib/realtime-refresh.ts', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } });
const module = { exports: {} };
new Function('module', 'exports', outputText)(module, module.exports);
const { createRealtimeRefresh, affectsPage } = module.exports;
assert(affectsPage('Inventory/Requests/Index', 'inventory'));
assert(affectsPage('Inventory/Assets/Show', 'inventory'));
assert(affectsPage('Admin/Users/Index', 'administration'));
assert(affectsPage('settings/hris-integration', 'administration'));
assert(!affectsPage('Admin/Users/Index', 'inventory'));
assert(!affectsPage('Inventory/Requests/Index', 'administration'));

let component = 'Inventory/Requests/Index';
let blocked = false;
let reloads = 0;
let finish;
let nextId = 0;
const timers = new Map();
const refresh = createRealtimeRefresh({
    component: () => component,
    blocked: () => blocked,
    reload: (callback) => { reloads++; finish = callback; },
    schedule: (callback, delay) => { timers.set(++nextId, { callback, delay }); return nextId; },
    cancel: (id) => timers.delete(id),
});
function tick() {
    const [id, timer] = timers.entries().next().value;
    timers.delete(id);
    timer.callback();
}
refresh.notify('inventory');
refresh.notify('inventory');
assert.equal(timers.size, 1);
tick();
assert.equal(reloads, 1);
refresh.notify('inventory');
assert.equal(timers.size, 0);
finish();
assert.equal(timers.size, 1);
blocked = true;
tick();
assert.equal(reloads, 1);
blocked = false;
tick();
assert.equal(reloads, 2);
finish(true);
assert.equal([...timers.values()][0].delay, 5000);
tick();
assert.equal(reloads, 3);
finish();
refresh.notify('inventory');
component = 'Admin/Users/Index';
tick();
assert.equal(reloads, 3);
refresh.notify('administration');
assert.equal(timers.size, 1);
refresh.dispose();
assert.equal(timers.size, 0);
refresh.notify('account');
assert.equal(timers.size, 0);
