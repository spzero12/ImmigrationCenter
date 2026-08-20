ALTER TABLE employees
    ADD COLUMN organization VARCHAR(255) NULL AFTER department;

ALTER TABLE employee_documents
    ADD COLUMN passport_document_id INT UNSIGNED NULL AFTER employee_id;

-- Без CHECK (type != 'passport' OR passport_document_id IS NULL): эта
-- версия MariaDB (12.3.2) отказывается добавлять self-referencing FOREIGN
-- KEY на колонку, уже участвующую в CHECK constraint ("Function or
-- expression 'passport_document_id' cannot be used in the CHECK clause") —
-- воспроизводится стабильно, независимо от порядка добавления. Правило
-- "паспорт не ссылается сам на себя" гарантируется в PHP
-- (document_form.php всегда передаёт NULL для type = 'passport').
ALTER TABLE employee_documents
    ADD CONSTRAINT fk_employee_documents_passport FOREIGN KEY (passport_document_id) REFERENCES employee_documents(id) ON DELETE SET NULL;

-- Бэкофилл: привязать существующие непаспортные документы к паспорту
-- сотрудника с максимальной issue_date (то, что раньше вычисляла
-- getCanonicalPassport() на лету). Если у сотрудника несколько паспортов
-- с одинаковой/пустой issue_date — выбирается один произвольно, это
-- разовый снимок, а не точное восстановление истории.
UPDATE employee_documents d
JOIN (
    SELECT p1.employee_id, p1.id AS passport_id
    FROM employee_documents p1
    WHERE p1.type = 'passport'
      AND p1.issue_date = (
          SELECT MAX(p2.issue_date) FROM employee_documents p2
          WHERE p2.employee_id = p1.employee_id AND p2.type = 'passport'
      )
) latest_passport ON latest_passport.employee_id = d.employee_id
SET d.passport_document_id = latest_passport.passport_id
WHERE d.type != 'passport' AND d.passport_document_id IS NULL;
