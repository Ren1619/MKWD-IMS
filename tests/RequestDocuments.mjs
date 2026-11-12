import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../resources/js/pages/Inventory/Requests/RequestDocuments.tsx', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX },
});
let states = [];
let cursor = 0;
const element = (type, props) => ({ type, props });
const route = { url: (id, options = {}) => `/documents/${id}?${new URLSearchParams(options.query ?? {})}` };
function requireStub(name) {
    if (name === 'react/jsx-runtime') return { jsx: element, jsxs: element, Fragment: 'fragment' };
    if (name === 'react') return { useState: (initial) => {
        const index = cursor++;
        if (!(index in states)) states[index] = initial;
        return [states[index], (value) => { states[index] = typeof value === 'function' ? value(states[index]) : value; }];
    } };
    if (name.includes('/routes/')) return { print: route };
    if (name.includes('hris-reference')) return { HrisReferenceCombobox: 'employee-selector' };
    if (name.endsWith('/button')) return { Button: 'button', buttonVariants: () => 'button' };
    if (name.endsWith('/dialog')) return Object.fromEntries(['Dialog', 'DialogContent', 'DialogDescription', 'DialogHeader', 'DialogTitle'].map((key) => [key, key]));
    if (name.endsWith('/form-select')) return { FormSelect: 'select' };
    if (name.endsWith('/label')) return { Label: 'label' };
    throw new Error(`Unexpected import: ${name}`);
}
const module = { exports: {} };
new Function('require', 'module', 'exports', outputText)(requireStub, module, module.exports);
function nodes(tree) {
    if (!tree || typeof tree !== 'object') return [];
    if (Array.isArray(tree)) return tree.flatMap(nodes);
    return [tree, ...nodes(tree.props?.children)];
}
const props = {
    requestId: 1, status: 'released', isSuperAdmin: true,
    slips: [{ id: 2, slip_no: '11-12-001', issued_at: '2026-11-12' }],
    employees: [{ id: 3, name: 'Selected Employee', position_name: 'Supply Officer' }],
    onLiquidation() {}, onReturn() {},
};
function render(overrides = {}) {
    cursor = 0;
    return nodes(module.exports.RequestDocuments({ ...props, ...overrides }));
}
function actions(tree) {
    return tree.filter((entry) => ['button', 'a'].includes(entry.type)).map((entry) => entry.props.children);
}
assert.deepEqual(actions(render()), ['RIS Liquidation', 'Return Slip', 'Issuance Request', 'Issuance Slip']);
assert.equal(render().filter((entry) => entry.type === 'employee-selector').length, 0);
assert.deepEqual(actions(render({ isSuperAdmin: false })), ['RIS Liquidation', 'Return Slip', 'Issuance Request']);
for (const status of ['submitted', 'division_approved', 'approved', 'ready_for_release', 'partially_released']) {
    assert.deepEqual(actions(render({ status })), ['Issuance Request']);
}
render().find((entry) => entry.type === 'button' && entry.props.children === 'Issuance Slip').props.onClick();
let tree = render();
assert.equal(tree.filter((entry) => entry.type === 'employee-selector').length, 3);
assert.ok(tree.some((entry) => entry.type === 'iframe' && entry.props.title === 'Issuance slip preview'));
tree.find((entry) => entry.type === 'employee-selector' && entry.props.name === 'prepared_by').props.onValueChange('3');
tree = render();
assert.ok(tree.some((entry) => entry.type === 'iframe' && entry.props.src.includes('prepared_by=3')));
assert.ok(tree.some((entry) => entry.type === 'p' && entry.props.children === 'Supply Officer'));
assert.ok(tree.some((entry) => entry.type === 'span' && entry.props.children === 'Selected Employee' && entry.props.className.includes('underline')));
assert.equal(render({ isSuperAdmin: false }).filter((entry) => entry.type === 'employee-selector').length, 0);
assert.equal(render({ status: 'approved' }).filter((entry) => entry.type === 'iframe').length, 0);
states = [];
const multi = render({ slips: [...props.slips, { id: 4, slip_no: '11-12-002', issued_at: '2026-11-12' }] });
multi.find((entry) => entry.type === 'button' && entry.props.children === 'Issuance Slip').props.onClick();
render({ slips: [...props.slips, { id: 4, slip_no: '11-12-002', issued_at: '2026-11-12' }] }).find((entry) => entry.type === 'select').props.onChange({ target: { value: '4' } });
assert.ok(render({ slips: [...props.slips, { id: 4, slip_no: '11-12-002', issued_at: '2026-11-12' }] }).some((entry) => entry.type === 'iframe' && entry.props.src.startsWith('/documents/4?')));
process.stdout.write('Request document actions and preview passed.');
