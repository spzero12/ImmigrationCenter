-- Номер ИРС сотрудника — число (без ведущих нулей).
ALTER TABLE employees
    ADD COLUMN irs_number BIGINT UNSIGNED NULL COMMENT 'Номер ИРС' AFTER foreign_workplace;
