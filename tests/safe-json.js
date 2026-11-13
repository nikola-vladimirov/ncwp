/* The JSON the scripts send to the server (the courier cookie, the export settings) writes < > % as JSON escapes, so the server can sanitize it as text before decoding it and lose nothing. Run: node tests/safe-json.js */
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const assert = require('node:assert/strict');
let checks = 0;
function check(condition, message) { checks++; assert.ok(condition, message); }

const value = { city: 'София', note: 'вход Б, 50% <b>отстъпка</b>, a<b и c>d', name: 'Офис "Център" \\ 5', list: ['%41', '<', '>'] };

['pickers.js', 'export-builder.js'].forEach((file) => {
  const source = fs.readFileSync(path.join(__dirname, '../assets/js/', file), 'utf8');
  const found = /function safeJson\( value \) \{[\s\S]*?\n\t\}/.exec(source);
  check(found, file + ' has safeJson()');
  const safeJson = vm.runInNewContext('(' + found[0] + ')', { JSON, String });
  const json = safeJson(value);
  check(!/[<>%]/.test(json), file + ': the JSON has no <, > or % left');
  check(json.includes('София') && json.includes('\\u003cb\\u003e') && json.includes('50\\u0025'), file + ': Cyrillic stays as it is, the three characters are JSON escapes');
  assert.deepEqual(JSON.parse(json), value, file + ': the value decodes to the very same data');
  checks++;
  const sent = 'pickers.js' === file ? ['encodeURIComponent( safeJson( value ) )'] : ["body.append( 'config', safeJson( config ) )", "[ 'config', safeJson( config ) ]"];
  check(sent.every((text) => source.includes(text)) && !source.includes("'config', JSON.stringify") && !source.includes('encodeURIComponent( JSON.stringify'), file + ': what goes to the server is written with safeJson()');
});

console.log('PASS: ' + checks + ' safe JSON assertions');
