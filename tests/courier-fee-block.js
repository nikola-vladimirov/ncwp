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

// A price element as the script sees it: attributes and the font size it sets.
function node() {
  const attrs = {};
  const style = {};
  return {
    attrs, style: { setProperty(k, v) { style[k] = v; }, removeProperty(k) { delete style[k]; }, get: (k) => style[k] },
    setAttribute(k, v) { attrs[k] = v; },
    removeAttribute(k) { delete attrs[k]; },
  };
}
// The block checkout page: one price element per shipping option (kept between runs, like the real page) and the one in the order summary.
const prices = {};
const summary = node();

function run(packages) {
  let observer = null;
  packages.forEach((pkg) => pkg.shipping_rates.forEach((r) => { prices[r.rate_id] = prices[r.rate_id] || node(); }));
  const document = {
    body: {},
    createElement(tag) { assert.ok(!/^(style|script|link)$/i.test(tag), 'no style, script or link element is created'); return {}; },
    querySelector(selector) {
      const input = /^input[.]wc-block-components-radio-control__input[[]value="([^"]+)"[\]]$/.exec(selector);
      if (input) {
        return prices[input[1]] ? { closest: () => ({ querySelector: () => prices[input[1]] }) } : null;
      }
      return '.wc-block-components-totals-shipping .wc-block-components-totals-item__value' === selector ? summary : null;
    },
    querySelectorAll(selector) {
      assert.equal(selector, '[data-nashenski-text]');
      return Object.values(prices).concat(summary).filter((n) => 'data-nashenski-text' in n.attrs);
    },
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
}

const rate = (id, selected, meta) => ({ rate_id: id, selected, meta_data: Object.keys(meta).map((key) => ({ key, value: meta[key] })) });
const text = (n) => n.attrs['data-nashenski-text'];
const fee = '6,50 €, paid to the courier on delivery, not included in the total';
const econt = 'nashenski_econt:3';
const speedy = 'nashenski_speedy:4';

run([{ shipping_rates: [rate(econt, true, { nashenski_courier_fee: '6.50' }), rate(speedy, false, {})] }]);
check(text(prices[econt]) === fee && !('data-nashenski-price' in prices[econt].attrs), 'the option shows the price paid to the courier instead of "Free"');
check('0.875rem' === prices[econt].style.get('--nashenski-text-size'), 'the text gets a font size (the default one here: the page has no option label to measure)');
check(undefined === text(prices[speedy]), 'an option the shop collects keeps its own price');
check(text(summary) === fee, 'the order summary says the delivery is paid to the courier');

run([{ shipping_rates: [rate(econt, false, { nashenski_courier_fee: '6.50' }), rate(speedy, true, {})] }]);
check(undefined === text(summary) && undefined === summary.style.get('--nashenski-text-size'), 'another chosen option gives the order summary its own price back');

run([{ shipping_rates: [rate(econt, true, { nashenski_pending: 'yes' })] }]);
check('Complete the fields to calculate' === text(prices[econt]) && 'Complete the fields to calculate' === text(summary), 'a pending option still says "Complete the fields to calculate"');

// Cards and side by side layouts: short texts in the option, the long ones in the summary.
P.cardTexts = { pending: 'Price after address', toCourier: 'to the courier' };
run([{ shipping_rates: [rate(econt, true, { nashenski_courier_fee: '6.50' }), rate(speedy, false, { nashenski_pending: 'yes' })] }]);
check('6,50 €' === prices[econt].attrs['data-nashenski-price'] && ' to the courier' === text(prices[econt]), 'the card shows the price and a short "to the courier"');
check(text(summary) === fee, 'the order summary keeps the full sentence');
check('Price after address' === text(prices[speedy]) && !('data-nashenski-price' in prices[speedy].attrs), 'a card still waiting for the address says "Price after address"');

// R7: the fee is in the store currency, whatever currency the shopper switched to.
P.feeSymbol = 'лв.';
run([{ shipping_rates: [rate(econt, true, { nashenski_courier_fee: '6.50' })] }]);
check('6,50 лв.' === prices[econt].attrs['data-nashenski-price'] && 0 === text(summary).indexOf('6,50 лв., paid'), 'the fee shows the store currency symbol');
check(undefined === text(prices[speedy]), 'an option that is priced again gets its own price back');
delete P.feeSymbol;

// The texts are printed by the stylesheet, from the attributes.
const css = fs.readFileSync(path.join(__dirname, '../assets/css/checkout.css'), 'utf8');
check(css.includes('content: attr(data-nashenski-text)') && css.includes('content: attr(data-nashenski-price)'), 'checkout.css prints the text and the price from the attributes');

console.log('PASS: ' + checks + ' block checkout courier fee assertions');
