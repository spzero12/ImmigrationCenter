<?php

// «Лист Word» для страницы редактирования документа: HTML шаблона
// (templates/processes/<doc>.html, сделан из .docx — см.
// templates/build_html.ps1) с плейсхолдерами ${ключ}, превращёнными в
// редактируемые поля. Название поля — красной подписью, значение правится
// прямо в тексте документа. Лист показывается в iframe, чтобы стили Word
// не смешивались со стилями портала.

// Даты в документах: точки (как в образце) или косые черты.
const SHEET_DOT_DATE_FIELDS = [
    'petition' => ['document_date', 'org_egrul_date'],
];

// Поля, которые в готовом документе печатаются ЗАГЛАВНЫМИ.
const SHEET_UPPERCASE_FIELDS = [
    'petition' => ['birth_country', 'birth_place', 'citizenship', 'residence_country', 'residence_region', 'visa_country', 'visa_city'],
];

function sheetEsc($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// "3.1. Фамилия рус." → "Фамилия рус."; "Телефон (если есть)" → "Телефон".
function sheetFieldName(string $label): string
{
    $label = preg_replace('/^[\d.]+\s*/u', '', $label);
    $label = preg_replace('/\s*\(.*$/u', '', $label);

    return trim($label);
}

function sheetFormatDate(string $iso, string $separator): string
{
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $iso);

    return $parsed ? $parsed->format('d' . $separator . 'm' . $separator . 'Y') : $iso;
}

