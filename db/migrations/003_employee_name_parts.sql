ALTER TABLE employees
    ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER full_name,
    ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT '' AFTER last_name,
    ADD COLUMN middle_name VARCHAR(100) NULL AFTER first_name;

-- Бэкофилл для существующих строк: наивное разбиение full_name по первым
-- двум пробелам (Фамилия / Имя / остальное — Отчество). Для нестандартных
-- ФИО (одно слово, несколько отчеств и т.п.) результат нужно проверить
-- вручную после миграции — см. tasks.md фичи document-generation.
UPDATE employees
SET
    last_name = TRIM(SUBSTRING_INDEX(full_name, ' ', 1)),
    first_name = TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(full_name, ' ', 2), ' ', -1)),
    middle_name = TRIM(SUBSTRING(full_name, LENGTH(SUBSTRING_INDEX(full_name, ' ', 2)) + 2))
WHERE last_name = '';

UPDATE employees SET middle_name = NULL WHERE middle_name = '';
