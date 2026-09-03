// node tests/standalone/scommerce-bulk-status.mjs
// Exercise the shipped browser handler with an isolated DOM/fetch adapter.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
const source = fs.readFileSync(new URL('../../views/scripts/ordersBulkStatus.blade.php', import.meta.url), 'utf8')
    .replace(/^<script>\s*/, '').replace(/\s*<\/script>\s*$/, '');
class Control {
    constructor(properties = {}) {
        Object.assign(this, {checked: false, disabled: false, hidden: true, dataset: {}, listeners: {}, attributes: {}}, properties);
        const classes = new Set();
        this.classList = {add: name => classes.add(name), remove: name => classes.delete(name), contains: name => classes.has(name)};
    }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    async trigger(name, event = {}) { for (const handler of this.listeners[name] || []) await handler({preventDefault() {}, ...event}); }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    focus() {}
}
function setup() {
    const rows = [new Control({value: '31', checked: true}), new Control({value: '30'}), new Control({value: '28', checked: true})];
    const radios = [new Control({value: '2', dataset: {statusLabel: 'Processing'}})];
    const apply = new Control();
    const cancel = new Control();
    const clear = new Control();
    const selectAll = new Control();
    const message = new Control();
    const menu = new Control({open: true});
    menu.contains = () => false;
    menu.querySelector = () => new Control();
    const form = new Control({action: 'https://example.test/manager/index.php?a=112&_token=old&id=module&get=ordersBulkStatus', dataset: {
        confirm: 'Change :count orders to :status?', error: 'Reload before retrying', sessionError: 'Session expired', pending: 'Updating',
        confirmTitle: 'Change status', confirmOk: 'Change status', confirmCancel: 'Cancel'
    }});
    form.querySelector = selector => selector === '[type="submit"]' ? apply : cancel;
    form.querySelectorAll = selector => selector === '[name="status"]' ? radios : [apply, cancel];
    form.reset = () => radios.forEach(input => { input.checked = false; });
    const bar = new Control();
    const document = new Control();
    document.createElement = () => new Control();
    const controls = {'[data-orders-bulk-form]': form, '[data-orders-bulk-status]': menu,
        '[data-orders-bulk-bar]': bar, '[data-orders-bulk-message]': message,
        '[data-orders-select-all]': selectAll, '[data-orders-selection-clear]': clear,
        'meta[name="csrf-token"]': {content: 'current-token'}};
    document.querySelector = selector => controls[selector];
    document.querySelectorAll = selector => selector === '[data-order-select]' ? rows : [];
    class TestFormData {
        constructor() { this.entries = [['_token', 'rendered-token'], ...radios.filter(r => r.checked).map(r => ['status', r.value])]; }
        append(key, value) { this.entries.push([key, value]); }
        get(key) { return this.entries.find(entry => entry[0] === key)?.[1]; }
        set(key, value) { this.entries = this.entries.filter(entry => entry[0] !== key); this.append(key, value); }
    }
    const state = {calls: [], reloads: 0, confirm: true, prompts: [], dialogs: []};
    let resolveFetch;
    let rejectFetch;
    const window = {confirm: () => { throw new Error('Native confirm must not be used'); }, alertify: {
        confirm: (title, content, accept, cancel) => {
            state.prompts.push(content.textContent);
            const dialog = {title, content, accept, cancel, set(settings) {
                this.settings = settings;
                if (state.confirm === true) accept();
                else if (state.confirm === false) cancel();
                return this;
            }};
            state.dialogs.push(dialog);
            return dialog;
        }
    }, location: {
        href: 'https://example.test/manager/?get=orders&status=1&order=id&direc=desc&page=2',
        reload: () => { state.reloads++; }
    }};
    vm.runInNewContext(source, {document, window, FormData: TestFormData, URL, Error, TypeError,
        fetch: (url, options) => { state.calls.push({url, ...options}); return new Promise((resolve, reject) => { resolveFetch = resolve; rejectFetch = reject; }); }
    });
    return {rows, radios, apply, cancel, clear, selectAll, menu, message, form, bar, document, state, window,
        respond: response => resolveFetch(response), fail: () => rejectFetch(new TypeError('network error'))};
}
const ui = setup();
assert.equal(ui.apply.disabled, true);
ui.radios[0].checked = true;
await ui.form.trigger('change');
assert.equal(ui.apply.disabled, false);
ui.state.confirm = false;
await ui.form.trigger('submit');
assert.equal(ui.state.calls.length, 0);
assert.equal(ui.state.prompts[0], 'Change 2 orders to Processing?');
ui.state.confirm = true;
const pending = ui.form.trigger('submit');
await Promise.resolve();
assert.equal(ui.state.calls.length, 1);
await ui.form.trigger('submit');
assert.equal(ui.state.calls.length, 1, 'Double submit blocked');
assert.equal(ui.apply.disabled, true);
assert.equal(ui.clear.disabled, true);
assert.equal(ui.selectAll.disabled, true);
assert.ok(ui.rows.every(row => row.disabled));
assert.equal(ui.bar.attributes['aria-busy'], 'true');
const request = ui.state.calls[0];
assert.equal(request.method, 'POST');
assert.equal(request.credentials, 'same-origin');
assert.equal(new URL(request.url).searchParams.has('_token'), false);
assert.equal(new URL(request.url).searchParams.get('get'), 'ordersBulkStatus');
assert.equal(request.body.get('_token'), 'current-token');
assert.equal(request.body.get('status'), '2');
assert.deepEqual(request.body.entries.filter(entry => entry[0] === 'ids[]').map(entry => entry[1]), ['31', '28']);
ui.respond({ok: true, status: 200, json: async () => ({success: true, message: '2 updated'})});
await pending;
assert.equal(ui.state.reloads, 1);
assert.equal(ui.message.textContent, '2 updated');
for (const kind of ['403', '409', 'network', 'html']) {
    const failed = setup();
    failed.radios[0].checked = true;
    const waiting = failed.form.trigger('submit');
    await Promise.resolve();
    if (kind === 'network') failed.fail();
    else failed.respond({ok: false, status: Number(kind) || 200, json: async () => {
        if (kind === 'html') throw new SyntaxError('login HTML');
        return kind === '409' ? {message: 'Order unavailable'} : {error: 'CSRF token mismatch'};
    }});
    await waiting;
    assert.equal(failed.state.reloads, 0);
    assert.equal(failed.apply.disabled, false);
    assert.equal(failed.clear.disabled, false);
    assert.ok(failed.rows.every(row => !row.disabled));
    assert.deepEqual(failed.rows.map(row => row.checked), [true, false, true]);
    assert.equal(failed.message.classList.contains('is-error'), true);
    assert.equal(failed.message.textContent, kind === '403' ? 'Session expired' : kind === '409' ? 'Order unavailable' : 'Reload before retrying');
}
const canceled = setup();
canceled.radios[0].checked = true;
await canceled.cancel.trigger('click');
assert.equal(canceled.menu.open, false);
assert.equal(canceled.radios[0].checked, false);
assert.equal(canceled.apply.disabled, true);
canceled.rows.forEach(row => { row.checked = false; });
await canceled.document.trigger('scommerce:order-selection-changed');
assert.equal(canceled.message.hidden, true);
const dialogUI = setup();
dialogUI.radios[0].checked = true;
dialogUI.radios[0].dataset.statusLabel = '<img src=x onerror=alert(1)>';
dialogUI.state.confirm = null;
const dialogWaiting = dialogUI.form.trigger('submit');
assert.equal(dialogUI.state.calls.length, 0, 'No request until Alertify approval');
assert.equal(dialogUI.apply.disabled, true, 'Lock selection while confirmation is open');
await dialogUI.form.trigger('submit');
assert.equal(dialogUI.state.dialogs.length, 1, 'No duplicate confirmation');
const dialog = dialogUI.state.dialogs[0];
assert.equal(dialog.title, 'Change status');
assert.equal(dialog.settings.labels.ok, 'Change status');
assert.equal(dialog.settings.labels.cancel, 'Cancel');
assert.equal(dialog.settings.closableByDimmer, false);
assert.equal(dialog.content.textContent, 'Change 2 orders to <img src=x onerror=alert(1)>?', 'Status label is text, not HTML');
dialog.settings.onclose();
await dialogWaiting;
assert.equal(dialogUI.state.calls.length, 0, 'Closing the dialog cancels the operation');
assert.equal(dialogUI.apply.disabled, false);
assert.deepEqual(dialogUI.rows.map(row => row.checked), [true, false, true]);
const unavailable = setup();
unavailable.radios[0].checked = true;
unavailable.window.alertify = undefined;
await unavailable.form.trigger('submit');
assert.equal(unavailable.state.calls.length, 0, 'Fail closed if Alertify did not load');
assert.equal(unavailable.apply.disabled, false);
assert.equal(unavailable.message.hidden, false);
console.log('Bulk status UI checks passed: Alertify approval/cancel/close, safe text, duplicate dialog/submit guards, selection, CSRF, reload and errors');
