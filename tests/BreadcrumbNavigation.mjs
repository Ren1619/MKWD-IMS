import { readFileSync, readdirSync, existsSync } from 'node:fs';
import { createRequire } from 'node:module';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import * as inertia from '@inertiajs/react';
import * as icons from 'lucide-react';
import ts from 'typescript';

const root = fileURLToPath(new URL('..', import.meta.url));
const require = createRequire(import.meta.url);
const modules = new Map();

// Load the actual TSX components and generated route helpers without a browser or build server.
function loadSource(filename) {
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
    const { outputText } = ts.transpileModule(readFileSync(resolved, 'utf8'), {
        fileName: resolved,
        compilerOptions: {
            module: ts.ModuleKind.CommonJS,
            jsx: ts.JsxEmit.ReactJSX,
            esModuleInterop: true,
        },
    });
    const localRequire = (specifier) => {
        if (specifier === '@inertiajs/react') return inertia;
        if (specifier === 'lucide-react') return icons;
        if (specifier.startsWith('@/'))
            return loadSource(
                path.join(root, 'resources/js', specifier.slice(2)),
            );
        if (specifier.startsWith('.'))
            return loadSource(path.resolve(path.dirname(resolved), specifier));
        return require(specifier);
    };
    new Function('require', 'module', 'exports', outputText)(
        localRequire,
        module,
        module.exports,
    );
    return module.exports;
}

const { Breadcrumbs } = loadSource(
    path.join(root, 'resources/js/components/breadcrumbs.tsx'),
);
const { index: returnsIndex } = loadSource(
    path.join(root, 'resources/js/routes/inventory/returns/index.ts'),
);
const cases = {
    parent: {
        url: '/inventory/returns/7',
        breadcrumbs: [{ title: 'Return to Stock', href: returnsIndex() }],
    },
    current: {
        url: '/inventory/returns?page=2',
        breadcrumbs: [{ title: 'Return to Stock', href: returnsIndex.url() }],
    },
    nested: {
        url: '/inventory/warehouse/batches/7',
        breadcrumbs: [
            { title: 'Items', href: '/inventory/items' },
            { title: 'Storage', href: '/inventory/warehouse' },
        ],
    },
    title: {
        url: '/inventory/counts/7',
        breadcrumbs: [
            { title: 'Counts', href: '/inventory/counts' },
            { title: 'Count 7' },
        ],
    },
    group: {
        url: '/admin/users',
        breadcrumbs: [
            { title: 'Administration' },
            { title: 'Users', href: '/admin/users' },
        ],
    },
    absolute: {
        url: '/inventory/returns',
        breadcrumbs: [
            { title: 'Returns', href: 'http://localhost/inventory/returns' },
        ],
    },
};
const rendered = Object.fromEntries(
    Object.entries(cases).map(([name, { url, breadcrumbs }]) => [
        name,
        renderToStaticMarkup(
            React.createElement(inertia.App, {
                initialPage: {
                    component: 'Test',
                    props: {},
                    url,
                    version: null,
                    clearHistory: false,
                    encryptHistory: false,
                },
                initialComponent: () =>
                    React.createElement(Breadcrumbs, { breadcrumbs }),
            }),
        ),
    ]),
);

function filesIn(directory) {
    return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
        const filename = path.join(directory, entry.name);
        return entry.isDirectory()
            ? filesIn(filename)
            : filename.endsWith('.tsx')
              ? [filename]
              : [];
    });
}

const destinations = [];
const pages = [];
for (const filename of filesIn(path.join(root, 'resources/js/pages'))) {
    const source = ts.createSourceFile(
        filename,
        readFileSync(filename, 'utf8'),
        ts.ScriptTarget.Latest,
        true,
        ts.ScriptKind.TSX,
    );
    const imports = new Map();
    for (const statement of source.statements) {
        if (!ts.isImportDeclaration(statement)) continue;
        const bindings = statement.importClause?.namedBindings;
        if (!bindings || !ts.isNamedImports(bindings)) continue;
        for (const binding of bindings.elements)
            imports.set(binding.name.text, {
                module: statement.moduleSpecifier.text,
                name: binding.propertyName?.text ?? binding.name.text,
            });
    }
    const variables = new Map();
    function collectVariables(node) {
        if (
            ts.isVariableDeclaration(node) &&
            ts.isIdentifier(node.name) &&
            node.initializer
        )
            variables.set(node.name.text, node.initializer);
        ts.forEachChild(node, collectVariables);
    }
    collectVariables(source);
    function hrefOptions(node, seen = new Set()) {
        if (
            ts.isIdentifier(node) &&
            variables.has(node.text) &&
            !seen.has(node.text)
        ) {
            return hrefOptions(
                variables.get(node.text),
                new Set([...seen, node.text]),
            );
        }
        if (ts.isConditionalExpression(node))
            return [
                ...hrefOptions(node.whenTrue, seen),
                ...hrefOptions(node.whenFalse, seen),
            ];
        return [node];
    }
    function visit(node) {
        if (
            ts.isPropertyAssignment(node) &&
            node.name.getText(source) === 'breadcrumbs' &&
            ts.isArrayLiteralExpression(node.initializer)
        ) {
            const page = path.relative(root, filename).replaceAll('\\', '/');
            pages.push(page);
            for (const item of node.initializer.elements) {
                if (!ts.isObjectLiteralExpression(item))
                    throw new Error(`Unsupported breadcrumb in ${page}`);
                const hrefExpression = item.properties.find(
                    (property) =>
                        ts.isPropertyAssignment(property) &&
                        property.name.getText(source) === 'href',
                )?.initializer;
                if (!hrefExpression) continue;
                for (const href of hrefOptions(hrefExpression)) {
                    let destination;
                    if (ts.isStringLiteral(href)) destination = href.text;
                    else if (ts.isCallExpression(href)) {
                        const accessor = ts.isPropertyAccessExpression(
                            href.expression,
                        )
                            ? href.expression
                            : null;
                        const localName = (
                            accessor?.expression ?? href.expression
                        ).getText(source);
                        const imported = imports.get(localName);
                        if (!imported?.module.startsWith('@/routes'))
                            throw new Error(
                                `Use a Wayfinder breadcrumb in ${page}`,
                            );
                        const routeModule = loadSource(
                            path.join(
                                root,
                                'resources/js',
                                imported.module.slice(2),
                            ),
                        );
                        const route = routeModule[imported.name];
                        destination = accessor
                            ? route[accessor.name.text]()
                            : route();
                    } else
                        throw new Error(
                            `Unsupported breadcrumb destination in ${page}: ${href.getText(source)}`,
                        );
                    const url =
                        typeof destination === 'string'
                            ? destination
                            : destination.url;
                    if (!url || url === '#' || !url.startsWith('/'))
                        throw new Error(
                            `Invalid breadcrumb destination in ${page}`,
                        );
                    destinations.push({ page, url });
                }
            }
        }
        ts.forEachChild(node, visit);
    }
    visit(source);
}
process.stdout.write(JSON.stringify({ rendered, pages, destinations }));
