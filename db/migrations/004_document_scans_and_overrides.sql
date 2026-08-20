ALTER TABLE employee_documents
    ADD COLUMN scan_stored_name VARCHAR(255) NULL,
    ADD COLUMN scan_original_name VARCHAR(255) NULL,
    ADD COLUMN scan_mime_type VARCHAR(100) NULL,
    ADD COLUMN scan_size INT UNSIGNED NULL,
    ADD COLUMN field_overrides JSON NULL;
