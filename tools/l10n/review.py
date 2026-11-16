"""
ملف مراجعة الترجمة (عربي | إنكليزي | لغة الهدف) للموقع والتطبيق معاً، واستيراد تعديلات المراجع.

    python tools/l10n/review.py export --app <مسار تطبيق Flutter> [--lang tr] [--out review-tr.xlsx]
    python tools/l10n/review.py import review-tr.xlsx --app <مسار التطبيق> [--lang tr] [--apply]

المصادر:
  web   lang/<lang>.json                مفاتيح عربية، متغيرات :name
  web   lang/<lang>/{categories,addresses,validation}.php
  app   assets/l10n/<lang>.json         مفاتيح Dart: ${expr} أو $name، والترجمة @{expr}

في الملف كل عبارة عربية تظهر مرة واحدة حتى لو استُخدمت في الموقع والتطبيق، والمتغيرات
بصيغة موحّدة {name}. المراجع يكتب في عمود "مراجَع" فقط؛ الاستيراد يكتبه في كل مصدر
بصيغته، ويرفض أي تصحيح غيّر المتغيرات. بدون --apply يعرض التغييرات فقط.

يتطلب openpyxl (pip install openpyxl). يمكن تمرير مسار التطبيق عبر TRENDSHAM_APP بدل --app.
"""
import argparse
import json
import os
import re
import subprocess
import sys

try:
    from openpyxl import Workbook, load_workbook
    from openpyxl.styles import Alignment, Font, PatternFill
    from openpyxl.worksheet.datavalidation import DataValidation  # noqa: F401  (متاح لمن يوسّع)
except ImportError:  # pragma: no cover
    sys.exit('openpyxl غير مثبّت: pip install openpyxl')

WEB_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
PHP_FILES = ('categories', 'addresses', 'validation')
LANG_NAMES = {'tr': 'Turkish', 'en': 'English'}
ARABIC = re.compile(r'[؀-ۿ]')
SHEET = 'Translations'
COLS = ['ID', 'Arabic', 'English', 'CURRENT', 'REVIEWED', 'Comment', 'Used in', 'Notes', 'refs']

# ------------------------------------------------------------------ المتغيرات


def web_norm(text):
    return re.sub(r':([a-z_]+)', r'{\1}', text)


def web_denorm(text):
    return re.sub(r'\{([a-z_]+)\}', r':\1', text)


def dart_placeholders(key):
    """مواضع ${expr} (مع أقواس متداخلة) و$name في مفتاح Dart، كما في L10nCatalog.placeholders."""
    out, i = [], 0
    while i < len(key) - 1:
        if key[i] != '$':
            i += 1
            continue
        if key[i + 1] == '{':
            depth, j = 1, i + 2
            while j < len(key) and depth:
                depth += {'{': 1, '}': -1}.get(key[j], 0)
                j += 1
            if depth == 0:
                out.append((i, j, key[i + 2:j - 1]))
                i = j
                continue
        m = re.match(r'[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*', key[i + 1:])
        if m:
            out.append((i, i + 1 + m.end(), m.group(0)))
            i += 1 + m.end()
            continue
        i += 1
    return out


SIMPLE_EXPR = re.compile(r'^[A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)*$')


def app_tokens(key):
    """تعبير Dart -> اسم يراه المراجع: البسيط كما هو ({order}، {cart.currency})،
    والمعقّد (أقواس، شروط، نصوص) رمز مرقّم {x1}، {x2}... بترتيب ظهوره في المفتاح."""
    tokens, n = {}, 0
    for _, _, expr in dart_placeholders(key):
        if expr in tokens:
            continue
        if SIMPLE_EXPR.match(expr) and len(expr) <= 40:
            tokens[expr] = expr
        else:
            n += 1
            tokens[expr] = f'x{n}'
    return tokens


def app_key_norm(key):
    tokens, last, out = app_tokens(key), 0, []
    for start, end, expr in dart_placeholders(key):
        out.append(key[last:start] + '{' + tokens[expr] + '}')
        last = end
    return ''.join(out) + key[last:]


