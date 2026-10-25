-- Справочник регионов; регион сотрудника подставляется в документы
-- (например, "УВМ ГУ МВД России по ${region}").
CREATE TABLE IF NOT EXISTS regions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    name_dative VARCHAR(150) NOT NULL COMMENT 'Форма для адресата, например: г. Москве',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_regions_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO regions (name, name_dative) VALUES ('Москва', 'г. Москве');

ALTER TABLE employees
    ADD COLUMN region_id INT UNSIGNED NULL;

ALTER TABLE employees
    ADD CONSTRAINT fk_employees_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE SET NULL;
