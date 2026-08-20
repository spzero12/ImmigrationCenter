# Шаблоны документов

Сюда кладутся .docx-шаблоны для генерации ([document_generate.php](../document_generate.php)).

Имя файла = тип документа: `passport.docx`, `visa.docx`, `work_patent.docx`,
`work_permit.docx`, `registration.docx` (значения `type` из
`DOCUMENT_TYPE_LABELS` в [includes/documents.php](../includes/documents.php)).

Плейсхолдеры внутри .docx — синтаксис PHPWord `${имя}`, полный список и их
источники — в [.agent-os/specs/2026-08-20-document-generation/spec.md](../.agent-os/specs/2026-08-20-document-generation/spec.md).

Реальных шаблонов пока нет — папка пуста до появления файлов от заказчика.