def app_value_exprs(value):
    """مواضع @{expr} في ترجمة التطبيق، مع أقواس متداخلة داخل التعبير."""
    out, i = [], 0
    while True:
        i = value.find('@{', i)
        if i < 0:
            return out
        depth, j = 1, i + 2
        while j < len(value) and depth:
            depth += {'{': 1, '}': -1}.get(value[j], 0)
            j += 1
        if depth:
            return out
        out.append((i, j, value[i + 2:j - 1]))
        i = j


def app_value_norm(value, key):
    tokens, last, out = app_tokens(key), 0, []
    for start, end, expr in app_value_exprs(value):
        # تعبير لا يطابق المفتاح يبقى كما هو، فيظهر للمراجع ويرفضه فحص المتغيرات
        out.append(value[last:start] + ('{' + tokens[expr] + '}' if expr in tokens else value[start:end]))
        last = end
    return ''.join(out) + value[last:]


def app_denorm(text, key):
    reverse = {token: expr for expr, token in app_tokens(key).items()}
    return re.sub(r'\{([^{}]+)\}', lambda m: '@{' + reverse.get(m.group(1), m.group(1)) + '}', text)


def placeholders(text):
    return sorted(re.findall(r'\{([^{}]+)\}', text or ''))


# ------------------------------------------------------------------ القراءة


def read_json(path):
    with open(path, encoding='utf-8-sig') as f:
        return json.load(f)


def php_array(path):
    code = 'echo json_encode(require $argv[1], JSON_UNESCAPED_UNICODE);'
    raw = subprocess.run(['php', '-r', code, path], capture_output=True, text=True, encoding='utf-8', check=True).stdout
    return json.loads(raw) if raw.strip() else {}


def flatten(d, prefix=''):
    out = {}
    for k, v in (d or {}).items():
        key = f'{prefix}{k}'
        if isinstance(v, dict):
            out.update(flatten(v, key + '.'))
        elif isinstance(v, str):
            out[key] = v
    return out


def collect(lang, app_root):
    """يعيد قائمة إدخالات: {source, key, ar, en, cur} بصيغة موحّدة."""
    entries = []
    en = read_json(os.path.join(WEB_ROOT, 'lang', 'en.json'))
    cur = read_json(os.path.join(WEB_ROOT, 'lang', f'{lang}.json'))
    for key in sorted(set(en) | set(cur)):
        if ARABIC.search(key):
            entries.append({'source': 'web', 'key': key, 'ar': web_norm(key),
                            'en': web_norm(en.get(key, '')), 'cur': web_norm(cur.get(key, ''))})

    for name in PHP_FILES:
        files = {code: os.path.join(WEB_ROOT, 'lang', code, f'{name}.php') for code in ('ar', 'en', lang)}
        if not os.path.exists(files[lang]):
            continue
        data = {code: flatten(php_array(p)) if os.path.exists(p) else {} for code, p in files.items()}
        for key, value in data[lang].items():
            if key.startswith('attributes.') or name != 'categories':
                ar = data['ar'].get(key)
            else:
                ar = key  # أسماء الأقسام: المفتاح هو الاسم العربي
            if not ar:
                continue
            entries.append({'source': f'web-php:{name}', 'key': key, 'ar': web_norm(ar),
                            'en': web_norm(data['en'].get(key, '')), 'cur': web_norm(value)})

    if app_root:
        en = read_json(os.path.join(app_root, 'assets', 'l10n', 'en.json'))
        cur = read_json(os.path.join(app_root, 'assets', 'l10n', f'{lang}.json'))
        for key in sorted(set(en) | set(cur)):
            if ARABIC.search(key):
                entries.append({'source': 'app', 'key': key, 'ar': app_key_norm(key),
                                'en': app_value_norm(en.get(key, ''), key), 'cur': app_value_norm(cur.get(key, ''), key)})
    return entries


