import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
import ts from 'typescript';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';

const filename = fileURLToPath(
    new URL('../resources/js/lib/input-validation.ts', import.meta.url),
);
const module = { exports: {} };
const { outputText } = ts.transpileModule(readFileSync(filename, 'utf8'), {
    fileName: filename,
    compilerOptions: {
        module: ts.ModuleKind.CommonJS,
        target: ts.ScriptTarget.ES2022,
        esModuleInterop: true,
    },
});
new Function('require', 'module', 'exports', outputText)(
    createRequire(filename),
    module,
    module.exports,
);
const {
    inputValidationError,
    matchesInputFormat,
    revealInputError,
    validationErrorMessages,
} = module.exports;
const data = JSON.parse(readFileSync(0, 'utf8'));
const formats = JSON.parse(
    readFileSync(
        new URL('../resources/input-formats.json', import.meta.url),
        'utf8',
    ),
);

const summaryFilename = fileURLToPath(
    new URL(
        '../resources/js/components/validation-summary.tsx',
        import.meta.url,
    ),
);
function loadSummary(production) {
    const summaryModule = { exports: {} };
    const summaryCode = ts.transpileModule(
        readFileSync(summaryFilename, 'utf8').replaceAll(
            'import.meta.env.PROD',
            JSON.stringify(production),
        ),
        {
            fileName: summaryFilename,
            compilerOptions: {
                module: ts.ModuleKind.CommonJS,
                jsx: ts.JsxEmit.ReactJSX,
                target: ts.ScriptTarget.ES2022,
                esModuleInterop: true,
            },
        },
    ).outputText;
    new Function('require', 'module', 'exports', summaryCode)(
        createRequire(summaryFilename),
        summaryModule,
        summaryModule.exports,
    );
    return summaryModule.exports.default;
}
const DevelopmentSummary = loadSummary(false);
const ProductionSummary = loadSummary(true);
const summaryEntries = [
    {
        id: 'quantity',
        label: 'Quantity',
        message: 'Enter a whole number.',
        target: {},
    },
    { id: 'date', label: 'Date', message: 'Enter a valid date.', target: {} },
    {
        id: 'action',
        label: 'Decision',
        message: 'Another person must approve this record.',
    },
];
const summary = renderToStaticMarkup(
    React.createElement(DevelopmentSummary, {
        entries: summaryEntries,
        onSelect: () => {},
    }),
);
const emptySummary = renderToStaticMarkup(
    React.createElement(DevelopmentSummary, {
        entries: [],
        onSelect: () => {},
    }),
);
const productionSummary = renderToStaticMarkup(
    React.createElement(ProductionSummary, {
        entries: summaryEntries,
        onSelect: () => {},
    }),
);

function revealCase({
    focus = false,
    hidden = false,
    removed = false,
    reducedMotion = false,
} = {}) {
    const actions = [];
    const visibleControl = {
        focus: (options) => actions.push({ action: 'focus-visible', options }),
        scrollIntoView: (options) =>
            actions.push({ action: 'scroll-visible', options }),
    };
    const details = {
        tagName: 'DETAILS',
        parentElement: null,
        setAttribute: (name) => actions.push({ action: name }),
    };
    const element = {
        isConnected: !removed,
        parentElement: {
            tagName: 'DIV',
            parentElement: details,
            querySelector: () => visibleControl,
        },
        getAttribute: () => (hidden ? 'true' : null),
        classList: { contains: () => false },
        focus: (options) => actions.push({ action: 'focus', options }),
        scrollIntoView: (options) =>
            actions.push({ action: 'scroll', options }),
    };
    globalThis.window = { matchMedia: () => ({ matches: reducedMotion }) };
    revealInputError(element, focus);
    return actions;
}

process.stdout.write(
    JSON.stringify({
        errors: data.inputs.map(inputValidationError),
        matches: data.formats.map(({ format, value }) =>
            matchesInputFormat(formats[format].pattern, value),
        ),
        reveals: {
            submission: revealCase({ focus: true }),
            typing: revealCase(),
            picker: revealCase({ focus: true, hidden: true }),
            reducedMotion: revealCase({ reducedMotion: true }),
            removed: revealCase({ focus: true, removed: true }),
        },
        summary,
        emptySummary,
        productionSummary,
        serverMessages: validationErrorMessages({
            quantity: [
                'Enter a whole number.',
                'Quantity exceeds the issued balance.',
            ],
            'lines.1.unit_cost': 'Enter a valid cost.',
            createUser: { email: 'This email is already in use.' },
        }),
    }),
);
