import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const source = fs.readFileSync(new URL('../../views/scripts/orderRepeat.blade.php', import.meta.url), 'utf8')
    .replace(/const fallback = @js\([^\n]+\);/, 'const fallback = "Save failed";');
function setup(fetch) {
    let handler;
    const state = {requests: [], destinations: [], errors: [], warnings: []};
    const feedback = {hidden: true, textContent: ''};
    const attrs = new Map();
    const form = {
        action: 'https://shop.test/manager/?a=112&_token=stale&get=orderRepeatSave',
        querySelector: () => feedback,
        setAttribute: (key, value) => attrs.set(key, value),
        removeAttribute: key => attrs.delete(key),
    };
    class FormData {
        constructor() { this.values = new Map([['_token', 'form-token'], ['repeat_token', 'same-draft']]); }
        get(key) { return this.values.get(key); }
        set(key, value) { this.values.set(key, value); }
    }
    const context = {
        $: () => ({on: (name, fn) => { assert.equal(name, 'submit'); handler = fn; }}),
        FormData, URL, documentDirty: true,
        document: {
            querySelector: () => ({content: 'fresh-token'}),
            createElement: () => ({textContent: '', get innerHTML() { return this.textContent.replaceAll('<', '&lt;').replaceAll('>', '&gt;'); }}),
        },
        window: {
            location: {href: 'https://shop.test/manager/', origin: 'https://shop.test', assign: url => state.destinations.push(url)},
            alertify: {error: text => state.errors.push(text), warning: text => state.warnings.push(text)},
        },
        fetch: async (url, options) => { state.requests.push({url, options}); return fetch(); },
    };
    vm.createContext(context);
    vm.runInContext(source, context);
    const submit = () => handler.call(form, {preventDefault() {}});
    return {state, context, submit, feedback, attrs};
}
let resolve;
const pending = new Promise(done => { resolve = done; });
const success = setup(() => pending);
const first = success.submit();
await success.submit();
assert.equal(success.state.requests.length, 1, 'Concurrent submit blocked');
assert.equal(success.state.requests[0].options.body.get('_token'), 'fresh-token');
assert.equal(success.state.requests[0].options.body.get('repeat_token'), 'same-draft');
assert.equal(new URL(success.state.requests[0].url).searchParams.has('_token'), false);
resolve({ok: true, json: async () => ({success: true, url: '?a=112&get=order&i=50'})});
await first;
assert.equal(success.state.destinations.length, 1);
assert.equal(success.context.documentDirty, false);
for (const response of [
    {ok: false, json: async () => ({message: '<img src=x onerror=alert(1)>'})},
    {ok: true, json: async () => { throw new Error('Invalid JSON'); }},
    {ok: true, json: async () => ({success: true, url: 'https://other.test/'})},
]) {
    const failure = setup(async () => response);
    await failure.submit();
    assert.equal(failure.state.destinations.length, 0);
    assert.equal(failure.feedback.hidden, false);
    assert.equal(failure.context.documentDirty, true);
    assert.equal(failure.attrs.has('aria-busy'), false);
    assert.equal(failure.state.errors[0].includes('<img'), false);
    await failure.submit();
    assert.equal(failure.state.requests.length, 2, 'Retry enabled after failure');
}
console.log('Repeat UI checks passed: CSRF, duplicate submit, redirect, errors, safe Alertify and retry');
