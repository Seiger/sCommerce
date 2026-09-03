// Exercise both shipped bulk handlers with synthetic DOM/fetch/download adapters.
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import {Blob} from 'node:buffer';
class Headers {
    constructor(values) { this.values = values; }
    get(key) { return this.values[key] ?? null; }
}
class FormData {
    constructor(form) { this.values = form ? [['_token', 'form-token'], ['status', '6']] : []; }
    append(key, value) { this.values.push([key, value]); }
    set(key, value) { this.values = this.values.filter(entry => entry[0] !== key); this.append(key, value); }
    get(key) { return this.getAll(key)[0] ?? null; }
    getAll(key) { return this.values.filter(entry => entry[0] === key).map(entry => entry[1]); }
}
const sources = ['ordersBulkStatus', 'ordersBulkExport', 'ordersBulkMenu'].map(name => fs.readFileSync(new URL(`../../views/scripts/${name}.blade.php`, import.meta.url), 'utf8').replace(/^<script>\s*/, '').replace(/\s*<\/script>\s*$/, ''));
class Control {
    constructor(props = {}) {
        Object.assign(this, {checked: false, disabled: false, hidden: true, dataset: {}, listeners: {}, attributes: {}}, props);
        const classes = new Set();
        this.classList = {add: x => classes.add(x), remove: x => classes.delete(x), contains: x => classes.has(x)};
    }
    addEventListener(name, handler) { (this.listeners[name] ||= []).push(handler); }
    async trigger(name, event = {}) { for (const fn of this.listeners[name] || []) await fn({preventDefault() {}, ...event}); }
    setAttribute(name, value) { this.attributes[name] = value; }
    getAttribute(name) { return this.attributes[name] ?? null; }
    focus() {}
}
function setup() {
    const state = {requests: [], downloads: [], revoked: [], dialogs: [], timers: [], reloads: 0, blobs: []};
    const rows = [new Control({value: '31', checked: true}), new Control({value: '30'}), new Control({value: '28', checked: true})];
    const radio = new Control({checked: true, value: '6', dataset: {statusLabel: 'Shipped'}});
    const apply = new Control(), cancel = new Control(), clear = new Control(), selectAll = new Control(), more = new Control({disabled: true}), message = new Control();
    const button = new Control({dataset: {url: 'https://example.test/manager/?a=112&id=module&_token=old&get=ordersBulkExport', pending: 'Preparing', success: 'Download started', error: 'Export failed', sessionError: 'Session expired'}});
    const bar = new Control();
    const paid = new Control({textContent:'Mark paid', dataset:{bulkAction:'paid', confirm:'Received :count payments?'}});
    const print = new Control({textContent:'Print', dataset:{bulkAction:'print'}});
    const remove = new Control({textContent:'Delete', dataset:{bulkAction:'delete', confirm:'Delete :count orders?'}});
    const bulkMenu = new Control({dataset:{url:'https://example.test/?get=ordersBulkMenu&_token=old', error:'Menu failed', sessionError:'Session expired', pending:'Processing', popupError:'Allow popups', cancel:'Cancel'}});
    bulkMenu.querySelector = () => new Control();
    bulkMenu.querySelectorAll = () => [paid, print, remove];
    bulkMenu.contains = () => false;
    bar.querySelectorAll = () => [button, radio, apply, cancel, clear, more, paid, print, remove];
    const form = new Control({action: 'https://example.test/manager/?get=ordersBulkStatus', dataset: {confirm: 'Change :count to :status?', error: 'Status failed', pending: 'Updating'}});
    form.querySelector = selector => selector === '[type="submit"]' ? apply : cancel;
    form.querySelectorAll = selector => selector === '[name="status"]' ? [radio] : [apply, cancel];
    form.reset = () => { radio.checked = false; };
    const menu = new Control({open: true});
    menu.contains = () => false;
    menu.querySelector = () => new Control();
    const document = new Control();
    document.dispatchEvent = event => document.trigger(event.type);
    document.body = {appendChild() {}};
    document.createElement = name => {
        const element = new Control();
        if (name === 'a') {
            element.click = () => state.downloads.push({href: element.href, filename: element.download});
            element.remove = () => { state.removed = true; };
        }
        return element;
    };
    const controls = {'[data-orders-bulk-menu]': bulkMenu, '[data-orders-bulk-form]': form, '[data-orders-bulk-status]': menu, '[data-orders-bulk-bar]': bar,
        '[data-orders-bulk-message]': message, '[data-orders-bulk-export]': button, '[data-orders-select-all]': selectAll,
        '[data-orders-selection-clear]': clear, 'meta[name="csrf-token"]': {content: 'current-token'},
        '[data-orders-bulk-form] [name="_token"]': {value: 'form-token'}};
    document.querySelector = selector => controls[selector];
    document.querySelectorAll = selector => selector === '[data-order-select]' ? rows : [];
    class TestURL extends URL {
        static createObjectURL(blob) { state.blobs.push(blob); return 'blob:private'; }
        static revokeObjectURL(url) { state.revoked.push(url); }
    }
    const window = {location: {href: 'https://example.test/manager/?status=1&page=2', reload: () => state.reloads++},
        setTimeout: fn => state.timers.push(fn), alertify: {confirm: (...args) => { state.dialogs.push(args); return {set(settings) { args.settings = settings; }}; }},
        open: () => {
            state.popup = {closed:false, opener:{}, document:{body:{}, open(){}, write(html){state.printHtml = html;}, close(){}}, focus(){}, close(){this.closed=true;}};
            return state.popup;
        }};
    let resolve, reject;
    const context = vm.createContext({document, window, URL: TestURL, FormData, Error, TypeError,
        CustomEvent: class { constructor(type) { this.type = type; } },
        fetch: (url, options) => { state.requests.push({url, ...options}); return new Promise((yes, no) => { resolve = yes; reject = no; }); }});
    sources.forEach(source => vm.runInContext(source, context));
    return {state, rows, radio, apply, clear, more, selectAll, button, bar, form, menu, bulkMenu, paid, print, remove, message, controls, window,
        respond: response => resolve(response), fail: () => reject(new TypeError('network'))};
}
const csvResponse = () => ({ok: true, status: 200, headers: new Headers({'Content-Type': 'text/csv; charset=UTF-8', 'Content-Disposition': 'attachment; filename="sCommerce_orders_2026-09-03_12-00-00.csv"'}), blob: async () => new Blob(['synthetic CSV'])});
const ui = setup();
const waiting = ui.button.trigger('click');
assert.equal(ui.state.requests.length, 1);
await ui.button.trigger('click');
await ui.form.trigger('submit');
assert.equal(ui.state.requests.length, 1, 'Duplicate export/status blocked');
assert.equal(ui.state.dialogs.length, 0, 'Status confirmation cannot open while exporting');
assert.ok(ui.rows.every(row => row.disabled));
assert.ok(ui.apply.disabled && ui.button.disabled && ui.clear.disabled && ui.selectAll.disabled);
assert.equal(ui.menu.open, false);
const request = ui.state.requests[0];
assert.equal(request.method, 'POST');
assert.equal(request.credentials, 'same-origin');
assert.equal(new URL(request.url).searchParams.has('_token'), false);
assert.equal(request.body.get('_token'), 'current-token');
assert.deepEqual(request.body.getAll('ids[]'), ['31', '28']);
ui.respond(csvResponse());
await waiting;
assert.deepEqual(ui.state.downloads, [{href: 'blob:private', filename: 'sCommerce_orders_2026-09-03_12-00-00.csv'}]);
assert.equal(ui.state.removed, true);
ui.state.timers.forEach(fn => fn());
assert.deepEqual(ui.state.revoked, ['blob:private']);
assert.equal(ui.state.reloads, 0);
assert.equal(ui.window.location.href, 'https://example.test/manager/?status=1&page=2');
assert.deepEqual(ui.rows.map(row => row.checked), [true, false, true]);
assert.ok(!ui.apply.disabled && !ui.button.disabled && !ui.clear.disabled && !ui.selectAll.disabled);
assert.equal(ui.more.disabled, true, 'Pre-disabled controls remain disabled');
assert.equal(ui.message.textContent, 'Download started');
for (const kind of ['403', '409', '500', 'html', 'network', 'empty']) {
    const failed = setup();
    const pending = failed.button.trigger('click');
    if (kind === 'network') failed.fail();
    else if (kind === 'empty') failed.respond({...csvResponse(), blob: async () => new Blob([])});
    else failed.respond({ok: kind === 'html', status: Number(kind) || 200, headers: new Headers({'Content-Type': kind === 'html' ? 'text/html' : 'application/json'}), json: async () => {
        if (kind === 'html') throw new SyntaxError('login HTML');
        return kind === '409' ? {message: 'Order missing'} : {error: 'failure'};
    }});
    await pending;
    assert.equal(failed.state.downloads.length, 0, 'Never download errors/login HTML/empty CSV');
    assert.equal(failed.state.blobs.length, 0);
    assert.ok(failed.message.classList.contains('is-error'));
    assert.equal(failed.message.textContent, kind === '403' ? 'Session expired' : kind === '409' ? 'Order missing' : 'Export failed');
    assert.ok(!failed.button.disabled && !failed.apply.disabled && !failed.clear.disabled);
    assert.deepEqual(failed.rows.map(row => row.checked), [true, false, true]);
}
const empty = setup();
empty.rows.forEach(row => { row.checked = false; });
await empty.button.trigger('click');
assert.equal(empty.state.requests.length, 0, 'Empty selection cannot export');
const fallback = setup();
delete fallback.controls['meta[name="csrf-token"]'];
const fallbackPending = fallback.button.trigger('click');
assert.equal(fallback.state.requests[0].body.get('_token'), 'form-token');
fallback.respond(csvResponse()); await fallbackPending;
const status = setup();
const statusPending = status.form.trigger('submit');
assert.equal(status.state.dialogs.length, 1);
assert.equal(status.button.disabled, true, 'Status confirmation locks the export button');
await status.button.trigger('click');
assert.equal(status.state.requests.length, 0, 'Export respects a status operation lock');
status.state.dialogs[0][3]();
await statusPending;
assert.equal(status.button.disabled, false, 'Canceling status confirmation unlocks export');
console.log('CSV UI checks passed: selection, CSRF, download, cleanup, retry, error handling and shared status lock');
for (const action of ['paid','remove']) {
    const bulk = setup();
    const pending = bulk[action].trigger('click');
    assert.equal(bulk.state.requests.length, 0, 'Confirmation required');
    assert.equal(bulk.state.dialogs.length, 1);
    assert.ok(bulk.button.disabled && bulk.apply.disabled && bulk.clear.disabled);
    await bulk[action].trigger('click'); await bulk.button.trigger('click'); await bulk.form.trigger('submit');
    assert.equal(bulk.state.dialogs.length, 1, 'No concurrent bulk actions');
    bulk.state.dialogs[0][2]();
    await Promise.resolve();
    assert.equal(bulk.state.requests.length, 1);
    const req = bulk.state.requests[0];
    assert.equal(req.body.get('action'), action === 'remove' ? 'delete' : 'paid');
    assert.equal(req.body.get('_token'), 'current-token');
    assert.deepEqual(req.body.getAll('ids[]'), ['31','28']);
    assert.equal(new URL(req.url).searchParams.has('_token'), false);
    bulk.respond({ok:true, json:async()=>({success:true,message:'Done'})});
    await pending;
    assert.equal(bulk.state.reloads, 1);
}
for (const close of [false,true]) {
    const canceled = setup(); const pending = canceled.paid.trigger('click');
    if (close) canceled.state.dialogs[0].settings.onclose(); else canceled.state.dialogs[0][3]();
    await pending;
    assert.equal(canceled.state.requests.length,0);
    assert.equal(canceled.button.disabled,false);
}
for (const kind of ['403','422','network','html']) {
    const failed = setup(); const pending = failed.remove.trigger('click');
    failed.state.dialogs[0][2](); await Promise.resolve();
    if (kind === 'network') failed.fail();
    else failed.respond({ok:kind==='html', status:Number(kind)||200, json:async()=>{
        if(kind==='html') throw new SyntaxError('login');
        return kind==='422'?{message:'Individual review needed'}:{error:'CSRF'};
    }});
    await pending;
    assert.equal(failed.state.reloads,0);
    assert.ok(failed.message.classList.contains('is-error'));
    assert.deepEqual(failed.rows.map(r=>r.checked),[true,false,true]);
    assert.equal(failed.button.disabled,false);
}
const printable = setup(); const printPending = printable.print.trigger('click');
assert.ok(printable.state.popup, 'Popup opened before fetch finishes');
assert.equal(printable.state.popup.opener,null);
printable.respond({ok:true,json:async()=>({success:true,html:'<html><body>Print preview</body></html>'})});
await printPending;
assert.ok(printable.state.printHtml.includes('Print preview'));
assert.equal(printable.state.reloads,0);
assert.deepEqual(printable.rows.map(r=>r.checked),[true,false,true]);
const blocked = setup(); blocked.window.open=()=>null;
await blocked.print.trigger('click');
assert.equal(blocked.state.requests.length,0);
assert.equal(blocked.message.textContent,'Allow popups');
const badPrint = setup(); const badPending = badPrint.print.trigger('click');
badPrint.fail(); await badPending;
assert.equal(badPrint.state.popup.closed,true);
const noAlertify = setup(); noAlertify.window.alertify=null;
await noAlertify.paid.trigger('click');
assert.equal(noAlertify.state.requests.length,0);
console.log('Bulk menu UI checks passed: Alertify, shared locks, paid/delete POST, cancel/errors and print popup lifecycle');
