-- Данные сотрудника, нужные для ходатайства на приглашение (латиница,
-- пол, рождение, проживание, визовые данные).
ALTER TABLE employees
    ADD COLUMN last_name_lat VARCHAR(100) NULL,
    ADD COLUMN first_name_lat VARCHAR(100) NULL,
    ADD COLUMN middle_name_lat VARCHAR(100) NULL,
    ADD COLUMN gender ENUM('male', 'female') NULL,
    ADD COLUMN birth_country VARCHAR(100) NULL,
    ADD COLUMN birth_place VARCHAR(255) NULL,
    ADD COLUMN residence_country VARCHAR(100) NULL,
    ADD COLUMN visa_country VARCHAR(100) NULL COMMENT 'Страна получения визы',
    ADD COLUMN visa_city VARCHAR(100) NULL COMMENT 'Город получения визы',
    ADD COLUMN foreign_workplace VARCHAR(500) NULL,
    ADD COLUMN stay_address JSON NULL COMMENT 'Адрес предполагаемого места пребывания по частям';
