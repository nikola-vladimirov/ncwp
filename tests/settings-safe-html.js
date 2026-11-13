/* The export result in assets/js/settings.js: the handler's HTML is rebuilt from a short list of tags and attributes. Runs the shipped functions on a small stand-in for the DOM. Run: node tests/settings-safe-html.js */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '..', 'assets/js/settings.js'), 'utf8').replace(/\r\n/g, '\n');
const start = source.indexOf('\tvar SAFE_TAGS');
const end = source.indexOf('\t// Shows a result under the export');
assert.ok(start > 0 && end > start, 'Find the shipped safeCopy() and its lists');

// Stand-in nodes: an element is { tagName, attrs, childNodes }, a text node is { nodeType: 3, nodeValue }.
function element(tagName, attrs, children) {
  return {
    nodeType: 1, tagName: tagName.toUpperCase(), attrs: attrs || {}, childNodes: children || [],
    hasAttribute(name) { return name in this.attrs; },
    getAttribute(name) { return this.attrs[name]; },
    setAttribute(name, value) { this.attrs[name] = value; },
    appendChild(child) { this.childNodes.push(child); }
  };
}
const text = (value) => ({ nodeType: 3, nodeValue: value });
function html(node) {
  if (node.nodeType === 3) return node.nodeValue;
  const attrs = Object.keys(node.attrs).sort().map((name) => ' ' + name + '="' + node.attrs[name] + '"').join('');
  return '<' + node.tagName.toLowerCase() + attrs + '>' + node.childNodes.map(html).join('') + '</' + node.tagName.toLowerCase() + '>';
}
const context = vm.createContext({
  URL,
  window: { location: { href: 'https://shop.example/wp-admin/admin.php?page=wc-settings' } },
  document: { createElement: (tag) => element(tag), createTextNode: text },
  each: (list, callback) => Array.prototype.forEach.call(list, callback)
});
vm.runInContext(source.slice(start, end) + '\nthis.safeCopy = safeCopy; this.webAddress = webAddress;', context);

let checks = 0;
function check(actual, expected, message) { checks++; assert.equal(actual, expected, message); }
function copy(children) {
  const to = element('div');
  context.safeCopy(element('body', {}, children), to);
  return to.childNodes.map(html).join('');
}

for (const href of ['https://shop.example/file.xml', 'http://other.example/', '/wp-admin/admin.php?page=x', 'admin.php?page=x', '#top']) {
  check(context.webAddress(href), true, 'A web address is kept: ' + href);
}
for (const href of ['javascript:alert(1)', 'JaVaScRiPt:alert(1)', ' javascript:alert(1)', 'java\tscript:alert(1)', 'data:text/html,x', 'vbscript:x', 'blob:https://shop.example/1', 'file:///etc/passwd', 'https://']) {
  check(context.webAddress(href), false, 'Not a web address: ' + href);
}

check(
  copy([text('Done. '), element('a', { href: 'https://shop.example/f.xml', download: '', target: '_blank', onclick: 'x()', style: 'y' }, [text('Download')]), element('br')]),
  'Done. <a download="" href="https://shop.example/f.xml" target="_blank">Download</a><br></br>',
  'A link keeps its address, download and target, and loses the rest'
);
check(copy([element('a', { href: 'javascript:alert(1)', class: 'x' }, [text('link')])]), '<a class="x">link</a>', 'A script address is dropped, the link text stays');
check(copy([element('img', { src: 'x', onerror: 'x()' }), element('iframe', { src: 'https://other.example' }, [text('inside')])]), 'inside', 'An element that is not on the list is left out, its text is kept');
check(copy([element('script', {}, [text('alert(1)')]), element('style', {}, [text('p{}')]), text('after')]), 'after', 'A script or a style goes whole');
check(copy([element('ul', {}, [element('li', { onmouseover: 'x()' }, [element('form', {}, [element('strong', {}, [text('One')])])])])]), '<ul><li><strong>One</strong></li></ul>', 'Allowed elements inside one that is left out are kept');
check(copy([{ nodeType: 8, nodeValue: 'comment' }, element('span', { class: 'amount' }, [element('bdi', {}, [text('12,00')])])]), '<span class="amount"><bdi>12,00</bdi></span>', 'A comment is dropped, a price keeps its markup');

console.log('PASS: ' + checks + ' export result HTML assertions');