# ------------------------------------------------------------------ التصدير

USED_IN = {'web': 'Website', 'app': 'App'}


def export(args):
    entries = collect(args.lang, args.app)
    rows = {}
    for e in entries:
        row = rows.setdefault(e['ar'], {'ar': e['ar'], 'en': {}, 'cur': {}, 'refs': []})
        row['refs'].append([e['source'], e['key']])
        row['en'][e['source']] = e['en']
        row['cur'][e['source']] = e['cur']

    target = LANG_NAMES.get(args.lang, args.lang)
    wb = Workbook()
    guide = wb.active
    guide.title = 'Instructions'
    sheet = wb.create_sheet(SHEET)
    header = [c.replace('CURRENT', f'{target} (current)').replace('REVIEWED', f'{target} (reviewed)') for c in COLS]
    sheet.append(header)

    base = Font(name='Arial', size=10)
    bold = Font(name='Arial', size=10, bold=True, color='FFFFFF')
    head_fill = PatternFill('solid', fgColor='0B5F4A')
    input_fill = PatternFill('solid', fgColor='FFF4C2')
    wrap = Alignment(wrap_text=True, vertical='top')
    rtl = Alignment(wrap_text=True, vertical='top', horizontal='right', readingOrder=2)

    def put(r, c, value, align=wrap):
        cell = sheet.cell(row=r, column=c, value=value)
        if isinstance(value, str) and value.startswith('='):
            cell.data_type = 's'  # نص يبدأ بـ = ليس صيغة
        cell.font, cell.alignment = base, align
        return cell

    ordered = sorted(rows.values(), key=lambda r: (0 if any(s == 'web' for s, _ in r['refs']) else 1, r['ar']))
    for n, row in enumerate(ordered, start=1):
        r = n + 1
        sources = [s for s, _ in row['refs']]
        used = sorted({USED_IN.get(s.split(':')[0], 'Website') + (f' ({s.split(":")[1]}.php)' if ':' in s else '') for s in sources})
        en_values = list(dict.fromkeys(v for v in row['en'].values() if v))
        cur_values = {s: v for s, v in row['cur'].items()}
        distinct = list(dict.fromkeys(v for v in cur_values.values() if v))
        notes = []
        if placeholders(row['ar']):
            notes.append('Keep: ' + ' '.join('{' + p + '}' for p in dict.fromkeys(placeholders(row['ar']))))
        if len(distinct) > 1:
            notes.append('Differs: ' + ' | '.join(f'{USED_IN.get(s.split(":")[0], s)}: {v}' for s, v in cur_values.items() if v))
        if not distinct:
            notes.append(f'Missing {target}')
        put(r, 1, n)
        put(r, 2, row['ar'], rtl)
        put(r, 3, en_values[0] if en_values else '')
        put(r, 4, distinct[0] if distinct else '')
        put(r, 5, None).fill = input_fill
        put(r, 6, None).fill = input_fill
        put(r, 7, ', '.join(used))
        put(r, 8, '\n'.join(notes))
        put(r, 9, json.dumps(row['refs'], ensure_ascii=False))

    for c, width in enumerate([7, 45, 45, 45, 45, 28, 18, 30, 10], start=1):
        sheet.column_dimensions[chr(64 + c)].width = width
    for cell in sheet[1]:
        cell.font, cell.fill, cell.alignment = bold, head_fill, Alignment(vertical='center', wrap_text=True)
    sheet.column_dimensions['I'].hidden = True
    sheet.freeze_panes = 'C2'
    sheet.auto_filter.ref = f'A1:H{len(ordered) + 1}'

    guide.column_dimensions['A'].width = 110
    lines = [
        (f'TrendSham — {target} translation review', Font(name='Arial', size=14, bold=True)),
        ('', base),
        (f'Sheet "{SHEET}" has one row per Arabic phrase used on the website and/or in the mobile app ({len(ordered)} rows).', base),
        (f'Only edit the two yellow columns: "{target} (reviewed)" and "Comment". Leave every other column as it is.', base),
        (f'• If the current {target} text is correct, leave "{target} (reviewed)" empty.', base),
        (f'• If it needs a change, write the full corrected text in "{target} (reviewed)" (not just the changed word).', base),
        ('• Keep every {placeholder} exactly as written, e.g. {order}, {n}, {store}. They are filled in by the system.', base),
        ('  You may move a placeholder within the sentence to fit Turkish word order, but do not translate or remove it.', base),
        ('  {x1}, {x2}... are values the app computes (an amount, a label); treat them like any other placeholder.', base),
        ('• Keep line breaks and bullet points (•) in long texts such as the legal pages.', base),
        ('• "Used in" tells you where the text appears. "Notes" lists placeholders to keep and any differences between website and app.', base),
        ('• Use "Comment" for questions or context; it is not imported.', base),
        ('• You can sort and filter freely. Do not delete rows or the hidden column I.', base),
        ('', base),
        ('Example of a corrected row:', Font(name='Arial', size=10, bold=True)),
        ('  Arabic: تم استلام طلبك #{order}', base),
        ('  Turkish (current): #{order} numaralı siparişiniz alındı', base),
        ('  Turkish (reviewed): Siparişiniz (#{order}) alındı', base),
    ]
    for i, (text, font) in enumerate(lines, start=1):
        cell = guide.cell(row=i, column=1, value=text)
        cell.font, cell.alignment = font, Alignment(wrap_text=True)
    wb.active = 1

    out = args.out or f'l10n-review-{args.lang}.xlsx'
    wb.save(out)
    missing = sum(1 for r in ordered if not any(r['cur'].values()))
    differ = sum(1 for r in ordered if len({v for v in r['cur'].values() if v}) > 1)
    print(f'{out}: {len(ordered)} rows ({len(entries)} source entries), missing {missing}, website/app differ {differ}')


