/* Exercises the actual shipped component computations, not a copy of their logic. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const root = path.resolve(__dirname, '..');
const context = vm.createContext({ URL, window: { location: { hostname: 'admin.example' } } });
const helper = path.join(root, 'assets/js/courier-pdf.js');
if (fs.existsSync(helper)) vm.runInContext(fs.readFileSync(helper, 'utf8'), context);
// Main ships plain assets/js files (no dist/ bundle) and no CVC: test Econt's pdfUrl() and the shared helper directly.
const econtSource = fs.readFileSync(path.join(root, 'assets/js/label-econt.js'), 'utf8');
const match = econtSource.match(/function pdfUrl\(\) \{([\s\S]*?)\n\t\}/);
assert.ok(match, 'Find shipped econt iframe computation');
const econtUrl = vm.runInContext('(function(state){' + match[1] + '})', context);
const getters = {
  econt: function () { return econtUrl(this); },
  cvc: function () { return context.window.nashenskiCourierPdf.url(this.pdf); }
};
const files = getters;
let checks = 0;
function check(actual, expected, message) { checks++; assert.equal(actual, expected, message); }
function preview(courier, value, size = '10x9') {
  return getters[courier].call({ shipmentStatus: courier === 'econt' ? { label: { pdfURL: value } } : { pdf: value }, pdf: value, size });
}
const rejected = [
  'javascript:parent.previewExploit=true;void(0)//', 'JaVaScRiPt:alert(1)',
  'java\nscript:alert(1)', 'data:text/html,<script>parent.x=1</script>',
  'vbscript:msgbox(1)', 'file:///etc/passwd', 'blob:https://admin.example/id',
  '//ee.econt.com/label.pdf', '/label.pdf', 'label.pdf', '', null, undefined, {}, [], 42,
  ' https://ee.econt.com/label.pdf', 'https:\\ee.econt.com\\label.pdf',
  'https://user:password@ee.econt.com/label.pdf', 'https://ee.econt.com/label\t.pdf',
  'https://admin.example/label.pdf', 'http://ADMIN.EXAMPLE./label.pdf',
  'https://admin.example:444/label.pdf', 'https://admin.example%2f.evil.test/file',
  'https://ee.econt.com:99999/file', 'https:///ee.econt.com/file',
  // Only Econt's own hosts may be framed.
  'https://lox.e-cvc.bg/labels/document.pdf?token=example', 'https://courier-cdn.example/print.pdf',
  'https://econt.com.evil.test/label.pdf', 'https://evilecont.com/label.pdf', 'https://ee.econt.com.evil.test/label.pdf'
];
for (const courier of Object.keys(files)) {
  for (const value of rejected) check(preview(courier, value), '', courier + ' rejects ' + String(value));
  check(getters[courier].call({}), '', courier + ' accepts missing status without throwing');
}
for (const value of [
  'https://ee.econt.com/api_export.php?key=abc%2B123&label=old#page=1',
  'https://demo.econt.com/ee/api_export.php?key=demo',
  'https://econt.com/label.pdf',
  'https://EE.ECONT.COM/label.pdf'
]) {
  check(preview('cvc', value), new URL(value).href, 'Remote PDF URL retained');
  const expected = new URL(value); expected.searchParams.set('label', '10x9');
  check(preview('econt', value), expected.href, 'Econt size set as a query parameter');
}
check(preview('cvc', 'http://ee.econt.com/print.pdf'), 'https://ee.econt.com/print.pdf', 'Legacy HTTP print URL upgraded');
const special = new URL(preview('econt', 'https://ee.econt.com/file.pdf?key=abc#page=2', '&x=1#fragment'));
check(special.searchParams.get('label'), '&x=1#fragment', 'Size cannot introduce another query parameter');
check(special.searchParams.get('x'), null, 'No injected query key');
check(special.hash, '#page=2', 'Original PDF fragment preserved');
console.log('PASS: ' + checks + ' courier PDF JavaScript assertions');
