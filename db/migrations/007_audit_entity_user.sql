ALTER TABLE audit_log
    MODIFY COLUMN entity_type ENUM('employee', 'employee_document', 'user') NOT NULL;
