-- Объекты, на которых работают сотрудники (название, описание, адрес).
CREATE TABLE IF NOT EXISTS work_sites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    address VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_work_sites_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE employees
    ADD COLUMN work_site_id INT UNSIGNED NULL,
    ADD CONSTRAINT fk_employees_work_site FOREIGN KEY (work_site_id) REFERENCES work_sites(id) ON DELETE SET NULL;
