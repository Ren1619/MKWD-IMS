import { existsSync, readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import React from 'react';
import * as icons from 'lucide-react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const root = fileURLToPath(new URL('../resources/js/', import.meta.url));
const require = createRequire(import.meta.url);
const modules = new Map();
let layoutProps;
let currentUrl = '/inventory/assets/1';
function load(filename) {
    const resolved = [
        filename,
        `${filename}.ts`,
        `${filename}.tsx`,
        path.join(filename, 'index.ts'),
    ].find(
        (candidate) => existsSync(candidate) && /\.(ts|tsx)$/.test(candidate),
    );
    if (!resolved) throw new Error(`Cannot resolve ${filename}`);
    if (modules.has(resolved)) return modules.get(resolved).exports;
    const module = { exports: {} };
    modules.set(resolved, module);
    const code = ts.transpileModule(readFileSync(resolved, 'utf8'), {
        fileName: resolved,
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
            target: ts.ScriptTarget.ES2022,
            esModuleInterop: true,
        },
    }).outputText;
    const localRequire = (specifier) => {
        if (specifier === 'lucide-react') return icons;
        if (specifier === '@inertiajs/react')
            return {
                Head: () => null,
                usePage: () => ({
                    url: currentUrl,
                    props: {
                        auth: { permissions: { manage_inventory: true } },
                    },
                }),
                router: { push: () => {} },
                setLayoutProps: (props) => {
                    layoutProps = props;
                },
                Link: ({ href, children, ...props }) =>
                    React.createElement(
                        'a',
                        {
                            ...props,
                            href: typeof href === 'string' ? href : href.url,
                        },
                        children,
                    ),
            };
        if (specifier.startsWith('@/'))
            return load(path.join(root, specifier.slice(2)));
        if (specifier.startsWith('.'))
            return load(path.resolve(path.dirname(resolved), specifier));
        return require(specifier);
    };
    new Function('require', 'module', 'exports', code)(
        localRequire,
        module,
        module.exports,
    );
    return module.exports;
}
const asset = JSON.parse(readFileSync(0, 'utf8'));
const Show = load(path.join(root, 'pages/Inventory/Assets/Show.tsx')).default;
const pages = Object.fromEntries(
    [
        'overview',
        'custody',
        'borrowing',
        'accounting',
        'activity',
        'invalid',
    ].map((tab) => {
        currentUrl = `/inventory/assets/1?tab=${tab === 'borrowing' ? 'custody' : tab}${tab === 'borrowing' ? '&history=borrowing' : ''}`;
        return [
            tab,
            renderToStaticMarkup(React.createElement(Show, { asset })),
        ];
    }),
);
const html = Object.values(pages).join('');
const { filterHistoryRecords } = load(
    path.join(root, 'components/property-history.tsx'),
);
const sampleRecords = [
    { search: 'Maria returned laptop', status: 'returned' },
    { search: 'Maria borrowed printer', status: 'borrowed' },
    { search: 'John returned laptop', status: 'returned' },
];
const { PropertyHistory } = load(
    path.join(root, 'components/property-history.tsx'),
);
const historyPreview = renderToStaticMarkup(
    React.createElement(PropertyHistory, {
        title: 'Test history',
        columns: ['Name'],
        emptyMessage: 'No records',
        records: Array.from({ length: 8 }, (_, index) => ({
            id: index,
            search: `Record ${index + 1}`,
            status: 'returned',
            cells: [`Record ${index + 1}`],
            details: 'Supplementary notes',
        })),
    }),
);
process.stdout.write(
    JSON.stringify({
        html,
        pages,
        historyPreview,
        filtered: filterHistoryRecords(
            sampleRecords,
            'MARIA laptop',
            'returned',
        ),
        noMatches: filterHistoryRecords(sampleRecords, 'Maria', 'missing'),
        breadcrumbs: layoutProps.breadcrumbs,
    }),
);