# ------------------------------------------------------------------ الاستيراد


def import_review(args):
    wb = load_workbook(args.file, read_only=True)
    if SHEET not in wb.sheetnames:
        sys.exit(f'لا توجد ورقة "{SHEET}" في الملف.')
    rows = wb[SHEET].iter_rows(values_only=True)
    header = [str(h or '') for h in next(rows)]
    target = LANG_NAMES.get(args.lang, args.lang)
    try:
        col = {name: header.index(name) for name in ('ID', 'Arabic', f'{target} (current)', f'{target} (reviewed)', 'refs')}
    except ValueError as e:
        sys.exit(f'عمود ناقص في الملف: {e}')

    current = {e['source'] + '\x00' + e['key']: e for e in collect(args.lang, args.app)}
    changes, errors, skipped_app = [], [], 0
    for values in rows:
        reviewed = values[col[f'{target} (reviewed)']]
        if reviewed is None or str(reviewed).strip() == '':
            continue
        row_id, arabic = values[col['ID']], str(values[col['Arabic']] or '')
        # المسافات وأسطر البداية والنهاية من الأصل (جزء من التنسيق)، لا مما كتبه المراجع
        lead, trail = re.match(r'\s*', arabic).group(0), re.search(r'\s*$', arabic).group(0)
        reviewed = lead + str(reviewed).replace('\r\n', '\n').strip() + trail
        if placeholders(reviewed) != placeholders(arabic):
            errors.append(f'#{row_id}: placeholders {placeholders(reviewed)} ≠ {placeholders(arabic)} — "{reviewed[:60]}"')
            continue
        try:
            refs = json.loads(values[col['refs']] or '[]')
        except json.JSONDecodeError:
            errors.append(f'#{row_id}: hidden column "refs" was changed')
            continue
        for source, key in refs:
            if source == 'app' and not args.app:
                skipped_app += 1
                continue
            entry = current.get(source + '\x00' + key)
            if entry is None:
                errors.append(f'#{row_id}: {source} key no longer exists: {key[:60]}')
                continue
            if entry['ar'] != arabic:
                errors.append(f'#{row_id}: Arabic text changed in the file or the source: {key[:60]}')
                continue
            if entry['cur'] != reviewed:
                changes.append((source, key, entry['cur'], reviewed, row_id))

    for source, key, old, new, row_id in changes:
        print(f'#{row_id} [{source}] {old!r}\n      → {new!r}')
    for e in errors:
        print('ERROR', e)
    if skipped_app:
        print(f'{skipped_app} app entries skipped: pass --app or set TRENDSHAM_APP.')
    print(f'{len(changes)} changes, {len(errors)} errors.')

    if not args.apply:
        print('Dry run. Re-run with --apply to write the changes.')
        return 1 if errors else 0
    write_changes(changes, args.lang, args.app)
    print('Written.')
    return 1 if errors else 0