// $current — значения полей (ключ => строка, даты в Y-m-d), $defaults — из
// карточки (для отметки «изменено»).
function renderDocumentSheet(string $document, array $definitions, array $current, array $defaults): string
{
    $path = __DIR__ . '/../templates/processes/' . $document . '.html';
    $html = is_file($path) ? file_get_contents($path) : false;

    if ($html === false) {
        return '<p>Нет HTML-шаблона документа (templates/build_html.ps1).</p>';
    }

    $dots = SHEET_DOT_DATE_FIELDS[$document] ?? [];
    $uppercase = SHEET_UPPERCASE_FIELDS[$document] ?? [];

    // Поля ${ключ} → редактируемые элементы.
    $html = preg_replace_callback('/\$\{([a-z0-9_]+)\}/', static function ($m) use ($definitions, $current, $defaults, $dots, $uppercase) {
        $key = $m[1];

        // Адрес в гарантийном письме: в docx он делится на две линии бланка, а на
        // странице это одно поле (перенос по ширине листа).
        if ($key === 'stay_address_2') {
            return '';
        }

        if ($key === 'stay_address_1') {
            $key = 'stay_address';
        }

        if ($key === 'gender_male' || $key === 'gender_female') {
            $gender = $key === 'gender_male' ? 'male' : 'female';

            return '<span class="wf-gender" data-gender="' . $gender . '" title="Пол: нажмите, чтобы выбрать">'
                . (($current['gender'] ?? '') === $gender ? 'X' : '') . '</span>';
        }

        $definition = $definitions[$key] ?? null;
        $value = (string) ($current[$key] ?? '');

        // Только для чтения (регион из карточки) и неизвестные ключи — обычный текст.
        if (!$definition || ($definition[3] ?? false)) {
            return sheetEsc($value);
        }

        $isDate = $definition[1] === 'date';
        $separator = in_array($key, $dots, true) ? '.' : '/';
        $shown = $isDate ? sheetFormatDate($value, $separator) : $value;
        $changed = (string) $value !== (string) ($defaults[$key] ?? '');
        $defaultShown = $isDate ? sheetFormatDate((string) ($defaults[$key] ?? ''), $separator) : (string) ($defaults[$key] ?? '');

        return '<span class="wf' . ($changed ? ' changed' : '') . (in_array($key, $uppercase, true) ? ' uc' : '') . '" data-key="' . $key . '">'
            . '<span class="wf-val" contenteditable="true" spellcheck="false" title="' . sheetEsc(sheetFieldName($definition[0])) . '" data-type="' . ($isDate ? 'date' : 'text') . '"'
            . ($isDate ? ' data-sep="' . $separator . '" data-iso="' . sheetEsc($value) . '"' : '')
            . ' data-default="' . sheetEsc($defaultShown) . '">' . sheetEsc($shown) . '</span></span>';
    }, $html);

    // Поля страницы из @page шаблона (в вебе его нет) → отступы листа.
    $padding = '42.5pt';

    if (preg_match('/@page\s+WordSection1\s*\{[^}]*?margin:\s*([^;}]+)/s', $html, $m)) {
        $padding = trim($m[1]);
    }

    // Левое/правое поле листа (margin: сверху справа снизу слева | 1–4 значения) —
    // нужно, чтобы разделитель страниц выходил ровно за края листа.
    $parts = preg_split('/\s+/', $padding);
    $padRight = $parts[1] ?? $parts[0];
    $padLeft = $parts[3] ?? $padRight;

    $css = <<<CSS
<style>
html { background: #dfe3e8; }
body.desk { margin: 0; padding: 14px 0 28px; background: #dfe3e8; }
div.WordSection1 {
    width: 595.3pt; min-height: 841.9pt; box-sizing: border-box; margin: 0 auto;
    padding: {$padding}; background: #fff; box-shadow: 0 1px 4px rgba(0,0,0,.35);
}
.pgbreak { display: block; height: 22px; margin: 14px calc(-1 * {$padRight}) 14px calc(-1 * {$padLeft}); background: #dfe3e8; box-shadow: inset 0 3px 3px -3px rgba(0,0,0,.4), inset 0 -3px 3px -3px rgba(0,0,0,.4); }
.wf-val { background: #fdeded; border-bottom: 1px dashed #d63939; border-radius: 2px; padding: 0 1px; outline: none; cursor: text; }
.wf-val:empty { display: inline-block; min-width: 3em; }
.wf-val:hover { background: #fbdcdc; }
.wf-val:focus { background: #fff; box-shadow: 0 0 0 2px rgba(214,57,57,.45); }
.wf.changed .wf-val { background: #fff3cd; border-bottom-color: #f59f00; }
.wf.invalid .wf-val { box-shadow: 0 0 0 2px #d63939; }
.wf.uc .wf-val { text-transform: uppercase; }
.wf-gender { display: inline-block; min-width: 1.1em; text-align: center; color: #d63939; font-weight: bold; cursor: pointer; background: #fdeded; }
.wf-gender:hover { background: #fbdcdc; }
</style>
CSS;

    $script = <<<'JS'
<script>
(function () {
    function fieldsByKey(key) { return document.querySelectorAll('.wf[data-key="' + key + '"] .wf-val'); }

    function parseDate(text, sep) {
        const m = text.trim().match(/^(\d{1,2})[.\/\-](\d{1,2})[.\/\-](\d{4})$/);
        if (m) {
            const d = +m[1], mo = +m[2], y = +m[3], dt = new Date(y, mo - 1, d);
            if (dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d) {
                const pad = n => String(n).padStart(2, '0');
                return { iso: y + '-' + pad(mo) + '-' + pad(d), shown: pad(d) + sep + pad(mo) + sep + y };
            }
        }
        return text.trim() === '' ? { iso: '', shown: '' } : null;
    }

    function plain(el) { return el.textContent.replace(/​/g, '').replace(/\s+/g, ' ').trim(); }

    function currentValue(el) { return el.dataset.type === 'date' ? el.dataset.iso : plain(el); }

    function markChanged(wf) {
        const el = wf.querySelector('.wf-val');
        wf.classList.toggle('changed', (el.dataset.type === 'date' ? el.textContent.trim() : plain(el)) !== el.dataset.default);
    }

    function notify() { if (window.parent && window.parent.sheetResized) { window.parent.sheetResized(); } }

    document.addEventListener('keydown', function (e) {
        if (e.target.classList && e.target.classList.contains('wf-val') && e.key === 'Enter') {
            e.preventDefault();
            e.target.blur();
        }
    });

    document.addEventListener('paste', function (e) {
        if (e.target.classList && e.target.classList.contains('wf-val')) {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\s+/g, ' ');
            document.execCommand('insertText', false, text);
        }
    });

    document.addEventListener('input', function (e) {
        const el = e.target;
        if (!el.classList || !el.classList.contains('wf-val')) { return; }
        const wf = el.closest('.wf');
        if (el.dataset.type !== 'date') {
            // Одинаковые поля в документе синхронизируются.
            fieldsByKey(wf.dataset.key).forEach(other => { if (other !== el) { other.textContent = el.textContent; } });
        }
        markChanged(wf);
        notify();
    });

    document.addEventListener('focusout', function (e) {
        const el = e.target;
        if (!el.classList || !el.classList.contains('wf-val') || el.dataset.type !== 'date') { return; }
        const wf = el.closest('.wf');
        const parsed = parseDate(el.textContent, el.dataset.sep);
        if (parsed) {
            el.dataset.iso = parsed.iso;
            el.textContent = parsed.shown;
            wf.classList.remove('invalid');
        } else {
            wf.classList.add('invalid');
            el.title = 'Дата в формате ДД' + el.dataset.sep + 'ММ' + el.dataset.sep + 'ГГГГ';
        }
        markChanged(wf);
    });

    function setGender(value) {
        document.body.dataset.gender = value;
        document.querySelectorAll('.wf-gender').forEach(el => { el.textContent = el.dataset.gender === value ? 'X' : ''; });
    }

    document.addEventListener('click', function (e) {
        const el = e.target.closest ? e.target.closest('.wf-gender') : null;
        if (el) { setGender(document.body.dataset.gender === el.dataset.gender ? '' : el.dataset.gender); }
    });

    window.addEventListener('load', notify);

    window.sheetInit = function (gender) { document.body.dataset.gender = gender || ''; };

    window.getSheetValues = function () {
        const values = {};
        document.querySelectorAll('.wf').forEach(wf => { values[wf.dataset.key] = currentValue(wf.querySelector('.wf-val')); });
        if (document.querySelector('.wf-gender')) { values.gender = document.body.dataset.gender || ''; }
        return values;
    };

    window.hasInvalidFields = function () { return document.querySelector('.wf.invalid') !== null; };

    window.setSheetField = function (key, value) {
        fieldsByKey(key).forEach(el => { el.textContent = value; markChanged(el.closest('.wf')); });
        notify();
    };
})();
</script>
JS;

    // Разрыв страницы Word → визуальный разрыв листа.
    $html = preg_replace('/<br\s+clear=all\s+style=\'page-break-before:always\'>/i', '<span class="pgbreak"></span>', $html);
    $html = preg_replace('/<body\b/', '<body class="desk"', $html, 1);
    $html = str_replace('</head>', $css . '</head>', $html);
    $html = str_replace('</body>', $script . '<script>sheetInit(' . json_encode($current['gender'] ?? '') . ');</script></body>', $html);

    return $html;
}
