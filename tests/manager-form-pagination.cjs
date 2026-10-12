const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const root = path.resolve(__dirname, '..');
const index = fs.readFileSync(path.join(root, 'views/index.blade.php'), 'utf8');
const tabSave = index.slice(index.indexOf('function tabSave('), index.indexOf('{{-- Delete Item --}}'));
let checks = 0;
for (const form of [undefined, {}, {back: {value: ''}}]) {
    const saves = [];
    const context = vm.createContext({document: {form}, saveForm: selector => saves.push(selector)});
    new vm.Script(tabSave + "\ntabSave('&get=products');").runInContext(context);
    assert.deepEqual(saves, form?.back ? ['#form'] : []);
    if (form?.back) assert.equal(form.back.value, '&get=products');
    checks++;
}

// Execute each tab's real pagination setting and the shared cookie handler
// in one browser-like global scope, where const/let redeclarations fail.
const start = index.indexOf("let cookieName = 'scom_per_page';");
const cookieScript = index.slice(start, index.indexOf('document.title =', start))
    .replace(/\{\{--[\s\S]*?--\}\}/g, '');
for (const [tab, key] of [
    ['deliveries', 'scom_deliveries_page_items'],
    ['payments', 'scom_payments_page_items'],
    ['reviews', 'scom_reviews_page_items'],
    ['orders', 'scom_orders_page_items'],
    [null, 'scom_per_page'],
]) {
    const setting = tab ? fs.readFileSync(path.join(root, `views/${tab}Tab.blade.php`), 'utf8')
        .match(/(?:const cookieName|window\.cookieName)\s*=\s*['"][^'"]+['"];?/)[0] : '';
    for (const existing of [false, true]) {
        const listeners = [];
        const actual = [];
        const document = {
            cookie: existing ? `${key}=25` : '',
            querySelectorAll: () => [{getAttribute: () => '100', addEventListener: (_, fn) => listeners.push(fn)}],
            querySelector: () => ({setAttribute: (_, value) => actual.push(value)}),
        };
        const context = vm.createContext({document});
        context.window = context;
        new vm.Script(setting).runInContext(context);
        new vm.Script(cookieScript).runInContext(context);
        assert.equal(vm.runInContext('cookieName', context), key);
        if (existing) assert.deepEqual(actual, ['25']);
        else assert.ok(document.cookie.startsWith(`${key}=50;`));
        listeners[0]({});
        assert.ok(document.cookie.startsWith(`${key}=100;`));
        checks++;
    }
}
console.log(`Passed ${checks} manager form and pagination checks.`);
