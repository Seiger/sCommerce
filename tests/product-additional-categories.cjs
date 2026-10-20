const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const template = fs.readFileSync(path.join(__dirname, '../views/productTab.blade.php'), 'utf8');
const start = template.indexOf('        function syncAdditionalCategories()');
const end = template.indexOf('        $(document).ready', start);
assert.ok(start >= 0 && end > start);

const options = [
    { value: '2', selected: true, disabled: false },
    { value: '234', selected: true, disabled: false },
    { value: '240', selected: true, disabled: false },
];
const primaryInputs = [{ value: '2' }];
const additional = { options };
let refreshes = 0;
let hasAdditional = true;
const form = {
    querySelector: () => hasAdditional ? additional : null,
    querySelectorAll: () => primaryInputs,
};
const context = vm.createContext({
    document: { getElementById: () => form },
    $: () => ({ trigger(event) { assert.equal(event, 'change.select2'); refreshes++; } }),
});
vm.runInContext(template.slice(start, end), context);
const sync = () => vm.runInContext('syncAdditionalCategories()', context);

sync();
assert.deepEqual(options.map(x => x.selected), [false, true, true]);
assert.deepEqual(options.map(x => x.disabled), [true, false, false]);
assert.equal(refreshes, 1);
sync();
assert.equal(refreshes, 1, 'Unchanged state should not refresh Select2 again');

primaryInputs[0].value = '234';
sync();
assert.deepEqual(options.map(x => x.selected), [false, false, true]);
assert.deepEqual(options.map(x => x.disabled), [false, true, false]);

// Clearing a primary category makes the option available without selecting it automatically.
primaryInputs[0].value = '0';
sync();
assert.deepEqual(options.map(x => x.disabled), [false, false, false]);
assert.deepEqual(options.filter(x => x.selected).map(x => x.value), ['240']);

// Each site's primary is excluded in multisite mode.
primaryInputs.push({ value: '2' }, { value: '240' });
sync();
assert.deepEqual(options.map(x => x.disabled), [true, false, true]);
assert.ok(options.every(x => !x.selected));

hasAdditional = false;
assert.doesNotThrow(sync, 'Hidden additional category field is supported');
console.log('Product additional category checks passed');
