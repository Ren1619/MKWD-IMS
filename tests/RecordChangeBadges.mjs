import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../resources/js/lib/record-changes.ts', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 } });
const module = { exports: {} };
new Function('module', 'exports', outputText)(module, module.exports);
const { createRecordChanges, recordIdentity } = module.exports;
const originalNow = Date.now;
const originalSetTimeout = globalThis.setTimeout;
const originalClearTimeout = globalThis.clearTimeout;
let now = 1000;
let timerId = 0;
const timers = new Map();
Date.now = () => now;
globalThis.setTimeout = (callback) => { timers.set(++timerId, callback); return timerId; };
globalThis.clearTimeout = (id) => timers.delete(id);

try {
    const store = createRecordChanges();
    let notifications = 0;
    const unsubscribe = store.subscribe(() => notifications++);
    const scope = '7:Inventory/Requests/Index';
    const old = { id: 1, status: 'submitted', lines: [{ id: 11, quantity: 2 }] };
    const updated = { ...old, status: 'division_approved' };
    const added = { id: 2, status: 'submitted' };
    assert.equal(store.get(scope, 'requests', old), null);
    store.compare(scope, { requests: { data: [old] } }, { requests: { data: [updated, added] } });
    assert.equal(store.get(scope, 'requests', updated), 'Updated');
    assert.equal(store.get(scope, 'requests', added), 'New');
    assert.equal(store.get('8:Inventory/Requests/Index', 'requests', added), null);
    assert.equal(store.get('7:Inventory/Returns/Index', 'requests', added), null);
    assert.equal(store.get(scope, 'items', added), null);
    assert.equal(notifications, 1);
    const changedNew = { ...added, status: 'approved' };
    store.compare(scope, { requests: { data: [updated, added] } }, { requests: { data: [updated, changedNew] } });
    assert.equal(store.get(scope, 'requests', changedNew), 'New');
    store.compare(scope, { requests: { data: [updated, changedNew] } }, { requests: { data: [changedNew] } });
    assert.equal(store.get(scope, 'requests', updated), null);
    now += 60001;
    for (const callback of [...timers.values()]) callback();
    assert.equal(store.get(scope, 'requests', changedNew), null);
    assert.equal(timers.size, 0);

    store.compare(scope, { requests: { data: [old] } }, { requests: { data: [{ lines: old.lines, status: old.status, id: old.id }] } });
    assert.equal(store.get(scope, 'requests', old), null);
    store.compare(scope, { asset: old }, { asset: { ...old, lines: [{ id: 11, quantity: 3 }] } });
    assert.equal(store.get(scope, 'asset', old), 'Updated');
    store.compare(scope, {}, { requests: { data: [added] } });
    assert.equal(store.get(scope, 'requests', added), null);
    store.compare(scope, { requests: { data: [] } }, { requests: { data: [added] } });
    assert.equal(store.get(scope, 'requests', added), 'New');

    const category = { inv_class_cat_id: 2, inv_mjr_cat_id: 1, name: 'Old' };
    const major = { inv_mjr_cat_id: 1, class_categories: [category] };
    const afterCategory = { ...category, name: 'Renamed' };
    store.compare(scope, { majorCategories: [major] }, { majorCategories: [{ ...major, class_categories: [afterCategory] }] });
    assert.equal(store.get(scope, 'majorCategories', afterCategory), 'Updated');
    assert.equal(recordIdentity(category), 'inv_class_cat_id:2');
    assert.equal(recordIdentity({ inventory_item_batch_id: 8, inventory_item_id: 3 }), 'inventory_item_batch_id:8');
    assert.equal(recordIdentity({ inventory_item_id: 3 }), 'inventory_item_id:3');
    assert.equal(recordIdentity({ inventory_asset_id: 4 }), 'inventory_asset_id:4');
    assert.equal(recordIdentity(null), null);
    assert.equal(recordIdentity({ name: 'no identifier' }), null);
    unsubscribe();
    store.clear();
    assert.equal(timers.size, 0);
} finally {
    Date.now = originalNow;
    globalThis.setTimeout = originalSetTimeout;
    globalThis.clearTimeout = originalClearTimeout;
}
