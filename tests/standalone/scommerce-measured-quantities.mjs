import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';

// The JS port of the measured-quantity arithmetic must match the PHP vectors.
const read = path => fs.readFileSync(new URL(path, import.meta.url), 'utf8');
const vectors = JSON.parse(read('./fixtures/measured-quantities.json'));
const context = {window: {}};
vm.createContext(context);
vm.runInContext(read('../../views/scripts/measuredQuantity.blade.php'), context);
const m = context.window.sCommerceMeasured;

for (const [price, quantity, factor, expected] of vectors.lineTotalMinor) {
    assert.equal(m.lineTotalMinor(BigInt(price), quantity, factor), expected, `lineTotalMinor ${price} × ${quantity} / ${factor}`);
}
for (const [input, unitFactor, expected] of vectors.parseAmount) {
    assert.equal(m.parseAmount(input, unitFactor), expected, `parseAmount ${JSON.stringify(input)}`);
}
for (const [input, expected] of vectors.scalePrice) {
    assert.equal(m.scalePrice(input), BigInt(expected), `scalePrice ${input}`);
}
for (const [lines, expected] of vectors.orderSum) {
    assert.equal(m.orderSum(lines), expected, `orderSum ${JSON.stringify(lines)}`);
}
assert.equal(m.toUnits(2530, 1000), '2.530');
assert.equal(m.toUnits(5, 1000), '0.005');
assert.equal(m.toUnits(330, 1), '330');
assert.equal(m.effective({measure: 'length', quantity: 2530, actual_quantity: 2553}), 2553);
assert.equal(m.effective({quantity: 2, actual_quantity: 9}), 2, 'Piece lines ignore actual quantities');
assert.throws(() => m.lineTotalMinor(-1n, 1, 1));

// Random property check against an independent BigInt formula.
for (let i = 0; i < 2000; i++) {
    const price = BigInt(Math.floor(Math.random() * 1e9));
    const quantity = Math.floor(Math.random() * 1e7);
    const factor = [1, 10, 100, 1000, 1000000][i % 5];
    const exact = price * BigInt(quantity);
    const divisor = BigInt(factor) * 1000n;
    const expected = exact / divisor + ((exact % divisor) * 2n >= divisor ? 1n : 0n);
    assert.equal(m.lineTotalMinor(price, quantity, factor), Number(expected));
}

// The manager order form and the storefront script use the helpers.
const order = read('../../views/orderTab.blade.php');
assert.ok(order.includes("@include('sCommerce::scripts.measuredQuantity')") && order.includes('sCommerceMeasured.lineSum(product)'));
assert.ok(order.includes('sCommerceMeasured.parseAmount('), 'Manager amounts are parsed to base units');
const cart = read('../../views/scripts/cart.blade.php');
assert.ok(cart.includes("form.append('amount', amount)") && cart.includes('input?.dataset.scMeasure'), 'Storefront posts amounts of measured inputs');

console.log('Measured quantity UI checks passed: integer line totals, amount parsing and manager sums match the PHP vectors');
