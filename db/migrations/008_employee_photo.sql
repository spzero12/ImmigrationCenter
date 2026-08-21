ALTER TABLE employees
    ADD COLUMN photo_stored_name VARCHAR(255) NULL,
    ADD COLUMN photo_original_name VARCHAR(255) NULL,
    ADD COLUMN photo_mime_type VARCHAR(100) NULL,
    ADD COLUMN photo_size INT UNSIGNED NULL;
