/* Runs the shipped assets/js/label-pigeon.js and label-box.js (main has no webpack bundle) against a recording label-box stub. */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const root = path.resolve(__dirname, '..');
const pigeonSource = fs.readFileSync(path.join(root, 'assets/js/label-pigeon.js'), 'utf8');
let checks = 0;
function check(actual, expected, message) { checks++; assert.equal(actual, expected, message); }

function boot(cfg) {
  const run = { posts: [], reply: null };
  const walk = (node, test, out = []) => {
    if (!node || typeof node !== 'object') return out;
    if (test(node)) out.push(node);
    (node.children || []).forEach(child => walk(child, test, out));
    if (node.control) walk(node.control, test, out);
    return out;
  };
  const L = {
    copy: v => (v === undefined ? v : JSON.parse(JSON.stringify(v))),
    el: (tag, attrs, children) => ({ tag, attrs: attrs || {}, children: [].concat(children || []) }),
    field: (label, control) => ({ tag: 'field', label, control }),
    input: (obj, key, opts) => ({ tag: 'input', obj, key, opts: opts || {} }),
    select: (options, selected, onChange) => ({ tag: 'select', options, selected, onChange }),
    button: (text, cls, onClick) => ({ tag: 'button', text, onClick }),
    copyButton: () => null, errorNotice: () => null, labelFrame: () => null, statusTable: () => null,
    reloadItems: () => {},
    post: (action, data) => { run.posts.push({ action, data: JSON.parse(JSON.stringify(data)), raw: data }); return { then: fn => { fn(run.reply); return { catch: () => {} }; } }; },
    mount: (node, courier, view) => { run.view = view; return { redraw: () => {}, loading: () => {} }; }
  };
  const context = vm.createContext({ document: { getElementById: () => ({}), addEventListener: () => {} }, window: { nashenski_pigeon: cfg, nashenskiLabelBox: L }, Object, JSON, Math, String, Array, encodeURIComponent, Date });
  context.window.window = context.window;
  vm.runInContext('(function(window){' + pigeonSource + '})(window)', context);
  run.find = test => walk(run.view(), test);
  run.payer = () => run.find(n => n.tag === 'select' && n.options.some(o => o.id === 'fixed'))[0].selected.id;
  run.input = key => run.find(n => n.tag === 'input' && n.key === key)[0];
  // Keep the first Generate handler: after a success main swaps the button for Update/Delete, so a second press reuses it.
  run.generate = () => (run.button = run.button || run.find(n => n.tag === 'button' && n.text === 'Generate')[0]).onClick({ preventDefault() {} });
  return run;
}
const base = () => ({ i18n: { generateLabel: 'Generate' }, paymentType: 'cod', orderId: 7, label: { who_pays: 'receiver', service_codes: { cod_amount: '100.00' } }, cookie_data: { type: 'office', fixed_price: '-7.25' }, fixedPrice: '5.00', fixedPriceEnabled: false });

let run = boot(base());
check(run.payer(), 'receiver', 'Legacy cookie price cannot select fixed mode');
let cfg = base(); cfg.fixedPriceEnabled = true; run = boot(cfg);
check(run.payer(), 'fixed', 'Trusted server default selects fixed mode');
const price = run.input('fixedPrice');
check(price.obj[price.key], '5.00', 'Shipping input uses authoritative top-level default');
run.reply = { data: { shipmentStatus: { reference_number: '123' }, label: { who_pays: 'sender', service_codes: { cod_amount: '75.00' } } } };
run.generate();
let posted = run.posts.pop().data;
check(posted.fixedPrice, '5.00', 'Staff shipping input is sent separately from cookie');
check(posted.paymentVersion, 1, 'Form declares explicit-payment protocol');
check(posted.codOverride, undefined, 'Untouched COD is derived on server');
run = boot(cfg);
run.reply = { data: { shipmentStatus: { reference_number: '123' }, label: { who_pays: 'sender', service_codes: { cod_amount: '75.00' } } } };
const cod = run.input('cod_amount');
cod.obj[cod.key] = '75.00'; cod.opts.onInput('75.00');
run.generate();
posted = run.posts.pop().data;
check(posted.codOverride, '75.00', 'Explicit COD edit sent as final collection override');
run.generate();
posted = run.posts.pop();
check(posted.data.codOverride, undefined, 'Successful response clears the edit, so Generate again does not resubmit COD');
check('codOverride' in posted.raw, true, 'Key is present but undefined, left to the serializer');

// The shipped serializer (label-box.js encode) drops undefined values.
let body;
const boxContext = vm.createContext({ jQuery: {}, URLSearchParams, Object, String, window: {}, fetch: (url, opts) => { body = opts.body.toString(); return { then: () => ({}) }; } });
boxContext.window.window = boxContext.window;
vm.runInContext('(function(window){ window.nashenskiLabelBox = ' + fs.readFileSync(path.join(root, 'assets/js/label-box.js'), 'utf8').replace(/^[\s\S]*?window\.nashenskiLabelBox = /, '') + '})(window)', boxContext);
boxContext.window.nashenskiLabelBox.post('a', { fixedPrice: '5', codOverride: undefined }, 'n');
check(body, 'fixedPrice=5&action=a&nonce=n', 'Actual serializer omits untouched COD');
boxContext.window.nashenskiLabelBox.post('a', { fixedPrice: '5', codOverride: '75' }, 'n');
check(body, 'fixedPrice=5&codOverride=75&action=a&nonce=n', 'Actual serializer sends explicit final COD');
console.log('PASS: ' + checks + ' Pigeon payment JavaScript assertions');
