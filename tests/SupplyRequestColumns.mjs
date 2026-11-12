import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../resources/js/pages/Inventory/Requests/Index.tsx', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022, jsx: ts.JsxEmit.ReactJSX } });
const element = (type, props) => ({ type, props });
const route = Object.assign(() => ({ url: '/inventory/requests' }), { url: () => '/inventory/requests' });
function requireStub(name) {
    if (name === 'react/jsx-runtime') return { jsx: element, jsxs: element, Fragment: 'fragment' };
    if (name === 'react') return { useState: (initial) => [initial, () => {}] };
    if (name === '@inertiajs/react') return { Head: 'Head', router: {}, useForm: (data) => ({ data, errors: {}, processing: false }) };
    if (name.includes('/routes/')) return { index: route, transition: route };
    return new Proxy({}, { get: (_, key) => key === '__esModule' ? true : String(key) });
}
const module = { exports: {} };
new Function('require', 'module', 'exports', outputText)(requireStub, module, module.exports);
function nodes(tree) {
    if (!tree || typeof tree !== 'object') return [];
    if (Array.isArray(tree)) return tree.flatMap(nodes);
    return [tree, ...nodes(tree.props?.children)];
}
function text(tree) {
    if (tree === null || tree === undefined || typeof tree === 'boolean') return '';
    if (typeof tree !== 'object') return String(tree);
    if (Array.isArray(tree)) return tree.map(text).join('');
    return text(tree.props?.children);
}
const base = { id: 1, irf_no: '11-12-001', ris_no: 'RIS-1', requester_name: 'Requester', office_name: 'Operations', purpose: 'Supplies', submitted_at: '2026-11-12T08:00:00', lines: [], actions: [], return_documents: [], issuance_slips: [] };
const records = ['submitted', 'division_approved', 'approved', 'ready_for_release'].map((status, index) => ({ ...base, id: index + 1, status }));
records.push({ ...base, id: 5, status: 'released', issuance_slips: [{ id: 8, slip_no: '11-12-008' }, { id: 9, slip_no: '11-12-009' }] });
const props = { requests: { data: records, links: [], total: records.length, from: 1, to: records.length }, issuanceAllocations: [], liquidationDocuments: [], items: [], canManage: false, isSuperAdmin: false, responsibilityCenters: [], employees: [], issuanceContext: { irf_no: '11-12-002' }, filters: {} };
let tree = nodes(module.exports.default(props));
const queue = tree.find((entry) => entry.type === 'Table' && nodes(entry).some((child) => child.type === 'caption' && text(child) === 'Supply request queue'));
assert.deepEqual(nodes(queue).filter((entry) => entry.type === 'TableHead').map(text), ['IRF No.', 'Requester', 'Office', 'IS No.', 'Submitted', 'Items', 'Status']);
const rows = nodes(queue).filter((entry) => entry.type === 'TooltipTrigger').map((entry) => entry.props.children.filter((child) => child?.type === 'TableCell'));
assert.equal(rows.length, records.length);
for (const row of rows.slice(0, 4)) {
    assert.equal(text(row[2]), 'Operations');
    assert.equal(text(row[3]), 'N/A');
}
assert.equal(text(rows[4][3]), '11-12-008, 11-12-009');
assert.equal(tree.some((entry) => entry.type === 'TableHead' && text(entry) === 'ISF No.'), false);
tree = nodes(module.exports.default({ ...props, requests: { ...props.requests, data: [] } }));
assert.ok(tree.some((entry) => entry.type === 'TableCell' && entry.props.colSpan === 7));
process.stdout.write('IRF and IS request columns passed.');
