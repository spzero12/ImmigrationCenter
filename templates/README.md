# Шаблоны документов

Сюда кладутся .docx-шаблоны для генерации ([document_generate.php](../document_generate.php)).

Имя файла = тип документа: `passport.docx`, `visa.docx`, `work_patent.docx`,
`work_permit.docx`, `registration.docx` (значения `type` из
`DOCUMENT_TYPE_LABELS` в [includes/documents.php](../includes/documents.php)).

Плейсхолдеры внутри .docx — синтаксис PHPWord `${имя}`, полный список и их
источники — в [.agent-os/specs/2026-08-20-document-generation/spec.md](../.agent-os/specs/2026-08-20-document-generation/spec.md).

Реального шаблона от заказчика пока нет. `visa.docx` — собственный
заглушечный шаблон (сгенерирован через PHPWord, чтобы плейсхолдеры не были
случайно разбиты на отдельные XML-runs, как бывает при ручном наборе в
Word), использует все 18 плейсхолдеров из спецификации. Годится для
проверки механизма генерации, но не является официальной формой — когда
появится реальный шаблон от заказчика, заменить этот файл.
