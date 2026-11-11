/* Runs the shipped assets/js/pickers.js: the money format of the free shipping texts and the lowest free shipping total. Run: node tests/free-shipping-progress.js */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(path.join(__dirname, '../assets/js/pickers.js'), 'utf8');
let checks = 0;
function check(actual, expected, message) { checks++; assert.equal(actual, expected, message); }

function boot(currency) {
  const window = { wp: { element: { createElement() {} } }, nashenskiPickers: { currency } };
  window.window = window;
  vm.runInNewContext(source, { window, wp: window.wp, document: {}, Object, Number, Math, String, Array, RegExp, parseFloat });
  return window.nashenskiPickers;
}

// Bulgarian store: euro after the amount with a non-breaking space, comma decimals.
let P = boot({ symbol: '€', format: '%2$s %1$s', decimals: 2, decimal: ',', thousand: ' ' });
check(P.money(23.4), '23,40 €', 'decimals and the symbol after the amount');
check(P.money('1234567.5'), '1 234 567,50 €', 'thousands separator');
check(P.money(0), '0,00 €', 'zero');
check(P.money(6.5, 'лв.'), '6,50 лв.', 'another currency symbol (a fee in the store currency)');

P = boot({ symbol: '$', format: '%1$s%2$s', decimals: 0, decimal: '.', thousand: ',' });
check(P.money(1500), '$1,500', 'symbol first, no decimals');

const lowest = P.lowestOffer([{ over: 0, left: 0 }, { over: 150, left: 50 }, { over: 100, left: 0 }, { over: 120, left: 20 }]);
check(lowest.over, 100, 'the lowest free shipping total wins');
check(lowest.left, 0, 'its amount left goes with it');
const closest = P.lowestOffer([{ over: 100, left: 0 }, { over: 50, left: 20 }]);
check(closest.over, 100, 'an option already free wins over a lower total still to reach');
check(P.lowestOffer([{ over: 0, left: 0 }]), null, 'no total, no progress bar');
check(P.lowestOffer([]), null, 'no options, no progress bar');

console.log('PASS: ' + checks + ' free shipping progress assertions');
