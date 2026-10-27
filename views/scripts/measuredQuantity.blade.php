// Integer arithmetic of measured quantities, ported from Seiger\sCommerce\Services\MeasuredQuantity.
// Plain JavaScript without Blade directives: included inside script blocks and loaded by tests.
window.sCommerceMeasured = window.sCommerceMeasured || (function () {
    // Price in 1e-5 units as a BigInt; strings are parsed exactly, like scalePrice() in PHP.
    function scalePrice(price) {
        const text = String(price).trim();
        const match = /^(\d{1,13})(?:\.(\d*))?$/.exec(text);
        if (match) {
            const fraction = (match[2] || '').slice(0, 6).padEnd(6, '0');
            return BigInt(match[1]) * 100000n + BigInt(fraction.slice(0, 5)) + (Number(fraction[5]) >= 5 ? 1n : 0n);
        }
        const number = Number(price);
        if (!Number.isFinite(number) || number < 0) {
            throw new Error('Prices must be non-negative numbers.');
        }
        return BigInt(Math.round(number * 100000));
    }

    // round_half_up(price × quantity ÷ factor) in minor units, as a Number.
    function lineTotalMinor(priceScaled, quantity, factor) {
        const p = BigInt(priceScaled), q = BigInt(quantity), divisor = BigInt(factor) * 1000n;
        if (p < 0n || q < 0n || divisor < 1000n) {
            throw new Error('Line totals need a non-negative price and quantity and a positive factor.');
        }
        return Number((2n * p * q + divisor) / (2n * divisor));
    }

    // Amount in the price unit ("2.53" metres) to base units (2530), or null when invalid.
    function parseAmount(input, unitFactor) {
        const match = /^(\d{1,15})(?:\.(\d{1,15}))?$/.exec(String(input).trim());
        if (!match) {
            return null;
        }
        const decimals = String(unitFactor).length - 1;
        const fraction = (match[2] || '').replace(/0+$/, '');
        if (fraction.length > decimals) {
            return null;
        }
        return Number(BigInt(match[1]) * BigInt(unitFactor) + BigInt(fraction.padEnd(decimals, '0') || '0'));
    }

    // Exact decimal string of a quantity in units of `factor` base units: (2530, 1000) -> "2.530".
    function toUnits(quantity, factor) {
        const decimals = String(factor).length - 1;
        if (decimals === 0) {
            return String(quantity);
        }
        const digits = String(Math.abs(quantity)).padStart(decimals + 1, '0');
        return (quantity < 0 ? '-' : '') + digits.slice(0, -decimals) + '.' + digits.slice(-decimals);
    }

    function numericPrice(line) {
        for (const key of ['priceNumber', 'priceAsFloat']) {
            if (line[key] !== undefined && line[key] !== null && line[key] !== '' && !Number.isNaN(Number(line[key]))) {
                return line[key];
            }
        }
        return parseFloat(String(line.price ?? '0').replace(/[^\d.,]/g, '').replace(',', '.')) || 0;
    }

    function effective(line) {
        const actual = parseInt(line.actual_quantity, 10);
        return line.measure && actual > 0 ? actual : (parseInt(line.quantity, 10) || 0);
    }

    // Line total like MeasuredQuantity::lineSum(): measured lines exact, piece lines price × quantity.
    function lineSum(line) {
        if (!line.measure) {
            return (parseFloat(numericPrice(line)) || 0) * (parseInt(line.quantity, 10) || 0);
        }
        return lineTotalMinor(scalePrice(numericPrice(line)), effective(line), line.factor || 1) / 100;
    }

    function orderSum(lines) {
        return Math.round(lines.reduce((total, line) => total + lineSum(line), 0) * 100) / 100;
    }

    return {scalePrice, lineTotalMinor, parseAmount, toUnits, effective, lineSum, orderSum};
})();
