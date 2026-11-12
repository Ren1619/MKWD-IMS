import { readFileSync } from 'node:fs';
import ts from 'typescript';

const source = readFileSync(new URL('../resources/js/lib/supply-request-history.ts', import.meta.url), 'utf8');
const { outputText } = ts.transpileModule(source, {
    compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2022 },
});
const module = { exports: {} };
new Function('module', 'exports', outputText)(module, module.exports);
const entries = JSON.parse(readFileSync(0, 'utf8'));
process.stdout.write(JSON.stringify(entries.map(module.exports.supplyRequestHistoryDescription)));
