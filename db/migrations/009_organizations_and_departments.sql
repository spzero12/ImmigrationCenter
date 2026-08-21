CREATE TABLE IF NOT EXISTS organizations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_organizations_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS departments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_departments_org_name (organization_id, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE departments
    ADD CONSTRAINT fk_departments_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE;

ALTER TABLE employees
    ADD COLUMN organization_id INT UNSIGNED NULL AFTER organization,
    ADD COLUMN department_id INT UNSIGNED NULL AFTER department;

ALTER TABLE employees
    ADD CONSTRAINT fk_employees_organization FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE SET NULL;

ALTER TABLE employees
    ADD CONSTRAINT fk_employees_department FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL;

-- Автомиграция: организации/подразделения создаются из уже введённых
-- текстовых значений, сотрудники связываются с ними по совпадению текста.
INSERT INTO organizations (name)
SELECT DISTINCT organization FROM employees WHERE organization IS NOT NULL AND organization != '';

INSERT INTO departments (organization_id, name)
SELECT DISTINCT o.id, e.department
FROM employees e
JOIN organizations o ON o.name = e.organization
WHERE e.department IS NOT NULL AND e.department != '';

UPDATE employees e
JOIN organizations o ON o.name = e.organization
SET e.organization_id = o.id
WHERE e.organization IS NOT NULL AND e.organization != '';

UPDATE employees e
JOIN organizations o ON o.name = e.organization
JOIN departments d ON d.organization_id = o.id AND d.name = e.department
SET e.department_id = d.id
WHERE e.department IS NOT NULL AND e.department != '';

ALTER TABLE employees
    DROP COLUMN organization,
    DROP COLUMN department;
