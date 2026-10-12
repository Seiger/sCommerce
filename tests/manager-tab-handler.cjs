const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const index = fs.readFileSync(path.join(root, 'views/index.blade.php'), 'utf8');
const partial = fs.readFileSync(path.join(root, 'views/partials/tabRender.blade.php'), 'utf8');
const rewrite = index.slice(index.indexOf('const submitting ='), index.indexOf('const stabs ='));
assert.ok(rewrite.includes('setAttribute'), 'Exercise the real manager handler rewrite');

const standardHandler = partial.match(/onclick="([^"]+)"/)[1]
    .replace('{!!$saveUri!!}', '&get=products');
const languageHandler = index.match(/onclick="([^"]*tabSave[^\"]+)"/)[1]
    .replace('{{$tabId}}', 'content').replace('{{$lang}}', 'uk').replace('{{$iUrl}}', '&i=42');

const handlers = [
    [standardHandler, '&get=products'],
    [languageHandler, '&get=content&lang=uk&i=42'],
    // Custom/older tab templates may still omit the terminating semicolon.
    ["javascript:tabSave('&get=orders')", '&get=orders'],
    ["javascript:tabSave('&get=attributes');", '&get=attributes'],
    ["if (!window.__cfRLUnblockHandlers) return false; javascript:tabSave('&get=settings')", '&get=settings'],
];

let checks = 0;
for (const [input, target] of handlers) {
    for (const cloudflareReady of [true, false, undefined]) {
        const attributes = {onclick: input};
        const calls = [];
        const anchor = {
            getAttribute: name => attributes[name],
            setAttribute: (name, value) => { attributes[name] = value; },
        };
        const sandbox = {
            document: {querySelectorAll: () => [anchor]},
            window: {__cfRLUnblockHandlers: cloudflareReady},
            tabSave: value => calls.push(value),
        };
        vm.createContext(sandbox);
        new vm.Script(rewrite).runInContext(sandbox);
        new vm.Script(`(function () { ${attributes.onclick}\n})()`).runInContext(sandbox);
        assert.deepEqual(calls, [target], 'The tab target must survive rewriting without a syntax error');
        checks++;
    }
}

console.log(`Passed ${checks} manager tab-handler checks.`);
