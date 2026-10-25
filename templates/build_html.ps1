# Делает HTML-«листы» для страницы редактирования документа
# (process_document.php) из шаблонов templates/processes/*.docx: Word сохраняет
# их как «веб-страницу с фильтром», сохраняя вёрстку (таблицы, рамки, отступы,
# разрывы страниц). Плейсхолдеры ${ключ} остаются в тексте — на странице они
# превращаются в редактируемые поля (includes/document_sheet.php).
#
# Нужен установленный Microsoft Word. Запуск (после build_templates.php):
#   powershell -ExecutionPolicy Bypass -File templates\build_html.ps1

$dir = Join-Path $PSScriptRoot 'processes'
$word = New-Object -ComObject Word.Application
$word.Visible = $false
$word.DisplayAlerts = 0

try {
    foreach ($name in 'petition', 'guarantee_letter', 'visa_petition', 'visa_questionnaire') {
        $source = [string](Join-Path $dir "$name.docx")
        $doc = $word.Documents.Open($source, $false, $true)
        $doc.WebOptions.Encoding = 65001
        $doc.WebOptions.OrganizeInFolder = $true
        $tmp = [string](Join-Path $env:TEMP "$name.htm")
        $doc.SaveAs2($tmp, 10)   # wdFormatFilteredHTML
        $doc.Close($false)

        Copy-Item $tmp (Join-Path $dir "$name.html") -Force
        Remove-Item $tmp, (Join-Path $env:TEMP "$name.files") -Recurse -Force -ErrorAction SilentlyContinue
        Write-Output "$name.html"
    }
}
finally {
    $word.Quit()
}
