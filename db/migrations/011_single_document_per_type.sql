-- Фича "Единая карточка сотрудника": .agent-os/specs/2026-10-04-employee-card-processes/spec.md
-- У сотрудника ровно один документ каждого типа, без версий.
-- ВНИМАНИЕ: необратимо удаляет лишние документы — перед применением на
-- проде сделать бэкап БД. Остаётся документ с самым поздним сроком
-- действия (без срока — последним), при равенстве — с более поздней
-- датой выдачи, затем с большим id. Файлы сканов удалённых записей на
-- диске остаются (uploads/documents/), их чистит отдельно.

ALTER TABLE employee_documents
    MODIFY COLUMN type ENUM('passport', 'visa', 'work_patent', 'work_permit', 'registration', 'medical_certificate', 'dms') NOT NULL;

-- Паспорт теперь один — привязка документов к конкретному паспорту не нужна.
ALTER TABLE employee_documents DROP FOREIGN KEY fk_employee_documents_passport;
ALTER TABLE employee_documents DROP COLUMN passport_document_id;

DELETE d FROM employee_documents d
JOIN (
    SELECT id, ROW_NUMBER() OVER (
        PARTITION BY employee_id, type
        ORDER BY expire_date IS NULL, expire_date DESC, issue_date DESC, id DESC
    ) AS rn
    FROM employee_documents
) ranked ON ranked.id = d.id
WHERE ranked.rn > 1;

ALTER TABLE employee_documents
    ADD UNIQUE KEY uniq_employee_documents_employee_type (employee_id, type);
