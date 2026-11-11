/* Runs the shipped assets/js/pickers-block.js: a delivery the customer pays the courier shows its price and the info text in place of "Free" in the block checkout. Run: node tests/courier-fee-block.js */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const read = (file) => fs.readFileSync(path.join(__dirname, '../assets/js/', file), 'utf8');
let checks = 0;
function check(condition, message) { checks++; assert.ok(condition, message); }

// The shared picker script gives the money format.
const window = { wp: { element: { createElement() {} } }, nashenskiPickers: { currency: { symbol: '€', format: '%2$s %1$s', decimals: 2, decimal: ',', thousand: ' ' } } };
window.window = window;
vm.runInNewContext(read('pickers.js'), { window, wp: window.wp, document: {}, Object, Number, Math, String, Array, RegExp, parseFloat });
const P = Object.assign(window.nashenskiPickers, {
  Picker() {},
  decorateRates() {},
  pending: 'Complete the fields to calculate',
  courierFee: '%s, paid to the courier on delivery, not included in the total',
});

function run(packages) {
  let observer = null;
  let style = null;
  const document = {
    head: { appendChild(node) { style = node; } },
    body: {},
    createElement() { return {}; },
    getElementById() { return style; },
    querySelector() { return null; },
  };
  const wp = {
    element: { createElement() {}, useLayoutEffect() {}, createPortal() {} },
    plugins: { registerPlugin() {} },
    data: { useSelect() {}, select: () => ({ getShippingRates: () => packages }) },
  };
  const wc = { blocksCheckout: { ExperimentalOrderShippingPackages() {}, extensionCartUpdate() {} } };
  const win = {
    nashenskiPickers: P,
    wc,
    getComputedStyle: () => ({ fontSize: '14px' }),
    setTimeout: (fn) => fn(),
    clearTimeout() {},
  };
  vm.runInNewContext(read('pickers-block.js'), {
    window: win, wc, wp, document, Object, String, Array, parseFloat,
    MutationObserver: class { constructor(fn) { observer = fn; } observe() {} },
  });
  observer();
  return style ? style.textContent : '';
}

const rate = (id, selected, meta) => ({ rate_id: id, selected, meta_data: Object.keys(meta).map((key) => ({ key, value: meta[key] })) });
const fee = '"6,50 €, paid to the courier on delivery, not included in the total"';

let css = run([{ shipping_rates: [rate('nashenski_econt:3', true, { nashenski_courier_fee: '6.50' }), rate('nashenski_speedy:4', false, {})] }]);
check(css.includes('input[value="nashenski_econt:3"]') && css.includes('content:' + fee), 'the option shows the price paid to the courier instead of "Free"');
check(!css.includes('nashenski_speedy:4'), 'an option the shop collects keeps its own price');
check(css.includes('.wc-block-components-totals-shipping .wc-block-components-totals-item__value::after{content:' + fee), 'the order summary says the delivery is paid to the courier');

css = run([{ shipping_rates: [rate('nashenski_econt:3', false, { nashenski_courier_fee: '6.50' }), rate('nashenski_speedy:4', true, {})] }]);
check(!css.includes('totals-item__value::after'), 'another chosen option leaves the order summary alone');

css = run([{ shipping_rates: [rate('nashenski_econt:3', true, { nashenski_pending: 'yes' })] }]);
check(css.includes('Complete the fields to calculate') && !css.includes('paid to the courier'), 'a pending option still says "Complete the fields to calculate"');

// Cards and side by side layouts: short texts in the option, the long ones in the summary.
P.cardTexts = { pending: 'Price after address', toCourier: 'to the courier' };
css = run([{ shipping_rates: [rate('nashenski_econt:3', true, { nashenski_courier_fee: '6.50' }), rate('nashenski_speedy:4', false, { nashenski_pending: 'yes' })] }]);
check(css.includes('secondary-label::before{content:"6,50 €"') && css.includes('::after{content:" to the courier"'), 'the card shows the price and a short "to the courier"');
check(css.includes('totals-item__value::after{content:' + fee), 'the order summary keeps the full sentence');
check(css.includes('input[value="nashenski_speedy:4"]) .wc-block-components-radio-control__secondary-label::after{content:"Price after address"'), 'a card still waiting for the address says "Price after address"');

// R7: the fee is in the store currency, whatever currency the shopper switched to.
P.feeSymbol = 'лв.';
css = run([{ shipping_rates: [rate('nashenski_econt:3', true, { nashenski_courier_fee: '6.50' })] }]);
check(css.includes('secondary-label::before{content:"6,50 лв."') && css.includes('totals-item__value::after{content:"6,50 лв., paid'), 'the fee shows the store currency symbol');
delete P.feeSymbol;

console.log('PASS: ' + checks + ' block checkout courier fee assertions');
