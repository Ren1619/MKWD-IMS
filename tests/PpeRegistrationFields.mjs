import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import React from 'react';
import * as icons from 'lucide-react';
import { renderToStaticMarkup } from 'react-dom/server';
import ts from 'typescript';

const require = createRequire(import.meta.url);
const filename = fileURLToPath(
    new URL(
        '../resources/js/pages/Inventory/Assets/Index.tsx',
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
const route = () => ({ url: '/inventory/test', method: 'get' });
Object.assign(route, { url: () => '/inventory/test' });
const wrapper = ({ children }) => React.createElement('div', null, children);
const field =
    (tag) =>
    ({ name, id, children, defaultValue, required }) =>
        React.createElement(
            tag,
            { name, id, defaultValue, required },
            children,
        );
const module = { exports: {} };
const localRequire = (specifier) => {
    if (specifier === 'lucide-react') return icons;
    if (specifier === '@inertiajs/react') {
        return {
            Head: () => null,
            Link: wrapper,
            router: {},
            Form: ({ children }) =>
                React.createElement(
                    'form',
                    null,
                    typeof children === 'function'
                        ? children({ errors: {}, processing: false })
                        : children,
                ),
        };
    }
    if (specifier === '@/hooks/use-app-page') {
        return {
            useAppPage: () => ({
                props: { auth: { permissions: { manage_inventory: true } } },
            }),
        };
    }
    if (specifier === '@/hooks/use-filter-submit') {
        return {
            useFilterSubmit: () => ({
                submitAfterDelay: () => {},
                submitImmediately: () => {},
            }),
        };
    }
    if (specifier.startsWith('@/routes/')) {
        return new Proxy({}, { get: () => route });
    }
    if (specifier.startsWith('@/components/')) {
        return new Proxy(
            {},
            {
                get: (_, name) => {
                    if (name === 'Input' || name === 'DatePicker')
                        return field('input');
                    if (name === 'FormSelect') return field('select');
                    if (name === 'default' || name === 'InputError')
                        return () => null;
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
const pages = Object.fromEntries(
    ['ppe', 'semi_expendable'].map((registryType) => {
        const html = renderToStaticMarkup(
            React.createElement(module.exports.default, {
                assets: { data: [], links: [], total: 0 },
                categories: [],
                employees: [],
                filters: {},
                assetStateOptions: { lifecycles: [], conditions: [] },
                assetEventOptions: [],
                capitalizationThreshold: 50000,
                registryType,
                registrySummary: null,
            }),
        );
        return [
            registryType,
            [...html.matchAll(/\bname="([^"]+)"/g)].map((match) => match[1]),
        ];
    }),
);
process.stdout.write(JSON.stringify(pages));
