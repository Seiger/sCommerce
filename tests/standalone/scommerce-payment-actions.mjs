import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../views/scripts/orderPaymentActions.blade.php', import.meta.url), 'utf8')
    .replace(/@push\('scripts\.bot'\)|@endpush|<\/?script>/g, '');

function setup({confirm = true, fetch}) {
    const state = {requests: [], confirms: [], reloads: 0, timers: []};
    const classes = new Set();
    const message = {hidden: true, textContent: '', classList: {toggle: (name, on) => (on ? classes.add(name) : classes.delete(name))}};
    const amount = {value: ' 12.50 ', disabled: false};
    const reason = {value: 'Damaged ', disabled: false};
    const fields = {querySelector: selector => (selector === '[data-payment-amount]' ? amount : reason), querySelectorAll: () => []};
    const containerFields = {querySelector: () => null, querySelectorAll: selector => (selector === '[data-payment-field]'
        ? [{dataset: {paymentField: 'line'}, value: '2'}, {dataset: {paymentField: 'count'}, value: ' 3 '}, {dataset: {paymentField: 'settlement'}, value: 'credit'}] : [])};
    const row = {dataset: {payment: '7', label: '#2'}, querySelector: fields.querySelector};
    const orderRow = {dataset: {payment: '0', label: ''}};
    const button = (action, nonce, {form = null, parent = row} = {}) => {
        const element = {dataset: {paymentAction: action, nonce}, textContent: ` ${action} `, disabled: false};
        element.closest = selector => ({'[data-payment-action]': element, '[data-payment-form]': form, '[data-payment]': parent})[selector];
        return element;
    };
    const refund = button('refund', 'n-refund', {form: fields});
    const cancel = button('cancel', 'n-cancel');
    const receive = button('receive', 'n-receive', {form: fields, parent: orderRow});
    const containers = button('containers', 'n-containers', {form: containerFields, parent: orderRow});
    let click;
    const panel = {
        dataset: {url: 'index.php?a=112&get=orderPaymentAction', order: '31', error: 'Request failed', sessionError: 'Session expired',
            pending: 'Sending', confirmOk: 'Run', confirmCancel: 'Cancel'},
        querySelector: () => message,
        querySelectorAll: () => [refund, cancel, amount, reason],
        addEventListener: (name, handler) => { assert.equal(name, 'click'); click = handler; },
    };
    class FormData {
        constructor() { this.values = new Map(); }
        get(key) { return this.values.get(key) ?? null; }
        set(key, value) { this.values.set(key, value); }
    }
    const context = {
        FormData,
        document: {
            querySelector: selector => (selector === '[data-order-payments]' ? panel : {content: 'meta-token'}),
            getElementById: () => null,
            createElement: () => ({textContent: ''}),
        },
        window: {
            location: {reload: () => { state.reloads++; }},
            setTimeout: callback => state.timers.push(callback),
            alertify: {
                confirm: (title, content, ok, cancelled) => {
                    state.confirms.push(content.textContent);
                    (confirm ? ok : cancelled)();
                    return {set: () => {}};
                },
            },
        },
        fetch: async (url, options) => { state.requests.push({url, options}); return fetch(); },
    };
    vm.createContext(context);
    vm.runInContext(source, context);
    const press = target => click({target, preventDefault() {}});
    return {state, press, refund, cancel, receive, containers, message, classes, amount};
}

// A confirmed refund posts the token, nonce, trimmed amount and reason, then reloads.
let resolve;
const pending = new Promise(done => { resolve = done; });
const ok = setup({fetch: () => pending});
const first = ok.press(ok.refund);
await new Promise(done => setImmediate(done));
await ok.press(ok.cancel);
assert.equal(ok.state.requests.length, 1, 'A second action is blocked while one is running');
const body = ok.state.requests[0].options.body;
assert.deepEqual(['_token', 'action', 'order', 'payment', 'nonce', 'amount', 'reason'].map(key => body.get(key)),
    ['meta-token', 'refund', '31', '7', 'n-refund', '12.50', 'Damaged']);
assert.equal(ok.state.requests[0].options.method, 'POST');
assert.equal(ok.state.confirms[0], 'refund #2: 12.50?');
assert.equal(ok.amount.disabled, true, 'Controls are disabled while sending');
resolve({ok: true, status: 200, json: async () => ({success: true, message: 'Done'})});
await first;
assert.equal(ok.message.textContent, 'Done');
ok.state.timers.forEach(callback => callback());
assert.equal(ok.state.reloads, 1);

// Cancelled confirmation sends nothing; capture/cancel send no amount.
const declined = setup({confirm: false, fetch: async () => ({ok: true, json: async () => ({success: true})})});
await declined.press(declined.refund);
assert.equal(declined.state.requests.length, 0);
const voided = setup({fetch: async () => ({ok: true, status: 202, json: async () => ({success: true, message: 'Pending'})})});
await voided.press(voided.cancel);
assert.equal(voided.state.requests[0].options.body.get('amount'), null, 'Cancel ignores the refund inputs of its row');
assert.equal(voided.state.requests[0].options.body.get('reason'), null);

// An order-level offline record posts payment 0 with its own amount and note.
const recorded = setup({fetch: async () => ({ok: true, status: 200, json: async () => ({success: true, message: 'Recorded'})})});
await recorded.press(recorded.receive);
assert.deepEqual(['action', 'payment', 'nonce', 'amount', 'reason'].map(key => recorded.state.requests[0].options.body.get(key)),
    ['receive', '0', 'n-receive', '12.50', 'Damaged']);
assert.equal(recorded.state.confirms[0], 'receive: 12.50?');

// A container return posts its own fields: line, count and settlement.
const returned = setup({fetch: async () => ({ok: true, status: 200, json: async () => ({success: true, message: 'Returned'})})});
await returned.press(returned.containers);
assert.deepEqual(['action', 'payment', 'nonce', 'line', 'count', 'settlement', 'amount'].map(key => returned.state.requests[0].options.body.get(key)),
    ['containers', '0', 'n-containers', '2', '3', 'credit', null]);

// Errors are shown as text and re-enable the controls.
for (const [response, expected] of [
    [{ok: false, status: 422, json: async () => ({success: false, message: '<img src=x onerror=alert(1)>'})}, '<img src=x onerror=alert(1)>'],
    [{ok: false, status: 403, json: async () => { throw new Error('HTML'); }}, 'Session expired'],
    [{ok: true, status: 200, json: async () => ({success: false, message: 'Declined'})}, 'Declined'],
]) {
    const failed = setup({fetch: async () => response});
    await failed.press(failed.refund);
    assert.equal(failed.message.textContent, expected);
    assert.equal(failed.classes.has('is-error'), true);
    assert.equal(failed.amount.disabled, false);
    assert.equal(failed.state.reloads, 0);
    await failed.press(failed.refund);
    assert.equal(failed.state.requests.length, 2, 'Retry enabled after failure');
}
console.log('Payment action UI checks passed: confirmation, CSRF/nonce body, order-level records, busy guard, safe errors and reload');
