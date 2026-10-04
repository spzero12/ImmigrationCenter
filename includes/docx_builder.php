<?php

// Минимальный генератор .docx: тело документа собирается как
// WordprocessingML напрямую, без внешних библиотек. Если у организации есть
// бланк (.docx), он берётся за основу — колонтитулы (логотип, реквизиты) и
// параметры страницы сохраняются, подменяется только тело документа.

function docxEsc(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

// $opts: bold, italic, underline, size (пт), color
function docxRun(string $text, array $opts = []): string
{
    $size = (int) (($opts['size'] ?? 12) * 2);
    $props = '<w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/>'
        . (!empty($opts['bold']) ? '<w:b/>' : '')
        . (!empty($opts['italic']) ? '<w:i/>' : '')
        . (!empty($opts['color']) ? '<w:color w:val="' . $opts['color'] . '"/>' : '')
        . '<w:sz w:val="' . $size . '"/><w:szCs w:val="' . $size . '"/>'
        . (!empty($opts['underline']) ? '<w:u w:val="single"/>' : '');

    $parts = explode("\n", $text);
    $xml = '';

    foreach ($parts as $i => $part) {
        $xml .= ($i > 0 ? '<w:br/>' : '') . '<w:t xml:space="preserve">' . docxEsc($part) . '</w:t>';
    }

    return '<w:r><w:rPr>' . $props . '</w:rPr>' . $xml . '</w:r>';
}

// $content — строка или массив готовых run'ов.
// $opts: align (left|center|right|both), after, before (пт), indent и
// right_indent (twips), border_top, плюс опции run'а (bold, size...) для строки.
function docxPara($content, array $opts = []): string
{
    $runXml = is_array($content) ? implode('', $content) : docxRun((string) $content, $opts);
    $props = '';

    if (!empty($opts['border_top'])) {
        $props .= '<w:pBdr><w:top w:val="single" w:sz="4" w:space="1" w:color="000000"/></w:pBdr>';
    }

    $props .= '<w:spacing w:before="' . (int) (($opts['before'] ?? 0) * 20) . '" w:after="' . (int) (($opts['after'] ?? 6) * 20) . '"/>';

    if (isset($opts['indent']) || isset($opts['right_indent'])) {
        $props .= '<w:ind w:left="' . (int) ($opts['indent'] ?? 0) . '" w:right="' . (int) ($opts['right_indent'] ?? 0) . '"/>';
    }

    if (!empty($opts['align'])) {
        $props .= '<w:jc w:val="' . $opts['align'] . '"/>';
    }

    return '<w:p><w:pPr>' . $props . '</w:pPr>' . $runXml . '</w:p>';
}

// Подпись-пояснение мелким шрифтом под полем.
function docxCaption(string $text, array $opts = []): string
{
    return docxPara($text, array_merge(['size' => 8, 'align' => 'center', 'after' => 8, 'border_top' => true], $opts));
}

// $rows — массив строк, строка — массив ячеек; ячейка — строка либо
// ['text' => ..., 'bold' => bool, 'align' => ..., 'span' => int, 'size' => int, 'shade' => 'D9D9D9'].
// $widths — ширины столбцов в twips (сумма ≈ 9500 для А4 с полями).
function docxTable(array $rows, array $widths, bool $borders = true): string
{
    $border = $borders
        ? '<w:tblBorders>'
            . implode('', array_map(static fn($side) => '<w:' . $side . ' w:val="single" w:sz="4" w:space="0" w:color="000000"/>', ['top', 'left', 'bottom', 'right', 'insideH', 'insideV']))
            . '</w:tblBorders>'
        : '';

    $xml = '<w:tbl><w:tblPr><w:tblW w:w="' . array_sum($widths) . '" w:type="dxa"/>' . $border
        . '<w:tblLayout w:type="fixed"/><w:tblCellMar><w:left w:w="80" w:type="dxa"/><w:right w:w="80" w:type="dxa"/></w:tblCellMar></w:tblPr><w:tblGrid>';

    foreach ($widths as $w) {
        $xml .= '<w:gridCol w:w="' . $w . '"/>';
    }

    $xml .= '</w:tblGrid>';

    foreach ($rows as $row) {
        $xml .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>';
        $col = 0;

        foreach ($row as $cell) {
            $cell = is_array($cell) ? $cell : ['text' => (string) $cell];
            $span = $cell['span'] ?? 1;
            $width = array_sum(array_slice($widths, $col, $span));
            $col += $span;

            $xml .= '<w:tc><w:tcPr><w:tcW w:w="' . $width . '" w:type="dxa"/>'
                . ($span > 1 ? '<w:gridSpan w:val="' . $span . '"/>' : '')
                . (!empty($cell['shade']) ? '<w:shd w:val="clear" w:color="auto" w:fill="' . $cell['shade'] . '"/>' : '')
                . '</w:tcPr>'
                . docxPara($cell['text'] ?? '', [
                    'bold' => $cell['bold'] ?? false,
                    'size' => $cell['size'] ?? 10,
                    'align' => $cell['align'] ?? 'left',
                    'after' => 2,
                    'before' => 2,
                ])
                . '</w:tc>';
        }

        $xml .= '</w:tr>';
    }

    return $xml . '</w:tbl>' . docxPara('', ['after' => 4, 'size' => 4]);
}

// Собирает .docx и возвращает путь ко временному файлу (вызывающий
// обязан отдать/удалить его, см. docxSend).
function docxBuild(string $bodyXml, ?string $letterheadPath = null): string
{
    $out = tempnam(sys_get_temp_dir(), 'docx');

    if ($letterheadPath && is_file($letterheadPath)) {
        copy($letterheadPath, $out);
        $zip = new ZipArchive();

        if ($zip->open($out) === true) {
            $document = $zip->getFromName('word/document.xml');
            $bodyStart = $document !== false ? strpos($document, '<w:body>') : false;
            $sectPos = $document !== false ? strrpos($document, '<w:sectPr') : false;

            if ($bodyStart !== false && $sectPos !== false && $sectPos > $bodyStart) {
                $document = substr($document, 0, $bodyStart + 8) . $bodyXml . substr($document, $sectPos);
                $zip->addFromString('word/document.xml', $document);
                $zip->close();

                return $out;
            }

            $zip->close();
        }
        // Бланк не разобрался — формируем документ без него.
    }

    $zip = new ZipArchive();
    $zip->open($out, ZipArchive::OVERWRITE | ZipArchive::CREATE);
    $zip->addFromString(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
        . '</Types>'
    );
    $zip->addFromString(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
        . '</Relationships>'
    );
    $zip->addFromString(
        'word/document.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
        . $bodyXml
        . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="850" w:bottom="1134" w:left="1417" w:header="709" w:footer="709" w:gutter="0"/></w:sectPr>'
        . '</w:body></w:document>'
    );
    $zip->close();

    return $out;
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
