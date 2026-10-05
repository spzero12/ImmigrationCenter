<?php

// Формирование .docx по шаблону: шаблон (templates/processes/*.docx, см.
// templates/build_templates.php) копируется целиком — стили, колонтитулы,
// шрифты, разметка — и в нём подставляются значения ${ключ}.
// Если у организации загружен бланк (.docx), его колонтитулы (логотип,
// реквизиты) переносятся в итоговый документ.

const DOCX_TEMPLATE_DIR = __DIR__ . '/../templates/processes/';

function docxEsc(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function docxFillPlaceholders(string $xml, array $values): string
{
    return preg_replace_callback('/\$\{([a-z0-9_]+)\}/', static function ($m) use ($values) {
        return docxEsc((string) ($values[$m[1]] ?? ''));
    }, $xml);
}

// $template — имя файла без расширения; $values — ключ => значение;
// $rows — необязательно: строки таблицы, которые размножают первую строку,
// содержащую плейсхолдеры строки (['n' => ..., ...] на каждую).
// Возвращает путь ко временному файлу (отдать через docxSend).
function docxFromTemplate(string $template, array $values, ?string $letterheadPath = null, array $rows = []): string
{
    $source = DOCX_TEMPLATE_DIR . $template . '.docx';

    if (!is_file($source)) {
        throw new RuntimeException('Шаблон не найден: ' . $template);
    }

    $out = tempnam(sys_get_temp_dir(), 'docx');
    copy($source, $out);

    $zip = new ZipArchive();

    if ($zip->open($out) !== true) {
        throw new RuntimeException('Не удалось открыть шаблон ' . $template);
    }

    $document = $zip->getFromName('word/document.xml');

    if ($rows) {
        $document = docxRepeatRow($document, $rows);
    }

    $zip->addFromString('word/document.xml', docxFillPlaceholders($document, $values));

    if ($letterheadPath && is_file($letterheadPath)) {
        docxApplyLetterhead($zip, $letterheadPath);
    }

    $zip->close();

    return $out;
}

// Первая строка таблицы с плейсхолдерами строки (${full_name}) размножается по $rows.
function docxRepeatRow(string $document, array $rows): string
{
    if (!preg_match_all('/<w:tr[ >].*?<\/w:tr>/s', $document, $matches, PREG_OFFSET_CAPTURE)) {
        return $document;
    }

    foreach ($matches[0] as [$rowXml, $offset]) {
        if (!str_contains($rowXml, '${full_name}')) {
            continue;
        }

        $copies = '';

        foreach ($rows as $row) {
            $copies .= docxFillPlaceholders($rowXml, $row);
        }

        return substr($document, 0, $offset) . $copies . substr($document, $offset + strlen($rowXml));
    }

    return $document;
}

// Переносит header/footer бланка в документ: части и их rels/картинки
// копируются под префиксом lh_, ссылки прописываются в каждый sectPr.
function docxApplyLetterhead(ZipArchive $zip, string $letterheadPath): void
{
    $lh = new ZipArchive();

    if ($lh->open($letterheadPath) !== true) {
        return;
    }

    $lhDocument = $lh->getFromName('word/document.xml');
    $lhRels = $lh->getFromName('word/_rels/document.xml.rels');
    $lhTypes = $lh->getFromName('[Content_Types].xml');
    $rels = $zip->getFromName('word/_rels/document.xml.rels');
    $types = $zip->getFromName('[Content_Types].xml');
    $document = $zip->getFromName('word/document.xml');

    if ($lhDocument === false || $lhRels === false || $lhTypes === false || $rels === false || $types === false || $document === false) {
        $lh->close();

        return;
    }

    if (!preg_match('/<w:sectPr\b.*<\/w:sectPr>/s', $lhDocument, $sect)
        || !preg_match_all('/<w:(header|footer)Reference\b[^>]*\/>/', $sect[0], $refs, PREG_SET_ORDER)) {
        $lh->close();

        return;
    }

    // id => [type, target] из rels бланка.
    $targets = [];

    if (preg_match_all('/<Relationship\b[^>]*>/', $lhRels, $relTags)) {
        foreach ($relTags[0] as $tag) {
            if (preg_match('/Id="([^"]+)"/', $tag, $id) && preg_match('/Type="([^"]+)"/', $tag, $type) && preg_match('/Target="([^"]+)"/', $tag, $target)) {
                $targets[$id[1]] = [$type[1], $target[1]];
            }
        }
    }

    $newRefs = '';
    $newRels = '';
    $newTypes = '';
    $n = 0;

    foreach ($refs as $ref) {
        if (!preg_match('/r:id="([^"]+)"/', $ref[0], $rid) || !isset($targets[$rid[1]])) {
            continue;
        }

        [$relType, $target] = $targets[$rid[1]];
        $part = basename($target);
        $content = $lh->getFromName('word/' . $target);

        if ($content === false) {
            continue;
        }

        $n++;
        $newName = 'lh_' . $part;
        $zip->addFromString('word/' . $newName, $content);

        // Картинки и прочее, на что ссылается колонтитул.
        $partRels = $lh->getFromName('word/_rels/' . $part . '.rels');

        if ($partRels !== false) {
            $partRels = preg_replace_callback('/Target="([^"#]+)"(?![^>]*TargetMode="External")/', static function ($m) use ($lh, $zip) {
                $media = $lh->getFromName('word/' . $m[1]);

                if ($media === false) {
                    return $m[0];
                }

                $newMedia = dirname($m[1]) . '/lh_' . basename($m[1]);
                $zip->addFromString('word/' . $newMedia, $media);

                return 'Target="' . $newMedia . '"';
            }, $partRels);
            $zip->addFromString('word/_rels/' . $newName . '.rels', $partRels);
        }

        $newId = 'rIdLh' . $n;
        $newRels .= '<Relationship Id="' . $newId . '" Type="' . $relType . '" Target="' . $newName . '"/>';
        $newRefs .= preg_replace('/r:id="[^"]+"/', 'r:id="' . $newId . '"', $ref[0]);

        if (preg_match('/<Override\b[^>]*PartName="\/word\/' . preg_quote($part, '/') . '"[^>]*ContentType="([^"]+)"/', $lhTypes, $ct)
            || preg_match('/<Override\b[^>]*ContentType="([^"]+)"[^>]*PartName="\/word\/' . preg_quote($part, '/') . '"/', $lhTypes, $ct)) {
            $newTypes .= '<Override PartName="/word/' . $newName . '" ContentType="' . $ct[1] . '"/>';
        }
    }

    if ($newRefs === '') {
        $lh->close();

        return;
    }

    // Расширения файлов картинок, которых нет в шаблоне.
    if (preg_match_all('/<Default\b[^>]*>/', $lhTypes, $defaults)) {
        foreach ($defaults[0] as $default) {
            if (preg_match('/Extension="([^"]+)"/', $default, $ext) && stripos($types, 'Extension="' . $ext[1] . '"') === false) {
                $newTypes .= $default;
            }
        }
    }

    $zip->addFromString('word/_rels/document.xml.rels', str_replace('</Relationships>', $newRels . '</Relationships>', $rels));
    $zip->addFromString('[Content_Types].xml', str_replace('</Types>', $newTypes . '</Types>', $types));

    // Во всех секциях шаблона заменить колонтитулы на колонтитулы бланка.
    $document = preg_replace_callback('/<w:sectPr\b[^>]*>/', static fn($m) => $m[0] . $newRefs, preg_replace('/<w:(header|footer)Reference\b[^>]*\/>/', '', $document));
    $zip->addFromString('word/document.xml', $document);

    $lh->close();
}

function docxSend(string $tmpPath, string $downloadName): void
{
    $asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $downloadName) ?: 'document.docx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $asciiName . '"; filename*=UTF-8\'\'' . rawurlencode($downloadName));
    header('Content-Length: ' . filesize($tmpPath));
    readfile($tmpPath);
    unlink($tmpPath);
    exit;
}
