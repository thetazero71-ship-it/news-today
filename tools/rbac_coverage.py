# -*- coding: utf-8 -*-
"""RBAC coverage report: for every admin route, which permission protects it.

Run: python tools/rbac_coverage.py
"""
import re, io, sys, os, glob
from collections import defaultdict, Counter
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8', errors='replace')

root = r'C:\Users\P740\Desktop\MouseWithoutBorders\tech-news-platform'
idx = open(os.path.join(root, 'index.php'), encoding='utf-8').read()
routes = re.findall(r"\$router->(get|post|put|delete)\('(/admin/[^']+)'\s*,\s*'([A-Za-z0-9_]+)@([A-Za-z0-9_]+)'", idx)

# generic CRUD controllers protect themselves via crudPermission()
GENERIC = {
    'AdsController': 'ads.manage',
    'PagesController': 'pages.manage',
    'MenusController': 'menus.manage',
}

cls_src = {}
for p in glob.glob(os.path.join(root, 'controllers', '**', '*.php'), recursive=True) + \
         glob.glob(os.path.join(root, 'core', '*.php')):
    s = open(p, encoding='utf-8', errors='replace').read()
    m = re.search(r'class\s+([A-Za-z0-9_]+)', s)
    if m:
        cls_src.setdefault(m.group(1), s)


def method_body(src, meth):
    m = re.search(r'(?:public|protected|private)\s+function\s+' + re.escape(meth) + r'\s*\([^)]*\)\s*\{', src)
    if not m:
        return None
    brace = src.index('{', m.start())
    depth, j = 0, brace
    while j < len(src):
        if src[j] == '{':
            depth += 1
        elif src[j] == '}':
            depth -= 1
            if depth == 0:
                break
        j += 1
    return src[brace:j]


rows, legacy, unknown = [], [], []
for verb, path, cls, meth in routes:
    src = cls_src.get(cls)
    body = method_body(src, meth) if src else None
    perms = []
    if body:
        perms = re.findall(r"(?:guardPermission|postGuardPermission)\(\s*'([^']+)'", body)
    if not perms and body:
        arr = re.findall(r"(?:guardPermission|postGuardPermission)\(\s*array\(([^)]*)\)", body)
        for a in arr:
            perms += re.findall(r"'([^']+)'", a)
    if not perms and cls in GENERIC:
        perms = [GENERIC[cls]]
    if not perms:
        legacy.append('%s %s -> %s@%s' % (verb.upper(), path, cls, meth))
    else:
        rows.append((verb.upper(), path, cls, meth, ', '.join(sorted(set(perms)))))

out = []
out.append('RBAC COVERAGE REPORT')
out.append('=' * 70)
out.append('admin routes total      : %d' % len(routes))
out.append('permission-bound routes : %d' % len(rows))
out.append('still on legacy guard   : %d' % len(legacy))
out.append('')
out.append('--- permission usage ---')
c = Counter()
for _, _, _, _, ps in rows:
    for p in [x.strip() for x in ps.split(',')]:
        c[p] += 1
for k, v in sorted(c.items(), key=lambda x: -x[1]):
    out.append('  %-26s %d' % (k, v))
out.append('')
out.append('--- routes by permission ---')
by = defaultdict(list)
for verb, path, cls, meth, ps in rows:
    by[ps].append('%s %s' % (verb, path))
for k in sorted(by):
    out.append('')
    out.append('[%s]  (%d)' % (k, len(by[k])))
    for r in sorted(set(by[k])):
        out.append('    ' + r)
if legacy:
    out.append('')
    out.append('--- still using guardAdmin() ---')
    for l in sorted(legacy):
        out.append('  ' + l)

open(os.environ['TEMP'] + r'\opencode\rbac_coverage.txt', 'w', encoding='utf-8').write('\n'.join(out))
print('routes=%d bound=%d legacy=%d' % (len(routes), len(rows), len(legacy)))
print('distinct permissions used: %d' % len(c))