def dump_json(path, data):
    with open(path, encoding='utf-8-sig') as f:
        raw = f.read()
    original = json.loads(raw)
    if list(original) == sorted(original):
        data = dict(sorted(data.items()))
    indent = 2 if re.match(r'\{\r?\n  "', raw) else 4
    out = json.dumps(data, ensure_ascii=False, indent=indent) + ('\n' if raw.endswith('\n') else '')
    if '\\/' in raw:
        out = out.replace('/', '\\/')
    with open(path, 'w', encoding='utf-8', newline='\r\n' if '\r\n' in raw else '\n') as f:
        f.write(out)


def php_literal(value):
    return "'" + value.replace('\\', '\\\\').replace("'", "\\'") + "'"


def write_changes(changes, lang, app_root):
    by_file = {}
    for source, key, old, new, _ in changes:
        by_file.setdefault(source, []).append((key, old, new))

    for source, items in by_file.items():
        if source == 'web':
            path = os.path.join(WEB_ROOT, 'lang', f'{lang}.json')
            data = read_json(path)
            for key, _, new in items:
                data[key] = web_denorm(new)
            dump_json(path, data)
        elif source == 'app':
            path = os.path.join(app_root, 'assets', 'l10n', f'{lang}.json')
            data = read_json(path)
            for key, _, new in items:
                data[key] = app_denorm(new, key)
            dump_json(path, data)
        else:  # web-php:<file> — استبدال دقيق للقيمة بجانب مفتاحها
            path = os.path.join(WEB_ROOT, 'lang', lang, source.split(':')[1] + '.php')
            with open(path, encoding='utf-8', newline='') as f:
                code = f.read()
            for key, old, new in items:
                last = key.split('.')[-1]
                pattern = re.compile(re.escape(php_literal(last)) + r'(\s*=>\s*)' + re.escape(php_literal(web_denorm(old))))
                if len(pattern.findall(code)) != 1:
                    print(f'ERROR could not place {source} {key}; edit {path} by hand.')
                    continue
                code = pattern.sub(lambda m: php_literal(last) + m.group(1) + php_literal(web_denorm(new)), code)
            with open(path, 'w', encoding='utf-8', newline='') as f:
                f.write(code)


def main():
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest='command', required=True)
    for name in ('export', 'import'):
        p = sub.add_parser(name)
        if name == 'import':
            p.add_argument('file')
            p.add_argument('--apply', action='store_true', help='write the changes (default: dry run)')
        else:
            p.add_argument('--out')
        p.add_argument('--lang', default='tr')
        p.add_argument('--app', default=os.environ.get('TRENDSHAM_APP'), help='Flutter app root')
    args = parser.parse_args()
    if args.app and not os.path.isfile(os.path.join(args.app, 'assets', 'l10n', f'{args.lang}.json')):
        sys.exit(f'لم أجد assets/l10n/{args.lang}.json في {args.app}')
    sys.stdout.reconfigure(encoding='utf-8')
    sys.exit(export(args) if args.command == 'export' else import_review(args))


if __name__ == '__main__':
    main()
