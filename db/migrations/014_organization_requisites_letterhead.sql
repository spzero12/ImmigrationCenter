-- Реквизиты организации для ходатайства/гарантийного письма и бланк (.docx
-- с колонтитулами), который применяется при формировании документов.
ALTER TABLE organizations
    ADD COLUMN full_name VARCHAR(500) NULL COMMENT 'Полное наименование с ОПФ',
    ADD COLUMN inn VARCHAR(12) NULL,
    ADD COLUMN ogrn VARCHAR(15) NULL,
    ADD COLUMN kpp VARCHAR(9) NULL,
    ADD COLUMN egrul_date DATE NULL COMMENT 'Дата внесения записи в ЕГРЮЛ',
    ADD COLUMN legal_address VARCHAR(500) NULL,
    ADD COLUMN actual_address VARCHAR(500) NULL,
    ADD COLUMN phone VARCHAR(50) NULL,
    ADD COLUMN email VARCHAR(150) NULL,
    ADD COLUMN letterhead_stored_name VARCHAR(255) NULL,
    ADD COLUMN letterhead_original_name VARCHAR(255) NULL;
