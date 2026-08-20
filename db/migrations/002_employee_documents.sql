CREATE TABLE IF NOT EXISTS employee_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    type ENUM('passport', 'visa', 'work_patent', 'work_permit', 'registration') NOT NULL,
    number VARCHAR(50),
    series VARCHAR(20),
    issue_date DATE NULL,
    expire_date DATE NULL,
    issued_by VARCHAR(255),
    notes TEXT,
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_employee_documents_expire_date ON employee_documents (expire_date);
