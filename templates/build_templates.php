<?php

// Делает шаблоны документов процессов из образцов заказчика (templates/samples/).
// В образцах переменные данные набраны красным: красные фрагменты заменяются
// плейсхолдерами ${ключ} (и перекрашиваются в обычный цвет), всё остальное —
// тексты, таблицы, стили, колонтитулы, шрифты — остаётся байт в байт.
//
//   php templates/build_templates.php
//
// Результат: templates/processes/{petition,guarantee_letter,inventory,
// visa_petition,visa_questionnaire,visa_inventory}.docx.
// Подстановка значений — includes/docx_template.php.

const W_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

$root = __DIR__;

// Индексы абзацев — порядок //w:p в document.xml образца (см. dump в spec).
// groups  — шаблоны подряд идущих красных фрагментов абзаца, по порядку;
// insert  — добавить плейсхолдер в конец абзаца (поле в образце пустое),
//           like — абзац, с которого взять оформление текста;
// text    — заменить весь текст абзаца;
// replace — заменить фрагмент чёрного текста.
$templates = [
    'guarantee_letter' => [
        'source' => 'Гарантийное письмо.docx',
        'map' => [
            3 => ['replace' => ['г. Москве', '${region_dative}']],
            10 => ['groups' => ['${org_full_name}, ${org_legal_address},']],
            11 => ['groups' => ['${org_inn} ', '${org_ogrn} ', '${org_kpp} ${org_phone}']],
            16 => ['groups' => ['${document_date} г.']],
            19 => ['groups' => ['${org_short_name}']],
            20 => ['groups' => ['${citizenship}', '${person_name}', '${birth_date} ', '${passport_number}', '${passport_issue_date} ', '${passport_expire_date}']],
            // Под адресом две линии: первая — подчёркивание абзаца, вторая — над подписью.
            // Адрес делится на две части (includes/processes.php, addressLines).
            23 => ['groups' => ['${stay_address_1}']],
            24 => ['insert' => '${stay_address_2}', 'like' => 23],
            29 => ['text' => '${signer_position}'],
            30 => ['groups' => ['${signer_name}']],
        ],
    ],
    'petition' => [
        'source' => 'Ходатайство.docx',
        // Образец: таблицы шириной 10915 при полях 482 — 92% ширины листа, со
        // сдвигом. Вписываем в стандартные поля 1,5 см: таблицы, отступы и шрифты
        // масштабируются одинаково (ячейки не переполняются), таблицы — по центру.
        'fit_tables' => 0.935,
        // Шрифт уменьшается сильнее таблиц: размеры кратны 0,5 пт, а в узких ячейках
        // (дата выдачи документа и т. п.) запаса нет.
        'font_scale' => 0.9,
        'map' => [
            19 => ['groups' => ['${document_date}']],
            21 => ['groups' => ['${org_inn}']],
            24 => ['groups' => ['${org_ogrn}']],
            26 => ['groups' => ['${org_egrul_date}']],
            36 => ['groups' => ['${org_full_name}']],
            39 => ['groups' => ['${org_legal_address}']],
            41 => ['groups' => ['${org_actual_address}']],
            43 => ['groups' => ['${org_phone}']],
            45 => ['groups' => ['${org_email}']],
            87 => ['groups' => ['${visit_places}']],
            107 => ['groups' => ['${last_name}']],
            109 => ['groups' => ['${last_name_lat}']],
            111 => ['groups' => ['${first_name}']],
            113 => ['groups' => ['${first_name_lat}']],
            115 => ['insert' => '${middle_name}', 'like' => 107],
            117 => ['insert' => '${middle_name_lat}', 'like' => 109],
            119 => ['groups' => ['${gender_male}']],
            121 => ['insert' => '${gender_female}', 'like' => 119],
            125 => ['groups' => ['${birth_date}']],
            127 => ['groups' => ['${birth_country}']],
            130 => ['groups' => ['${birth_place}']],
            131 => ['groups' => ['${citizenship}']],
            137 => ['groups' => ['${residence_country}']],
            139 => ['groups' => ['${residence_region}']],
            143 => ['groups' => ['${visa_country}']],
            145 => ['groups' => ['${visa_city}']],
            148 => ['insert' => '${foreign_workplace}', 'like' => 107],
            157 => ['insert' => '${passport_series}', 'like' => 159],
            159 => ['groups' => ['${passport_number}']],
            161 => ['groups' => ['${passport_issued_by}']],
            163 => ['groups' => ['${passport_issue_date}']],
            165 => ['groups' => ['${passport_expire_date}']],
            169 => ['groups' => ['${stay_region}']],
            171 => ['insert' => '${stay_district}', 'like' => 169],
            173 => ['groups' => ['${stay_locality}']],
            175 => ['groups' => ['${stay_street}']],
            177 => ['groups' => ['${stay_house}']],
            179 => ['insert' => '${stay_corpus}', 'like' => 177],
            181 => ['insert' => '${stay_building}', 'like' => 177],
            183 => ['insert' => '${stay_flat}', 'like' => 177],
            185 => ['groups' => ['${stay_phone}']],
            268 => ['groups' => ['${signer_short}']],
        ],
    ],
    'inventory' => [
        'source' => 'ОПИСЬ.docx',
        'map' => [
            8 => ['before_tab' => ' ${reference_number}'],
            10 => ['groups' => ['${card_number}'], 'size' => 22],
            12 => ['fill_underscores' => ' ${applicant} '],
            14 => ['leader_line' => ' ${accept_date}'],
            17 => ['leader_line' => '${officer}'],
            18 => ['replace' => ['г. Москве', '${region_dative}']],
            23 => ['replace' => ['городу Москве', '${region_dative_long}']],
            // Первая строка данных таблицы — шаблон строки, остальные образцовые
            // строки удаляются (см. ниже), при выгрузке строка размножается.
            32 => ['groups' => ['${citizenship}']],
            33 => ['groups' => ['${full_name}']],
            34 => ['groups' => ['${passport}']],
            35 => ['text' => '${multiplicity}'],
            75 => ['groups' => ['${signer_name}']],
        ],
        'keep_first_data_row' => true,
    ],
    // Процесс «Виза» (продление визы). Ходатайство — на бланке компании (красная
    // пометка образца «НА БЛАНКЕ КОМПАНИИ» убирается, бланк подставляется из организации).
    'visa_petition' => [
        'source' => 'Ходатайство на визу.docx',
        'map' => [
            0 => ['text' => ''],
            4 => ['replace' => ['Московской области', '${region_dative}']],
            8 => ['groups' => ['${org_name_genitive}']],
            10 => ['groups' => ['${org_inn}', '${org_address_phone_1}']],
            12 => ['groups' => ['${org_address_phone_2}']],
            15 => ['groups' => ['${visa_until} ']],
            18 => ['groups' => ['${last_name}']],
            20 => ['groups' => ['${last_name_lat}']],
            27 => ['groups' => ['${first_name}']],
            29 => ['groups' => ['${first_name_lat}']],
            36 => ['insert' => '${middle_name}', 'like' => 27],
            38 => ['insert' => '${middle_name_lat}', 'like' => 29],
            45 => ['groups' => ['${birth_date}']],
            47 => ['groups' => ['${citizenship}']],
            49 => ['groups' => ['${gender_male}']],
            51 => ['insert' => '${gender_female}', 'like' => 49],
            62 => ['insert' => '${passport_series}', 'like' => 64],
            64 => ['groups' => ['${passport_number}']],
            66 => ['groups' => ['${passport_issue_date}']],
            68 => ['groups' => ['${passport_expire_date}']],
            79 => ['groups' => ['${visa_series}']],
            81 => ['groups' => ['${visa_number}']],
            83 => ['groups' => ['${visa_issue_date}']],
            85 => ['groups' => ['${visa_expire_date}']],
            94 => ['groups' => ['${visa_reason}']],
            97 => ['groups' => ['${entry_date}, КПП ${entry_checkpoint}']],
            101 => ['groups' => ['${document_date}']],
            103 => ['rules' => [
                ['replace' => ['Генеральный директор', '${signer_position}']],
                ['groups' => ['${signer_name}']],
            ]],
        ],
    ],
    // Визовая анкета — установленная форма (приказ МВД/ФСБ/МИД № 233/235/7018), без бланка.
    'visa_questionnaire' => [
        'source' => 'Визовая анкета.docx',
        'map' => [
            // Кратность визы (клетки слева от подписей).
            15 => ['insert' => '${visa_multiplicity_single}', 'like' => 18],
            32 => ['insert' => '${visa_multiplicity_double}', 'like' => 18],
            44 => ['text' => '${visa_multiplicity_multiple}'],
            // Категория (вид) визы.
            21 => ['insert' => '${visa_category_private}', 'like' => 18],
            23 => ['insert' => '${visa_category_humanitarian}', 'like' => 18],
            25 => ['insert' => '${visa_category_business}', 'like' => 18],
            27 => ['text' => '${visa_category_work}'],
            35 => ['insert' => '${visa_category_tourist}', 'like' => 18],
            37 => ['insert' => '${visa_category_tourist_group}', 'like' => 18],
            39 => ['insert' => '${visa_category_study}', 'like' => 18],
            47 => ['insert' => '${visa_category_reception}', 'like' => 18],
            63 => ['replace' => ['Заключением трудового договора', '${visa_reason}']],
            69 => ['groups' => ['${last_name}']],
            71 => ['groups' => ['${last_name_lat}']],
            77 => ['groups' => ['${first_name}']],
            79 => ['groups' => ['${first_name_lat}']],
            85 => ['text' => '${middle_name}'],
            87 => ['text' => '${middle_name_lat}'],
            94 => ['groups' => ['${birth_date}']],
            96 => ['groups' => ['${birth_place}']],
            98 => ['groups' => ['${gender_male}']],
            100 => ['insert' => '${gender_female}', 'like' => 98],
            110 => ['groups' => ['${citizenship}']],
            114 => ['insert' => '${passport_series}', 'like' => 116],
            116 => ['groups' => ['${passport_number}']],
            118 => ['groups' => ['${passport_issue_date}']],
            120 => ['groups' => ['${passport_expire_date}']],
            129 => ['groups' => ['${inviting_party_1}']],
            131 => ['groups' => ['${inviting_party_2}']],
            133 => ['groups' => ['${receiving_party}']],
            135 => ['groups' => ['${receiving_address}']],
            137 => ['groups' => ['${registration_address}']],
            140 => ['groups' => ['${route_1}']],
            142 => ['groups' => ['${route_2}']],
            144 => ['groups' => ['${home_address}']],
            148 => ['groups' => ['${workplace_1}']],
            150 => ['groups' => ['${workplace_2}']],
            182 => ['groups' => ['${visa_series}']],
            184 => ['groups' => ['${visa_number}']],
            186 => ['groups' => ['${visa_identifier}']],
            188 => ['groups' => ['${visa_issue_date}']],
            190 => ['groups' => ['${visa_expire_date}']],
            192 => ['groups' => ['${invitation_number}']],
            221 => ['groups' => ['${document_date}']],
        ],
    ],
    // Опись документов, подаваемых на продление визы (несколько сотрудников).
    'visa_inventory' => [
        'source' => 'Опись на визу.docx',
        'map' => [
            9 => ['replace' => ['Московской области', '${region_dative}']],
            12 => ['groups' => ['${org_short_name} ']],
            21 => ['groups' => ['${citizenship}']],
            22 => ['groups' => ['${full_name}']],
            23 => ['groups' => ['${passport}']],
            63 => ['replace' => ['Генеральный директор', '${signer_position}']],
            // Линия подписи короче, чтобы «ООО "…"» заглавными и ФИО уместились в строку.
            64 => ['rules' => [
                ['replace' => ['____________', '____']],
                ['groups' => ['${org_short_name}', '${signer_name}']],
            ]],
        ],
        'keep_first_data_row' => true,
    ],

];

