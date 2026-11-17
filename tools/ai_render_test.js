// Regression test for the AI assistant chat renderer.
//
// Bug: a URL in an answer was turned into <a href="$1" ...>$1</a> because the
// bare-URL regex had no capture group (in JS `$1` then stays literal) AND it
// ran after markdown links had already produced anchors, so it rewrote their
// href. The chat showed a broken link starting with `$1">`.
//
// The functions below are extracted from the real assets/js/tech-platform.js,
// so this test always exercises production code.
//
// Run: node tools/ai_render_test.js

'use strict';

const fs = require('fs');
const path = require('path');

const file = path.join(__dirname, '..', 'assets', 'js', 'tech-platform.js');
const src = fs.readFileSync(file, 'utf8');

const start = src.indexOf('    function escapeHtml(str)');
const end = src.indexOf('    function renderCodeBlock');
if (start < 0 || end < 0) {
    console.error('FAIL could not locate the renderer in tech-platform.js');
    process.exit(1);
}

const block = src.slice(start, end);
const make = new Function('base', block + '\nreturn { inlineMarkdown: inlineMarkdown, normalizeModelMarkup: normalizeModelMarkup, escapeHtml: escapeHtml };');
const api = make('');

let pass = 0;
let fail = 0;
function t(label, cond, extra) {
    if (cond) {
        pass++;
        console.log('PASS ' + label);
    } else {
        fail++;
        console.log('FAIL ' + label + (extra ? '\n     ' + extra : ''));
    }
}

// Mirrors renderMarkdown(): normalize -> escape -> inline transforms.
function render(text) {
    return api.inlineMarkdown(api.escapeHtml(api.normalizeModelMarkup(text)));
}

function anchors(html) {
    return (html.match(/<a\s/g) || []).length;
}
function hrefs(html) {
    return (html.match(/href="/g) || []).length;
}

const headline = 'الخارجية الأمريكية تأمر موظفيها باستبدال مصطلح "الذكاء الاصطناعي" بـ "الذكاء الخارق"';
const url = 'https://news-today.42web.io/article/123';

// 1) the reported case: markdown link whose label contains quotes
let out = render('تعرف على المزيد: [' + headline + '](' + url + ')');
t('markdown link renders one anchor', anchors(out) === 1, out);
t('markdown link keeps href intact', out.indexOf('<a href="' + url + '"') !== -1, out);
t('markdown link keeps the label', out.indexOf(headline.replace(/"/g, '&quot;')) !== -1, out);
t('no literal $1 leaks', out.indexOf('$1') === -1, out);
t('no nested anchor', out.indexOf('<a href="<a') === -1, out);

// 2) bare URL
out = render('المصدر: ' + url);
t('bare URL becomes an anchor', anchors(out) === 1, out);
t('bare URL href is the address', out.indexOf('href="' + url + '"') !== -1, out);
t('bare URL text is the address', out.indexOf('>' + url + '</a>') !== -1, out);
t('bare URL: no $1', out.indexOf('$1') === -1, out);

// 3) sentence punctuation stays outside the link
out = render('انظر ' + url + '.');
t('trailing period stays outside the link', out.indexOf(url + '.</a>') === -1, out);
t('trailing period follows the anchor', out.indexOf('</a>.') !== -1, out);

out = render('انظر (' + url + ')');
t('sentence bracket is not linked', out.indexOf(url + '</a>)') !== -1, out);

out = render('https://en.wikipedia.org/wiki/Mercury_(planet)');
t('balanced parentheses stay inside the URL', out.indexOf('href="https://en.wikipedia.org/wiki/Mercury_(planet)"') !== -1, out);

// 4) markup smuggled out by the model
out = render('اقرأ <a href="' + url + '">الخبر</a>');
t('smuggled anchor becomes one clean anchor', anchors(out) === 1 && out.indexOf(url) !== -1, out);
t('smuggled anchor: no $1', out.indexOf('$1') === -1, out);

// 5) internal article links
out = render('اقرأ [الخبر](/article/123)');
t('internal link uses the base prefix', out.indexOf('href="/article/123"') !== -1, out);
t('internal link stays in the same tab', out.indexOf('target="_blank"') === -1, out);
t('internal link keeps its label', out.indexOf('>الخبر</a>') !== -1, out);

// 6) inline code must not be linkified
out = render('افتح `' + url + '` ثم تابع');
t('URL inside inline code is not a link', anchors(out) === 0 && out.indexOf('<code>' + url + '</code>') !== -1, out);

// 7) emphasis still works
out = render('**عريض** و*مائل* و~~محذوف~~');
t('bold renders', out.indexOf('<strong>عريض</strong>') !== -1, out);
t('italic renders', out.indexOf('<em>مائل</em>') !== -1, out);
t('strikethrough renders', out.indexOf('<del>محذوف</del>') !== -1, out);

// 8) several links in one answer, mixed styles
out = render('أول [' + headline + '](' + url + ') ثم https://example.com/x وثالث [خبر](/article/9)');
t('three links produce three anchors', anchors(out) === 3, out);
t('every anchor has exactly one href', hrefs(out) === 3, out);
t('mixed links never nest', out.indexOf('<a href="<a') === -1, out);
t('no placeholder characters remain', out.indexOf('\u0000') === -1, JSON.stringify(out));

// 9) source still contains the group needed for the bare-URL pass
t('bare URL pattern captures the address', /\(\(\?:https\?\|ftp\):/.test(src), 'capture group missing');

console.log('\npassed: ' + pass + '  failed: ' + fail);
process.exit(fail === 0 ? 0 : 1);
