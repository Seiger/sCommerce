import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

const template = fs.readFileSync(new URL('../../views/reviewTab.blade.php', import.meta.url), 'utf8');
const script = template.match(/<script>([\s\S]*?)<\/script>/)[1];
for (const approved of [true, false]) {
    let prevented = false;
    const button = {dataset: {confirm: "Remove customer's photo?"}, form: {elements: {image: {value: 'photo.jpg'}}}};
    const context = {confirm: message => { assert.equal(message, button.dataset.confirm); return approved; }};
    vm.createContext(context);
    vm.runInContext(script, context);
    // target may be the nested icon; only currentTarget is reliably the button.
    context.removeImage({target: {}, currentTarget: button, preventDefault() { prevented = true; }});
    assert.equal(button.form.elements.image.value, approved ? '' : 'photo.jpg');
    assert.equal(prevented, !approved);
}
console.log('Review photo UI checks passed: nested icon click, approval and cancellation');
