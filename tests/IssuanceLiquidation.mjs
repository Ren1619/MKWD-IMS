import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../resources/js/pages/Inventory/Requests/IssuanceLiquidation.tsx', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX } });
let states = [];
let cursor = 0;
let lastForm;
const element = (type, props) => ({ type, props });
function requireStub(name) {
    if (name === 'react/jsx-runtime') return { jsx: element, jsxs: element, Fragment: 'fragment' };
    if (name === 'react') return {
        useRef: (current) => ({ current }),
        useState: (initial) => {
            const index = cursor++;
            if (!(index in states)) states[index] = initial;
            return [states[index], (value) => { states[index] = typeof value === 'function' ? value(states[index]) : value; }];
        },
    };
    if (name === '@inertiajs/react') return { useForm: (data) => {
        lastForm = { data, errors: {}, setData(key, value) { this.data[key] = value; }, post() {}, patch() {} };
        return lastForm;
    } };
    if (name.includes('/routes/')) return Object.fromEntries(['allocations', 'print', 'store', 'transition'].map((key) => [key, { url: () => '/test-route' }]));
    if (name.includes('/components/')) return new Proxy({}, { get: (_, key) => key === '__esModule' ? true : String(key) });
    throw new Error(`Unexpected import: ${name}`);
}
const module = { exports: {} };
new Function('require', 'module', 'exports', outputText)(requireStub, module, module.exports);
function nodes(tree) {
    if (!tree || typeof tree !== 'object') return [];
    if (Array.isArray(tree)) return tree.flatMap(nodes);
    return [tree, ...nodes(tree.props?.children)];
}
const material = { supply_request_line_id: 5, inventory_item_id: 9, item_description: 'Supplies', unit_of_measure: 'pc', quantity_issued: 4, previously_utilized: 0 };
const base = { supply_request_id: 10, department: 'Operations', requested_by_name: 'Requester', charge_code: null, issued_at: '2026-11-12', lines: [material] };
const allocations = [{ ...base, issuance_slip_id: 42, is_no: '11-12-001' }, { ...base, issuance_slip_id: 43, is_no: '11-12-002' }, { ...base, supply_request_id: 11, issuance_slip_id: 44, is_no: '11-12-003' }];
const props = { open: true, onOpenChange() {}, allocations, documents: [], canManage: true, supplyRequestId: 10 };
function render(overrides = {}) {
    cursor = 0;
    return module.exports.IssuanceLiquidationDialog({ ...props, ...overrides });
}
let tree = nodes(render());
assert.deepEqual(tree.filter((entry) => entry.type === 'option' && entry.props.value).map((entry) => entry.props.value), ['42', '43']);
tree.find((entry) => entry.type === 'FormSelect').props.onChange({ target: { value: '43' } });
const selectedForm = render();
assert.equal(selectedForm.props.allocation.issuance_slip_id, 43);
states = []; cursor = 0;
tree = nodes(selectedForm.type(selectedForm.props));
assert.equal(lastForm.data.issuance_slip_id, 43);
assert.equal('supply_request_id' in lastForm.data, false);
assert.ok(tree.some((entry) => entry.type === 'Input' && entry.props.id === 'liquidation-slip-number' && entry.props.value === '11-12-002'));
assert.ok(tree.some((entry) => entry.type === 'Input' && entry.props.id === 'liquidation-return-number' && entry.props.required));
assert.equal(tree.some((entry) => entry.type === 'Label' && entry.props.children === 'Issuance Request Form No.'), false);
states = [];
const pending = { id: 100, source_supply_request_id: 10, supply_request_id: null, issuance_slip_id: 42, is_no: '11-12-001', status: 'submitted', department: 'Operations', document_no: 'LIQ-1', liquidation_date: '2026-11-12' };
tree = nodes(render({ documents: [pending] }));
assert.equal(tree.find((entry) => entry.type === 'option' && entry.props.value === '42').props.disabled, true);
assert.equal(tree.find((entry) => entry.type === 'option' && entry.props.value === '43').props.disabled, false);
states = [];
tree = nodes(render({ documents: [pending], allocations: [], canManage: false }));
assert.equal(tree.some((entry) => entry.type === 'FormSelect'), false);
assert.ok(tree.some((entry) => entry.type === 'Button' && entry.props.children.includes('LIQ-1')));
process.stdout.write('Liquidation selects IS IDs and requires return references.');
