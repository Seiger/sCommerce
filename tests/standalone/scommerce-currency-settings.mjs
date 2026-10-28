import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// Currency cards in the settings: cyclic up/down buttons, header drag in both directions,
// no duplicate currencies and no debug output. Runs the real partial script on a tiny fake DOM.
const read = path => fs.readFileSync(new URL(path, import.meta.url), 'utf8');
const partial = read('../../views/partials/settingsCurrencyPriceConfiguration.blade.php');
const item = read('../../views/partials/settingsCurrencyItem.blade.php');
const script = partial.match(/<script>([\s\S]*?)<\/script>/)[1]
    .replace(/\{\{--[\s\S]*?--\}\}/g, '')
    .replace(/@json\(__\('[^']+'\)\)/g, '"already added"')
    .replace(/@lang\('[^']+'\)/g, 'label')
    .replace('{!!$moduleUrl!!}', 'module');
assert.ok(!/@lang|@json|\{\{|\{!!/.test(script), 'All Blade syntax is replaced before running the script');

function matchesSimple(el, token) {
    const parts = token.match(/^([a-z]*)((?:#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\])*)$/i);
    if (!parts) throw new Error('Unsupported selector ' + token);
    if (parts[1] && el.tag !== parts[1]) return false;
    for (const part of parts[2].match(/#[\w-]+|\.[\w-]+|\[[\w-]+(?:="[^"]*")?\]/g) ?? []) {
        if (part[0] === '#' && el.id !== part.slice(1)) return false;
        if (part[0] === '.' && !el.classList.contains(part.slice(1))) return false;
        if (part[0] === '[') {
            const [, name, value] = part.match(/^\[([\w-]+)(?:="([^"]*)")?\]$/);
            if (!(name in el.attributes) || (value !== undefined && el.attributes[name] !== value)) return false;
        }
    }
    return true;
}
function matches(el, selector) {
    return selector.split(',').some(alternative => {
        const chain = alternative.trim().split(/\s+/);
        if (!matchesSimple(el, chain.pop())) return false;
        let node = el.parentNode;
        while (chain.length && node) {
            if (matchesSimple(node, chain.at(-1))) chain.pop();
            node = node.parentNode;
        }
        return chain.length === 0;
    });
}
class Element {
    constructor(tag, {id = '', classes = [], attributes = {}, value = ''} = {}) {
        Object.assign(this, {tag, id, attributes, value, children: [], parentNode: null, disabled: false});
        const set = new Set(classes);
        this.classList = {add: c => set.add(c), remove: c => set.delete(c), contains: c => set.has(c)};
        this.dataset = Object.fromEntries(Object.entries(attributes).filter(([k]) => k.startsWith('data-'))
            .map(([k, v]) => [k.slice(5).replace(/-(\w)/g, (_, c) => c.toUpperCase()), v]));
    }
    append(...nodes) { for (const node of nodes) this.insertBefore(node, null); return this; }
    insertBefore(node, before) {
        if (node.parentNode) node.parentNode.children.splice(node.parentNode.children.indexOf(node), 1);
        const index = before ? this.children.indexOf(before) : this.children.length;
        assert.ok(index >= 0, 'insertBefore reference must be a child');
        this.children.splice(index, 0, node);
        node.parentNode = this;
        return node;
    }
    get nextElementSibling() { const s = this.parentNode.children; return s[s.indexOf(this) + 1] ?? null; }
    closest(selector) { for (let n = this; n; n = n.parentNode) if (matches(n, selector)) return n; return null; }
    querySelectorAll(selector) {
        const found = [];
        const walk = node => node.children.forEach(child => { if (matches(child, selector)) found.push(child); walk(child); });
        walk(this);
        return found;
    }
    querySelector(selector) { return this.querySelectorAll(selector)[0] ?? null; }
    // Fake layout: items are stacked 600px cards, or 40px headers while the list is sorting.
    getBoundingClientRect() {
        const card = this.closest('.scommerce-currency-item');
        const list = card.parentNode;
        let top = 100 - page.scrollY;
        for (const other of list.children) {
            const height = list.classList.contains('is-sorting') ? 40 : 600;
            if (other === card) return {top, height: this === card ? height : 40};
            top += height;
        }
    }
}

const page = {scrollY: 0, listeners: {}};
const root = new Element('body');
const list = new Element('div', {id: 'available_currencies', classes: ['scommerce-currency-list']});
root.append(list);
function currency(code) {
    const wrapper = new Element('div', {classes: ['col-sm-12', 'scommerce-currency-item']});
    const header = new Element('div', {classes: ['card-header', 'scommerce-currency-handle']});
    const up = new Element('button', {attributes: {'data-currency-move': '-1'}});
    const down = new Element('button', {attributes: {'data-currency-move': '1'}});
    up.append(new Element('i'));
    header.append(up, down, new Element('span', {classes: ['close-icon']}));
    wrapper.append(new Element('input', {attributes: {name: 'basic__available_currencies[]'}, value: code}), header);
    Object.assign(wrapper, {code, header, up, down});
    return wrapper;
}
const codes = () => list.children.map(child => child.code);
const reset = (...items) => { list.children = []; list.append(...items.map(currency)); };

const draftOptions = ['', 'UAH', 'EUR', 'USD'].map(value => new Element('option', {value}));
const ajsContent = new Element('div');
const ajsSelect = new Element('select');
const alerts = [];
const fetches = [];
let confirmOk;
const context = {
    documentDirty: false,
    window: {innerHeight: 900, scrollBy: (x, y) => { page.scrollY += y; }},
    console,
    document: {
        addEventListener: (type, handler) => (page.listeners[type] ??= []).push(handler),
        removeEventListener: (type, handler) => { page.listeners[type] = page.listeners[type].filter(h => h !== handler); },
        getElementById: id => (id === 'available_currencies' ? list : null),
        querySelector: selector => ({
            '.draft-currencies': {cloneNode: () => ({querySelectorAll: () => draftOptions, innerHTML: 'draft'})},
            '.ajs-content select': ajsSelect,
            '.ajs-content': Object.assign(ajsContent, {replaceChildren() {}}),
            '.ajs-ok': new Element('a'),
            '.alertify ': new Element('div'),
        })[selector],
    },
    alertify: {
        confirm: (title, body, ok) => { confirmOk = ok; const dialog = {set: () => dialog, setContent: () => dialog}; return dialog; },
        warning: message => alerts.push(message),
        alert: () => {},
    },
    fetch: (url, request) => {
        fetches.push(request.body);
        const code = decodeURIComponent(request.body.slice(5));
        return Promise.resolve({json: () => ({success: 1, view: code})});
    },
};
// Added cards arrive as HTML; the fake inserts a card for the returned currency code.
list.insertAdjacentHTML = (position, code) => list.append(currency(code));
context.window.document = context.document;
vm.createContext(context);
vm.runInContext(script, context);
const dispatch = (type, event) => (page.listeners[type] ?? []).slice().forEach(handler => handler({preventDefault() {}, button: 0, ...event}));

// 1. Up/down buttons move one step and wrap around at both ends.
reset('UAH', 'EUR', 'USD');
const click = (code, direction) => dispatch('click', {target: list.children.find(c => c.code === code)[direction]});
click('UAH', 'up');
assert.deepEqual(codes(), ['EUR', 'USD', 'UAH'], 'The first currency moved up becomes the last');
click('UAH', 'down');
assert.deepEqual(codes(), ['UAH', 'EUR', 'USD'], 'The last currency moved down becomes the first');
click('EUR', 'down');
assert.deepEqual(codes(), ['UAH', 'USD', 'EUR']);
dispatch('click', {target: list.children.find(c => c.code === 'USD').up.children[0]});
assert.deepEqual(codes(), ['USD', 'UAH', 'EUR'], 'A click on the icon inside the button also moves');
assert.equal(context.documentDirty, true);
reset('UAH');
click('UAH', 'down');
assert.deepEqual(codes(), ['UAH'], 'A single currency stays in place');

// 2. Dragging a header passes other items in both directions and keeps the header under the pointer.
function drag(code, steps) {
    const card = list.children.find(c => c.code === code);
    const start = card.header.getBoundingClientRect().top + 10;
    dispatch('pointerdown', {target: card.header, clientY: start});
    let y = start;
    for (const step of steps) {
        y += step;
        dispatch('pointermove', {clientY: y});
        if (list.classList.contains('is-sorting')) {
            const top = card.header.getBoundingClientRect().top;
            assert.ok(Math.abs(y - top) <= 50, `The dragged header follows the pointer (${y} vs ${top})`);
        }
    }
    dispatch('pointerup', {clientY: y});
    assert.ok(!list.classList.contains('is-sorting') && !card.classList.contains('is-dragging'), 'Cards expand after the drop');
}
reset('UAH', 'EUR', 'USD', 'PLN');
page.scrollY = 0;
drag('UAH', [10, 30, 30, 30]);
assert.deepEqual(codes(), ['EUR', 'USD', 'UAH', 'PLN'], 'The upper item is dragged down');
drag('PLN', [-10, -30, -30, -30, -30]);
assert.deepEqual(codes(), ['PLN', 'EUR', 'USD', 'UAH'], 'The lower item is dragged up to the top');
drag('USD', [3]);
assert.deepEqual(codes(), ['PLN', 'EUR', 'USD', 'UAH'], 'A click on the header without moving does not reorder');
assert.ok(!list.classList.contains('is-sorting'), 'A click does not collapse the cards');
const before = codes();
dispatch('pointerdown', {target: list.children[0].up, clientY: 110});
dispatch('pointermove', {clientY: 900});
dispatch('pointerup', {clientY: 900});
assert.deepEqual(codes(), before, 'Pressing a move button does not start a drag');
assert.equal(page.listeners.pointermove?.length ?? 0, 0, 'Drag listeners are removed after the drop');

// 3. Adding: currencies already listed are disabled and refused; a late duplicate response is ignored.
reset('UAH', 'EUR');
context.addCurrencyItem('available_currencies');
assert.deepEqual(draftOptions.map(o => o.disabled), [false, true, true, false], 'Listed currencies cannot be picked again');
ajsSelect.value = 'EUR';
confirmOk();
assert.deepEqual([fetches.length, alerts], [0, ['already added']], 'A listed currency is refused without a request');
ajsSelect.value = 'USD';
confirmOk();
confirmOk();
await new Promise(resolve => setTimeout(resolve, 0));
assert.deepEqual(codes(), ['UAH', 'EUR', 'USD'], 'Two pending additions of one currency insert it once');
ajsSelect.value = '';
confirmOk();
assert.equal(fetches.length, 2, 'An empty choice sends nothing');

// 4. Markup and debug output.
assert.ok(item.includes("deleteItem(this.closest('.scommerce-currency-item'))"), 'Deleting removes the whole item, not just its card');
assert.ok(!/class="[^"]*\bsortable\b/.test(partial.match(/<div id="available_currencies"[^>]*>/)[0]), 'The generic evo.sortable does not bind the currency cards');
assert.ok(!/console\.log\(/.test(read('../../views/index.blade.php')), 'Manager clicks are not logged to the console');

console.log('Currency settings UI checks passed: cyclic buttons, drag in both directions, duplicate guard, no click logging');
