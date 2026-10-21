-- Процессы оформления документов (пока один тип — "invitation").
-- Один процесс каждого типа на сотрудника, без версий. Опись — групповой
-- документ и здесь не хранится.
CREATE TABLE IF NOT EXISTS employee_processes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id INT UNSIGNED NOT NULL,
    process_type VARCHAR(50) NOT NULL,
    fields JSON NULL COMMENT 'Общие данные процесса: поездка, адрес пребывания, исх. номер',
    created_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_employee_processes_type (employee_id, process_type),
    FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS process_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    process_id INT UNSIGNED NOT NULL,
    doc_type VARCHAR(50) NOT NULL,
    fields JSON NULL COMMENT 'Поля веб-формы документа',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_process_documents_type (process_id, doc_type),
    FOREIGN KEY (process_id) REFERENCES employee_processes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