function xp(DOMDocument $d): DOMXPath
{
    $x = new DOMXPath($d);
    $x->registerNamespace('w', W_NS);

    return $x;
}

function isRed(DOMXPath $x, DOMElement $run): bool
{
    $c = $x->query('w:rPr/w:color/@w:val', $run)->item(0);

    return $c && strtoupper($c->value) === 'FF0000';
}

function runHasContent(DOMXPath $x, DOMElement $run): bool
{
    return $x->query('w:t[normalize-space(.) != "" or . = " "]|w:tab|w:br', $run)->length > 0;
}

function stripColor(DOMXPath $x, DOMElement $run): void
{
    foreach (iterator_to_array($x->query('w:rPr/w:color', $run)) as $color) {
        $color->parentNode->removeChild($color);
    }
}

function setRunText(DOMDocument $d, DOMXPath $x, DOMElement $run, string $text): void
{
    foreach (iterator_to_array($run->childNodes) as $child) {
        if ($child->nodeName !== 'w:rPr') {
            $run->removeChild($child);
        }
    }

    // \t в шаблоне — табуляция Word, остальное — текст.
    foreach (preg_split('/(\\\\t)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $piece) {
        if ($piece === '\\t') {
            $run->appendChild($d->createElementNS(W_NS, 'w:tab'));
            continue;
        }

        $t = $d->createElementNS(W_NS, 'w:t', $piece);
        $t->setAttributeNS('http://www.w3.org/XML/1998/namespace', 'xml:space', 'preserve');
        $run->appendChild($t);
    }
}

// Красные группы абзаца: подряд идущие красные run'ы; чёрный run с текстом
// группу разрывает.
function redGroups(DOMXPath $x, DOMElement $p): array
{
    $groups = [];
    $current = null;

    foreach ($p->childNodes as $child) {
        if (!($child instanceof DOMElement) || $child->localName !== 'r') {
            continue;
        }

        if (isRed($x, $child)) {
            $current ??= count($groups);
            $groups[$current][] = $child;
        } elseif (runHasContent($x, $child)) {
            $current = null;
        }
    }

    return $groups;
}

function newRunLike(DOMDocument $d, DOMXPath $x, DOMElement $p, ?DOMElement $likeParagraph): DOMElement
{
    $run = $d->createElementNS(W_NS, 'w:r');
    $source = null;

    if ($likeParagraph) {
        $red = redGroups($x, $likeParagraph);
        $sourceRun = $red ? $red[array_key_first($red)][0] : $x->query('w:r[w:rPr]', $likeParagraph)->item(0);
        $source = $sourceRun ? $x->query('w:rPr', $sourceRun)->item(0) : null;
        $source ??= $x->query('w:pPr/w:rPr', $likeParagraph)->item(0);
    }

    $source ??= $x->query('w:pPr/w:rPr', $p)->item(0);

    if ($source) {
        $run->appendChild($source->cloneNode(true));
    }

    return $run;
}

function transform(string $xml, array $map, bool $keepFirstDataRow, float $fitTables = 0.0, float $fontScale = 1.0): string
{
    $d = new DOMDocument();
    $d->preserveWhiteSpace = true;
    $d->loadXML($xml);
    $x = xp($d);
    $paragraphs = iterator_to_array($x->query('//w:p'));

    foreach ($map as $index => $ruleSet) {
        $p = $paragraphs[$index] ?? throw new RuntimeException("Нет абзаца $index");

        // Несколько правил на один абзац: ['rules' => [правило1, правило2]].
        foreach ($ruleSet['rules'] ?? [$ruleSet] as $rule) {
        $like = isset($rule['like']) ? $paragraphs[$rule['like']] : null;

        if (isset($rule['groups'])) {
            $groups = array_values(redGroups($x, $p));

            if (count($groups) !== count($rule['groups'])) {
                throw new RuntimeException("Абзац $index: красных групп " . count($groups) . ', ожидалось ' . count($rule['groups']));
            }

            foreach ($groups as $i => $runs) {
                setRunText($d, $x, $runs[0], $rule['groups'][$i]);
                stripColor($x, $runs[0]);

                if (isset($rule['size'])) {
                    foreach (['sz', 'szCs'] as $tag) {
                        foreach ($x->query('w:rPr/w:' . $tag, $runs[0]) as $size) {
                            $size->setAttributeNS(W_NS, 'w:val', (string) $rule['size']);
                        }
                    }
                }

                foreach (array_slice($runs, 1) as $extra) {
                    $p->removeChild($extra);
                }
            }
        } elseif (isset($rule['before_tab'])) {
            // Значение вписывается в подчёркнутую «строку» из табуляций: перед
            // первой табуляцией и с её оформлением (подчёркивание).
            $tabRun = $x->query('w:r[w:tab]', $p)->item(0) ?? throw new RuntimeException("Абзац $index без табуляции");
            $run = $d->createElementNS(W_NS, 'w:r');
            $props = $x->query('w:rPr', $tabRun)->item(0);

            if ($props) {
                $run->appendChild($props->cloneNode(true));
            }

            setRunText($d, $x, $run, $rule['before_tab']);
            $p->insertBefore($run, $tabRun);
        } elseif (isset($rule['leader_line'])) {
            // Линия из подряд идущих табуляций (не вмещает текст — переносится)
            // заменяется табуляцией с линией-заполнителем до правого поля.
            $tabRuns = iterator_to_array($x->query('w:r[w:tab]', $p));
            $first = $tabRuns[0] ?? throw new RuntimeException("Абзац $index без табуляции");
            $props = $x->query('w:rPr', $first)->item(0);

            $value = $d->createElementNS(W_NS, 'w:r');
            $props && $value->appendChild($props->cloneNode(true));
            setRunText($d, $x, $value, $rule['leader_line']);
            $p->insertBefore($value, $first);

            foreach (array_slice($tabRuns, 1) as $extra) {
                $p->removeChild($extra);
            }

            $tabs = $d->createElementNS(W_NS, 'w:tabs');
            $tab = $d->createElementNS(W_NS, 'w:tab');
            $tab->setAttributeNS(W_NS, 'w:val', 'right');
            $tab->setAttributeNS(W_NS, 'w:leader', 'underscore');
            $tab->setAttributeNS(W_NS, 'w:pos', '9354');
            $tabs->appendChild($tab);
            $pPr = $x->query('w:pPr', $p)->item(0);
            $pPr->insertBefore($tabs, $pPr->firstChild);
        } elseif (isset($rule['fill_underscores'])) {
            // Линия из подчёркиваний заменяется значением (с тем же подчёркиванием).
            $first = null;

            foreach (iterator_to_array($x->query('w:r[w:t]', $p)) as $run) {
                if (preg_match('/^_+$/u', $run->textContent)) {
                    if ($first === null) {
                        $first = $run;
                        setRunText($d, $x, $run, $rule['fill_underscores']);
                    } else {
                        $p->removeChild($run);
                    }
                }
            }

            $first ?? throw new RuntimeException("Абзац $index без линии подчёркиваний");
        } elseif (isset($rule['insert'])) {
            $run = newRunLike($d, $x, $p, $like);
            stripColor($x, $run);
            setRunText($d, $x, $run, $rule['insert']);
            $p->appendChild($run);
        } elseif (isset($rule['text'])) {
            $runs = iterator_to_array($x->query('w:r[w:t]', $p));
            $first = $runs[0] ?? throw new RuntimeException("Абзац $index без текста");
            setRunText($d, $x, $first, $rule['text']);

            foreach (array_slice($runs, 1) as $extra) {
                $p->removeChild($extra);
            }
        } elseif (isset($rule['replace'])) {
            [$from, $to] = $rule['replace'];
            $done = false;

            foreach ($x->query('.//w:t', $p) as $t) {
                if (str_contains($t->textContent, $from)) {
                    $t->textContent = str_replace($from, $to, $t->textContent);
                    $done = true;
                    break;
                }
            }

            if (!$done) {
                throw new RuntimeException("Абзац $index: нет текста «$from»");
            }
        }
        }
    }

    if ($keepFirstDataRow) {
        // Таблица описи: заголовок + строки данных. Оставляем одну строку данных.
        $rows = iterator_to_array($x->query('//w:tbl[1]/w:tr'));

        foreach (array_slice($rows, 2) as $row) {
            $row->parentNode->removeChild($row);
        }
    }

    if ($fitTables > 0) {
        fitTables($d, $x, $fitTables, $fontScale);
    }

    return $d->saveXML();
}

// Таблицы верхнего уровня масштабируются (ширины столбцов и ячеек), выравниваются
// по центру, поля листа по бокам — поровну.
function fitTables(DOMDocument $d, DOMXPath $x, float $scale, float $fontScale): void
{
    $width = 0;

    foreach ($x->query('/w:document/w:body/w:tbl') as $table) {
        foreach ($x->query('.//w:gridCol|.//w:tcW', $table) as $el) {
            if ($el->localName === 'tcW' && $el->getAttributeNS(W_NS, 'type') !== 'dxa') {
                continue;
            }

            $el->setAttributeNS(W_NS, 'w:w', (string) round((int) $el->getAttributeNS(W_NS, 'w') * $scale));
        }

        $sum = 0;

        foreach ($x->query('w:tblGrid/w:gridCol', $table) as $col) {
            $sum += (int) $col->getAttributeNS(W_NS, 'w');
        }

        $tblPr = $x->query('w:tblPr', $table)->item(0);
        $tblW = $x->query('w:tblW', $tblPr)->item(0);
        $tblW->setAttributeNS(W_NS, 'w:w', (string) $sum);

        foreach (iterator_to_array($x->query('w:jc|w:tblInd', $tblPr)) as $old) {
            $tblPr->removeChild($old);
        }

        $jc = $d->createElementNS(W_NS, 'w:jc');
        $jc->setAttributeNS(W_NS, 'w:val', 'center');
        $tblW->parentNode->insertBefore($jc, $tblW->nextSibling);
        $width = max($width, $sum);
    }

    foreach ($x->query('//w:sz|//w:szCs') as $size) {
        $size->setAttributeNS(W_NS, 'w:val', (string) max(8, round((int) $size->getAttributeNS(W_NS, 'val') * $fontScale)));
    }

    foreach ($x->query('//w:ind') as $ind) {
        foreach (['left', 'right', 'firstLine', 'hanging', 'start', 'end'] as $attr) {
            if ($ind->hasAttributeNS(W_NS, $attr)) {
                $ind->setAttributeNS(W_NS, 'w:' . $attr, (string) round((int) $ind->getAttributeNS(W_NS, $attr) * $scale));
            }
        }
    }

    $side = (string) intdiv(11906 - $width, 2);

    foreach ($x->query('//w:sectPr/w:pgMar') as $margin) {
        $margin->setAttributeNS(W_NS, 'w:left', $side);
        $margin->setAttributeNS(W_NS, 'w:right', $side);
    }
}

foreach ($templates as $name => $config) {
    $source = $root . '/samples/' . $config['source'];
    $target = $root . '/processes/' . $name . '.docx';
    copy($source, $target);

    $zip = new ZipArchive();
    $zip->open($target) === true || throw new RuntimeException("Не открыть $target");
    $xml = transform($zip->getFromName('word/document.xml'), $config['map'], $config['keep_first_data_row'] ?? false, (float) ($config['fit_tables'] ?? 0), (float) ($config['font_scale'] ?? 1));
    $zip->addFromString('word/document.xml', $xml);

    if (($config['fit_tables'] ?? 0) > 0 && $config['fit_tables'] < 1) {
        $styles = preg_replace_callback('/<w:(sz|szCs) w:val="(\d+)"/', static fn($m) => '<w:' . $m[1] . ' w:val="' . max(8, round((int) $m[2] * $config['font_scale'])) . '"', $zip->getFromName('word/styles.xml'));
        // Размер по умолчанию в образце не задан (Word берёт 10 пт) — задаём явно.
        $base = (int) round(20 * $config['font_scale']);
        $styles = preg_replace('/(<w:rPrDefault><w:rPr>.*?)(<w:lang )/s', '$1<w:sz w:val="' . $base . '"/><w:szCs w:val="' . $base . '"/>$2', $styles, 1);
        $zip->addFromString('word/styles.xml', $styles);
    }

    // Режим просмотра — «Разметка страницы»: без этого Word (особенно в защищённом
    // просмотре) открывает файл как веб-документ, и текст тянется на всю ширину окна.
    $settings = $zip->getFromName('word/settings.xml');

    if (!str_contains($settings, '<w:view ')) {
        $zip->addFromString('word/settings.xml', preg_replace('/<w:zoom\b/', '<w:view w:val="print"/><w:zoom', $settings, 1));
    }

    $zip->close();

    echo "$name: ok (" . substr_count($xml, '${') . " плейсхолдеров)\n";
}
