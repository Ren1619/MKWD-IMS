import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import React from 'react';
import * as icons from 'lucide-react';
import { Button } from '@base-ui/react/button';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const showPage = process.argv[2] === 'Show';
const filename = fileURLToPath(
    new URL(
        `../resources/js/pages/Inventory/Transfers/${showPage ? 'Show' : 'Index'}.tsx`,
        import.meta.url,
    ),
);
const code = ts.transpileModule(readFileSync(filename, 'utf8'), {
    fileName: filename,
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        jsx: ts.JsxEmit.ReactJSX,
        target: ts.ScriptTarget.ES2022,
        esModuleInterop: true,
    },
}).outputText;
const wrapper = ({ children }) => React.createElement('div', null, children);
const module = { exports: {} };
function loadLocalModule(relative) {
    const sourceFile = fileURLToPath(new URL(relative, import.meta.url));
    const output = ts.transpileModule(readFileSync(sourceFile, 'utf8'), {
        fileName: sourceFile,
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
            target: ts.ScriptTarget.ES2022,
            esModuleInterop: true,
        },
    }).outputText;
    const loaded = { exports: {} };
    new Function('require', 'module', 'exports', output)(
        localRequire,
        loaded,
        loaded.exports,
    );
    return loaded.exports;
}
const localRequire = (specifier) => {
    if (specifier === 'lucide-react') return icons;
    if (specifier === '@inertiajs/react') {
        return {
            Head: () => null,
            setLayoutProps: () => {},
            Link: wrapper,
            Form: ({ action, children }) =>
                React.createElement(
                    'form',
                    { action: action.url, method: action.method },
                    typeof children === 'function'
                        ? children({ errors: {}, processing: false })
                        : children,
                ),
        };
    }
    if (specifier === '@/routes/inventory/transfers') {
        return {
            index: () => ({ url: '/inventory/transfers', method: 'get' }),
            store: () => ({ url: '/inventory/transfers', method: 'post' }),
            show: () => ({ url: '/inventory/transfers/1', method: 'get' }),
            transition: () => ({
                url: '/inventory/transfers/1/transition',
                method: 'patch',
            }),
            print: { url: () => '/inventory/transfers/1/print' },
        };
    }
    if (specifier === './types') return { label: (value) => value };
    if (specifier === '@/components/ui/card')
        return loadLocalModule('../resources/js/components/ui/card.tsx');
    if (specifier === '@/lib/utils')
        return loadLocalModule('../resources/js/lib/utils.ts');
    if (specifier.startsWith('@/components/')) {
        return new Proxy(
            {},
            {
                get: (_, name) => {
                    if (name === 'Button') return Button;
                    if (name === 'Input' || name === 'DatePicker')
                        return ({ name }) =>
                            React.createElement('input', { name });
                    if (name === 'FormSelect')
                        return ({ name, children }) =>
                            React.createElement('select', { name }, children);
                    if (name === 'default') return () => null;
                    return wrapper;
                },
            },
        );
    }
    return require(specifier);
};
new Function('require', 'module', 'exports', code)(
    localRequire,
    module,
    module.exports,
);
if (showPage) {
    const transfer = {
        id: 1,
        document_no: 'PTR-001',
        status: 'approved',
        transfer_type: 'reassignment',
        desired_date: '2026-11-10',
        destination_location: 'New office',
        reason: 'New accountable officer',
        source_snapshot: {
            name: 'Pump',
            recipient_name: 'Outgoing employee',
            document_no: 'ARE-001',
        },
        recipient_snapshot: { name: 'Receiving employee' },
        actions: [],
    };
    const receipt = {
        id: 4,
        action: 'acknowledge_receipt',
        actor_name: 'Receiving employee',
        created_at: '2026-11-10T10:00:00Z',
        attestation: 'I received the property.',
        evidence: { handover_date: '2026-11-10' },
    };
    process.stdout.write(
        JSON.stringify({
            pending: renderToStaticMarkup(
                React.createElement(module.exports.default, {
                    transfer,
                    allowedActions: ['acknowledge_receipt'],
                    today: '2026-11-10',
                }),
            ),
            recorded: renderToStaticMarkup(
                React.createElement(module.exports.default, {
                    transfer: { ...transfer, actions: [receipt] },
                    allowedActions: [],
                    today: '2026-11-10',
                }),
            ),
        }),
    );
} else {
    process.stdout.write(
        JSON.stringify(
            Object.fromEntries(
                [true, false].map((available) => [
                    available ? 'available' : 'empty',
                    renderToStaticMarkup(
                        React.createElement(module.exports.default, {
                            canManage: process.argv[2] !== 'employee',
                            transfers: { data: [], links: [] },
                            assets: available
                                ? [
                                      {
                                          inventory_asset_id: 1,
                                          item_description: 'Test pump',
                                          property_number: 'PPE-001',
                                      },
                                  ]
                                : [],
                            employees: [],
                            filters: {},
                            today: '2026-11-10',
                        }),
                    ),
                ]),
            ),
        ),
    );
}
